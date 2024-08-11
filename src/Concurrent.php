<?php

namespace ReactphpX\Concurrent;

use React\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;
use React\EventLoop\Loop;
use Psr\Http\Message\ResponseInterface;
use React\Stream\ReadableStreamInterface;


final class Concurrent
{
    private $limit;
    private $pending = 0;
    private $queue = [];

    /**
     * @param int $limit Maximum amount of concurrent requests handled.
     *
     * For example when $limit is set to 10, 10 requests will flow to $next
     * while more incoming requests have to wait until one is done.
     */
    public function __construct($limit)
    {
        $this->limit = $limit;
    }

    public function concurrent($callback)
    {
        // happy path: simply invoke next request handler if we're below limit
        if ($this->pending < $this->limit) {
            ++$this->pending;
            return $this->await(Promise\resolve($callback()));
        }
        // get next queue position
        $queue =& $this->queue;
        $queue[] = null;
        \end($queue);
        $id = \key($queue);

        $deferred = new Deferred(function ($_, $reject) use (&$queue, $id) {
            var_dump('Cancelled queued next handle');
            unset($queue[$id]);
            $reject(new \RuntimeException('Cancelled queued next handler'));
        });

        $queue[$id] = $deferred;

        $pending = &$this->pending;
        $that = $this;
        return $deferred->promise()->then(function () use (&$pending, $that,$callback) {
            // invoke next request handler
            ++$pending;
            return $that->await(Promise\resolve($callback()));
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
            if (interface_exists(ResponseInterface::class) && $data instanceof ResponseInterface) {
                $body = $data->getBody();
                if (interface_exists(ReadableStreamInterface::class) && $body instanceof ReadableStreamInterface && $body->isReadable()) {
                    $body->on('close', function () use ($that) {
                        $that->processQueue();
                    });
                } else {
                    $that->processQueue();
                }
            } 
            else if (interface_exists(ReadableStreamInterface::class) && $data instanceof ReadableStreamInterface && $data->isReadable()) {
                $data->on('close', function () use ($that) {
                    $that->processQueue();
                });
            } 
            else {
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

        $first = \reset($this->queue);
        unset($this->queue[key($this->queue)]);

        Loop::futureTick(function () use ($first) {
            $first->resolve(null);
        });
    }
}


