# reactphp-x-concurrent

## install 

```
composer require reactphp-x/concurrent -vvv
```

## usage

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

## License
MIT