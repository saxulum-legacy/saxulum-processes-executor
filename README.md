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

### Simple use without any callbacks

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

$startedProcesses = [];

$executor = new ProcessesExecutor();
$executor->execute($processes);
```


### With start callback (get called onces per process)

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

$startedProcesses = [];

$executor = new ProcessesExecutor();
$executor->execute(
    $processes,
    function (Process $process) use (&$startedProcesses) {
        $startedProcesses[] = $process->getCommandLine();
    }
);
```

### With iteration callback (get called onces per iteration)

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
    function (array $processes) use (&$outputs, &$errorOutputs) {
        self::assertLessThanOrEqual(8, count($processes));
        foreach ($processes as $process) {
            /** @var Process $process $commandLine */
            $commandLine = $process->getCommandLine();
            if ('' !== $output = $process->getIncrementalOutput()) {
                if (!isset($outputs[$commandLine])) {
                    $outputs[$commandLine] = '';
                }

                $outputs[$commandLine] .= $output;
            }
            if ('' !== $errorOutput = $process->getIncrementalErrorOutput()) {
                if (!isset($outputs[$commandLine])) {
                    $errorOutputs[$commandLine] = '';
                }

                $errorOutputs[$commandLine] .= $errorOutput;
            }
        }
    }
);
```

### With finish callback (get called onces per process)

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

[1]: https://packagist.org/packages/saxulum/saxulum-processes-executor

## Copyright

Dominik Zogg 2016
