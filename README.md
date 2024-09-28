# reactphp-x-concurrent

## install 

```
composer require reactphp-x/concurrent -vvv
```

## usage

### base usage

```php

use ReactphpX\Concurrent\Concurrent;
use React\Promise\Deferred;
use React\EventLoop\Loop;

$concurrent = new Concurrent(10);

for ($i = 0; $i < 20; $i++) {
    $concurrent->concurrent(function () use ($i) {
        $deferred = new Deferred();
        echo "Request $i\n";
        Loop::addTimer($i, function () use ($deferred, $i) {
            $deferred->resolve($i);
        });
        return $deferred->promise();
    })->then(function ($result) {
        echo "Result $result\n";
    }, function ($error) {
        $message = $error->getMessage();
        echo "Error $message\n";
    });
}
```

### max concurrency

```php
use ReactphpX\Concurrent\Concurrent;
use React\Promise\Deferred;
use React\EventLoop\Loop;
// second param is max concurrency 0 is unlimited
$concurrent = new Concurrent(10, 10);

for ($i = 0; $i < 20; $i++) {
    $concurrent->concurrent(function () use ($i) {
        $deferred = new Deferred();
        echo "Request $i\n";
        Loop::addTimer($i, function () use ($deferred, $i) {
            $deferred->resolve($i);
        });
        return $deferred->promise();
    })->then(function ($result) {
        echo "Result $result\n";
    }, function ($error) use ($i) {
        if ($error instanceof \OverflowException) {
            echo "Error overflow $i\n";
        }
        $message = $error->getMessage();
        echo "Error $message\n";
    });
}
```

### stream support


当 stream close 后作为一次并发
    
```php
use ReactphpX\Concurrent\Concurrent;
use React\Promise\Deferred;
use React\EventLoop\Loop;

$concurrent = new Concurrent(10, 0, true);

for ($i = 0; $i < 20; $i++) {
    $concurrent->concurrent(function () use ($i) {
        $stream = new \React\Stream\ThroughStream();
        Loop::addTimer($i, function () use ($deferred, $i) {
            $stream->end($i);
        });
        // return \React\Promise\resove($stream);
        return $stream;
    })->then(function ($result) {
        echo "Result $result\n";
    }, function ($error) use ($i) {
        if ($error instanceof \OverflowException) {
            echo "Error overflow $i\n";
        }
        $message = $error->getMessage();
        echo "Error $message\n";
    });
}
```

### priority

first is executed result is 0;

second is executed result is 19;
second is executed result is 18;
second is executed result is 17;
second is executed result is 16;


$prioritize is max and it is executed at front of the queue

```php
use ReactphpX\Concurrent\Concurrent;
use React\Promise\Deferred;
use React\EventLoop\Loop;

$concurrent = new Concurrent(1, 0);

for ($i = 0; $i < 20; $i++) {
    $concurrent->concurrent(function () use ($i) {
        $deferred = new Deferred();
        echo "Request $i\n";
        Loop::addTimer(1, function () use ($deferred, $i) {
            $deferred->resolve($i);
        });
        return $deferred->promise();
    }, $i)->then(function ($result) {
        echo "Result $result\n";
    }, function ($error) use ($i) {
        $message = $error->getMessage();
        echo "Error $i $message\n";
    });
}
```





## License
MIT