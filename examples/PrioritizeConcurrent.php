<?php

require __DIR__ . '/../vendor/autoload.php';

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