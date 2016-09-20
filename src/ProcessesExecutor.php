<?php

namespace Saxulum\ProcessesExecutor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

final class ProcessesExecutor implements ProcessesExecutorInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

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
    ) {
        $this->logger->info(self::LOG_START);

        /** @var Process[]|array $parallelProcesses */
        $parallelProcesses = [];

        do {
            foreach ($parallelProcesses as $key => $process) {
                if (false === $process->isRunning()) {
                    $this->finishCallback($process, $key, $finishCallback);
                    unset($parallelProcesses[$key]);
                }
            }

            while (count($parallelProcesses) < $parallelProcessCount) {
                if (null !== $key = key($processes)) {
                    $process = current($processes);
                    $process->start();
                    $this->startCallback($process, $key, $startCallback);
                    $parallelProcesses[$key] = $process;
                    next($processes);
                } else {
                    break;
                }
            }

            usleep($iterationSleepInMicroseconds);

            $this->callIterationCallback($parallelProcesses, $iterationCallback);
        } while ([] !== $parallelProcesses);

        $this->logger->info(self::LOG_FINISHED);
    }

    /**
     * @param Process       $process
     * @param mixed         $key
     * @param \Closure|null $startCallback
     */
    private function startCallback(Process $process, $key, \Closure $startCallback = null)
    {
        if (null === $startCallback) {
            return;
        }

        $this->logger->debug(self::LOG_START_START_CALLBACK, ['process' => $process, 'key' => $key]);
        $startCallback($process, $key);
        $this->logger->debug(self::LOG_STOP_START_CALLBACK, ['process' => $process, 'key' => $key]);
    }

    /**
     * @param Process[]|array $processes
     * @param \Closure|null   $iterationCallback
     */
    private function callIterationCallback(array $processes, \Closure $iterationCallback = null)
    {
        if (null === $iterationCallback) {
            return;
        }

        $this->logger->debug(self::LOG_START_ITERATION_CALLBACK, ['processes' => $processes]);
        $iterationCallback($processes);
        $this->logger->debug(self::LOG_STOP_ITERATION_CALLBACK, ['processes' => $processes]);
    }

    /**
     * @param Process       $process
     * @param mixed         $key
     * @param \Closure|null $finishCallback
     */
    private function finishCallback(Process $process, $key, \Closure $finishCallback = null)
    {
        if (null === $finishCallback) {
            return;
        }

        $this->logger->debug(self::LOG_START_FINISH_CALLBACK, ['process' => $process, 'key' => $key]);
        $finishCallback($process, $key);
        $this->logger->debug(self::LOG_STOP_FINISH_CALLBACK, ['process' => $process, 'key' => $key]);
    }
}
