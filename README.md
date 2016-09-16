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

#### Sample Message for parent / children communication

```{.php}
<?php

namespace My\Project;

use Saxulum\MessageQueue\MessageInterface;

class SampleMessage implements MessageInterface
{
    /**
     * @var string
     */
    private $context;

    /**
     * @var string
     */
    private $message;

    /**
     * @param string $context
     * @param string $message
     */
    public function __construct(string $context, string $message)
    {
        $this->context = $context;
        $this->message = $message;
    }

    /**
     * @param string $json
     *
     * @return MessageInterface
     */
    public static function fromJson(string $json): MessageInterface
    {
        $rawMessage = json_decode($json);

        return new self($rawMessage->context, $rawMessage->message);
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode([
            'context' => $this->context,
            'message' => $this->message,
        ]);
    }

    /**
     * @return string
     */
    public function getContext(): string
    {
        return $this->context;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
```

#### Sample child command

```{.php}
#!/usr/bin/env php
<?php

use My\Project\SampleMessage;
use Saxulum\MessageQueue\SystemV\SystemVSend;

if (!isset($argv[1])) {
    throw new \InvalidArgumentException('Missing key for SystemVSend');
}

if (!isset($argv[2])) {
    throw new \InvalidArgumentException('Missing child id');
}

$send = new SystemVSend($argv[1]);

for ($i = 0; $i < 100; ++$i) {
    $message = new SampleMessage($argv[2], sprintf('message %d', $i));
    $send->send($message);

    echo $message->toJson().PHP_EOL;
}
```

#### Parent command

```{.php}
<?php

use My\Project\SampleMessage;
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

$receiver = new SystemVReceive(SampleMessage::class, 1);

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
