# saxulum-processes-executor

[![Build Status](https://api.travis-ci.org/saxulum/saxulum-processes-executor.png?branch=master)](https://travis-ci.org/saxulum/saxulum-processes-executor)
[![Total Downloads](https://poser.pugx.org/saxulum/saxulum-processes-executor/downloads.png)](https://packagist.org/packages/saxulum/saxulum-processes-executor)
[![Latest Stable Version](https://poser.pugx.org/saxulum/saxulum-processes-executor/v/stable.png)](https://packagist.org/packages/saxulum/saxulum-processes-executor)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/saxulum/saxulum-processes-executor/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/saxulum/saxulum-processes-executor/?branch=master)

## Description

A simple to use process executor.

## Requirements

 * php: ~7.0

## Installation

Through [Composer](http://getcomposer.org) as [saxulum/saxulum-processes-executor][1].

## Usage

### Start callback only

```{.php}
<?php

use Symfony\Component\Process\Process;
use Saxulum\ProcessesExecutor\ProcessesExecutor;

$processes = [
    new Process('php subprocess1.php'),
    new Process('php subprocess2.php'),
    new Process('php subprocess3.php'),
    new Process('php subprocess4.php'),
    new Process('php subprocess5.php'),
];

$output = '';
$errorOutput = '';

$executor = new ProcessesExecutor();
$executor->execute(
    $processes,
    function ($type, $buffer) use (&$output, &$errorOutput) {
        if (Process::OUT === $type) {
            $output .= $buffer;
        } elseif (Process::ERR === $type) {
            $errorOutput .= $buffer;
        }
    }
);
```

### Finish callback to get seperated output and error output per process

```{.php}
<?php

use Symfony\Component\Process\Process;
use Saxulum\ProcessesExecutor\ProcessesExecutor;

$processes = [
    new Process('php subprocess1.php'),
    new Process('php subprocess2.php'),
    new Process('php subprocess3.php'),
    new Process('php subprocess4.php'),
    new Process('php subprocess5.php'),
];

$outputs = [];
$errorOutputs = [];

$executor = new ProcessesExecutor();
$executor->execute(
    $processes,
    null,
    function (Process $process) use (&$outputs, &$errorOutputs) {
        $commandLine = $process->getCommandLine();
        if ('' !== $output = $process->getOutput()) {
            $outputs[$commandLine] = $output;
        }
        if ('' !== $errorOutput = $process->getErrorOutput()) {
            $errorOutputs[$commandLine] = $errorOutput;
        }
    }
);
```

### Iteration callback to get runtime information with a message queue

```{.php}
<?php

use Symfony\Component\Process\Process;
use Saxulum\MessageQueue\SystemV\SystemVReceive;
use Saxulum\ProcessesExecutor\ProcessesExecutor;

$processes = [
    new Process('php subprocess1.php --systemVKey=1'),
    new Process('php subprocess2.php --systemVKey=1'),
    new Process('php subprocess3.php --systemVKey=1'),
    new Process('php subprocess4.php --systemVKey=1'),
    new Process('php subprocess5.php --systemVKey=1'),
];

$messages = [];

$receiver = new SystemVReceive(<MessageInterface::class>, 1);

// make sure the queue is empty
while (null !== $message = $receiver->receive()) {}

$executor = new ProcessesExecutor();
$executor->execute(
    $processes,
    null,
    null,
    function (array $processes) use ($receiver, &$messages) {
        while (null !== $message = $receiver->receive()) {
            $messages[] = $message;
        }
    }
);
```

[1]: https://packagist.org/packages/saxulum/saxulum-processes-executor

## Copyright

Dominik Zogg 2016
