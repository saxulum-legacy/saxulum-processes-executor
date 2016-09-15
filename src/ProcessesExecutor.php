<?php

namespace Saxulum\ProcessesExecutor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;

class ProcessesExecutor implements ProcessesExecutorInterface
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
     * @param \Closure|null   $finishCallback
     * @param \Closure|null   $iterationCallback
     * @param int             $parallelProcessCount
     * @param int             $iterationSleepInMicroseconds
     */
    public function execute(
        array $processes,
        \Closure $startCallback = null,
        \Closure $finishCallback = null,
        \Closure $iterationCallback = null,
        int $parallelProcessCount = 8,
        int $iterationSleepInMicroseconds = 0
    ) {
        $this->logger->info(self::LOG_START);

        /** @var Process[]|array $parallelProcesses */
        $parallelProcesses = [];

        do {
            foreach ($parallelProcesses as $i => $process) {
                if (false === $process->isRunning()) {
                    $this->finishCallback($process, $finishCallback);
                    unset($parallelProcesses[$i]);
                }
            }

            while (count($parallelProcesses) < $parallelProcessCount) {
                if (null !== $process = array_shift($processes)) {
                    $this->startCallback($process, $startCallback);
                    $parallelProcesses[] = $process;
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
     * @param \Closure|null $startCallback
     */
    private function startCallback(Process $process, \Closure $startCallback = null)
    {
        $process->start($startCallback);
        $this->logger->debug(self::LOG_PROCESS_STARTED, ['process' => $process]);
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
     * @param \Closure|null $finishCallback
     */
    private function finishCallback(Process $process, \Closure $finishCallback = null)
    {
        if (null === $finishCallback) {
            return;
        }

        $this->logger->debug(self::LOG_START_FINISH_CALLBACK, ['process' => $process]);
        $finishCallback($process);
        $this->logger->debug(self::LOG_STOP_FINISH_CALLBACK, ['process' => $process]);
    }
}
