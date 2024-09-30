<?php

namespace ReactphpX\Concurrent;

use React\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;
use Psr\Http\Message\ResponseInterface;
use React\Stream\ReadableStreamInterface;


final class Concurrent
{
    private $stream = false;
    private $limit;
    private $maxLimit;
    private $pending = 0;
    private $queue = [];
    private $prioritizes = [];

    /**
     * @param int $limit Maximum amount of concurrent requests handled.
     *
     * For example when $limit is set to 10, 10 requests will flow to $next
     * while more incoming requests have to wait until one is done.
     */
    public function __construct($limit, $maxLimit = 0, $stream = false)
    {
        $this->limit = $limit;
        $this->maxLimit = $maxLimit;
        $this->stream = $stream;
    }

    public function concurrent($callback, $prioritize = 0)
    {
        // happy path: simply invoke next request handler if we're below limit
        if ($this->pending < $this->limit) {
            ++$this->pending;
            try {
                return $this->await(Promise\resolve($callback()));
            } catch (\Throwable $e) {
                $this->processQueue();
                throw $e;
            }
        }


        if ($this->maxLimit > 0 && ($this->pending + count($this->queue)) >= $this->maxLimit) {
            return Promise\reject(new \OverflowException('Max limit reached'));
        }

        // get next queue position
        $queue = &$this->queue;
        $prioritizes = &$this->prioritizes;
        $queue[] = null;
        \end($queue);
        $id = \key($queue);
        $prioritizes[$id] = $prioritize;
        arsort($prioritizes);
        $deferred = new Deferred(function ($_, $reject) use (&$queue, &$prioritizes, $id) {
            unset($queue[$id]);
            unset($prioritizes[$id]);
            $reject(new CancelledException('Cancelled queued next handler'));
        });

        $queue[$id] = $deferred;

        $pending = &$this->pending;
        $that = $this;
        return $deferred->promise()->then(function () use (&$pending, $that, $callback) {
            // invoke next request handler
            ++$pending;
            try {
                return $that->await(Promise\resolve($callback()));
            } catch (\Throwable $e) {
                $that->processQueue();
                throw $e;
            }
        });
    }

    /**
     * @internal
     * @param PromiseInterface $promise
     * @return PromiseInterface
     */
    public function await(PromiseInterface $promise)
    {
        $that = $this;
        return $promise->then(function ($data) use ($that) {
            if ($that->stream && interface_exists(ResponseInterface::class) && $data instanceof ResponseInterface) {
                $body = $data->getBody();
                if (interface_exists(ReadableStreamInterface::class) && $body instanceof ReadableStreamInterface && $body->isReadable()) {
                    $body->on('close', function () use ($that) {
                        $that->processQueue();
                    });
                } else {
                    $that->processQueue();
                }
            } else if ($that->stream && interface_exists(ReadableStreamInterface::class) && $data instanceof ReadableStreamInterface && $data->isReadable()) {
                $data->on('close', function () use ($that) {
                    $that->processQueue();
                });
            } else {
                $that->processQueue();
            }
            return $data;
        }, function ($error) use ($that) {
            $that->processQueue();
            return Promise\reject($error);
        });
    }

    /**
     * @internal
     */
    public function processQueue()
    {
        // skip if we're still above concurrency limit or there's no queued request waiting
        if (--$this->pending >= $this->limit || !$this->queue) {
            return;
        }

        reset($this->prioritizes);
        $id = key($this->prioritizes);
        $first = $this->queue[$id];
        unset($this->queue[$id]);
        unset($this->prioritizes[$id]);
        $first->resolve(null);
    }
}


