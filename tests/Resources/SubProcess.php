#!/usr/bin/env php
<?php

require __DIR__.'/../bootstrap.php';

use Saxulum\Tests\ProcessesExecutor\Resources\SampleMessage;

if (!isset($argv[1])) {
    throw new \InvalidArgumentException('Missing child id');
}

for ($i = 0; $i < 100; ++$i) {
    $message = new SampleMessage($argv[1], sprintf('message %d', $i));

    echo $message->toJson().PHP_EOL;

    usleep($argv[1] * $i * 30);
}
