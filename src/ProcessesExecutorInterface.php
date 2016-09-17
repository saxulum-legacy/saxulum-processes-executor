<?php

namespace Saxulum\ProcessesExecutor;

use Symfony\Component\Process\Process;

interface ProcessesExecutorInterface
{
    const LOG_START = 'start the execution of child processes';
    const LOG_START_START_CALLBACK = 'start start callback on child process: {process}';
    const LOG_STOP_START_CALLBACK = 'stop start callback on child process: {process}';
    const LOG_START_ITERATION_CALLBACK = 'start iteration callback on child processes: {processes}';
    const LOG_STOP_ITERATION_CALLBACK = 'stop iteration callback on child processes: {processes}';
    const LOG_START_FINISH_CALLBACK = 'start finish callback on child process: {process}';
    const LOG_STOP_FINISH_CALLBACK = 'stop finish callback on child process: {process}';
    const LOG_FINISHED = 'finished the execution of child processes';

    /**
     * @param Process[]|array $processes
     * @param \Closure|null   $startCallback
     * @param \Closure|null   $iterationCallback
     * @param \Closure|null   $finishCallback
     * @param int             $parallelProcessCount
     * @param int             $iterationSleepInMicroseconds
     */
    public function execute(
        array $processes,
        \Closure $startCallback = null,
        \Closure $iterationCallback = null,
        \Closure $finishCallback = null,
        int $parallelProcessCount = 8,
        int $iterationSleepInMicroseconds = 0
    );
}
