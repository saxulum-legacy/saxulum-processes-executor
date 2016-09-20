<?php

namespace Saxulum\Tests\ProcessesExecutor;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Saxulum\ProcessesExecutor\ProcessesExecutor;
use Symfony\Component\Process\Process;

/**
 * @group unit
 * @covers Saxulum\ProcessesExecutor\ProcessesExecutor
 */
class ProcessesExecutorTest extends TestCase
{
    public function testExecuteWithoutLogger()
    {
        $executor = new ProcessesExecutor();
        $executor->execute([]);
    }

    public function testExecuteWithoutProcesses()
    {
        $logger = new TestLogger();

        $executor = new ProcessesExecutor($logger);
        $executor->execute([]);

        $logs = $logger->getLogs();

        self::assertCount(2, $logs);

        self::assertEquals(
            ['level' => LogLevel::INFO, 'message' => $executor::LOG_START, 'context' => []],
            array_shift($logs)
        );

        self::assertEquals(
            ['level' => LogLevel::INFO, 'message' => $executor::LOG_FINISHED, 'context' => []],
            array_shift($logs)
        );
    }

    public function testExecuteWithTwentyProcessesAndStartCallback()
    {
        $logger = new TestLogger();

        $childProcessPath = 'path/to/child/process';

        $processes = [];
        for ($key = 0; $key < 20; ++$key) {
            $processes[] = $this->getProcess(
                $childProcessPath.' '.$key,
                [
                    ['return' => true],
                    ['return' => true],
                    ['return' => true],
                    ['return' => true],
                    ['return' => false],
                ]
            );
        }

        $startedProcesses = [];

        $executor = new ProcessesExecutor($logger);
        $executor->execute(
            $processes,
            function (Process $process, int $key) use (&$startedProcesses, $processes) {
                $startedProcesses[$key] = $process->getCommandLine();
                self::assertSame($process, $processes[$key]);
            }
        );

        $logs = $logger->getLogs();

        self::assertCount(42, $logs);

        $logsGroupByMessage = $this->getLogsGroupByMessage($logs);

        self::assertArrayHasKey(ProcessesExecutor::LOG_START, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_START]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START] as $key => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_START_START_CALLBACK, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_START_START_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START_START_CALLBACK] as $key => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$key], $log['context']['process']);
            self::assertSame($key, $log['context']['key']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_STOP_START_CALLBACK, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_STOP_START_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_STOP_START_CALLBACK] as $key => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$key], $log['context']['process']);
            self::assertSame($key, $log['context']['key']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_FINISHED, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_FINISHED]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_FINISHED] as $key => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        for ($key = 0; $key < 20; ++$key) {
            self::assertSame($childProcessPath.' '.$key, $startedProcesses[$key]);
        }
    }

    public function testExecuteWithTwentyProcessesAndIterationCallback()
    {
        $logger = new TestLogger();

        $childProcessPath = 'path/to/child/process';

        $processes = [];
        for ($key = 0; $key < 20; ++$key) {
            $processes[] = $this->getProcessIncrementalOutput(
                $childProcessPath.' '.$key,
                [
                    ['return' => true],
                    ['return' => true],
                    ['return' => true],
                    ['return' => true],
                    ['return' => false],
                ],
                [
                    ['return' => 'Yet'],
                    ['return' => ' another'],
                    ['return' => ' output'],
                    ['return' => ' line'],
                    ['return' => PHP_EOL],
                ],
                [
                    ['return' => ''],
                    ['return' => ''],
                    ['return' => ''],
                    ['return' => ''],
                    ['return' => ''],
                ]
            );
        }

        $outputs = [];
        $errorOutputs = [];

        $executor = new ProcessesExecutor($logger);
        $executor->execute(
            $processes,
            null,
            function (array $processes) use (&$outputs, &$errorOutputs) {
                self::assertLessThanOrEqual(8, count($processes));
                foreach ($processes as $key => $process) {
                    /* @var Process $process */
                    if ('' !== $output = $process->getIncrementalOutput()) {
                        if (!isset($outputs[$key])) {
                            $outputs[$key] = '';
                        }

                        $outputs[$key] .= $output;
                    }
                    if ('' !== $errorOutput = $process->getIncrementalErrorOutput()) {
                        if (!isset($outputs[$key])) {
                            $errorOutputs[$key] = '';
                        }

                        $errorOutputs[$key] .= $errorOutput;
                    }
                }
            }
        );

        $logs = $logger->getLogs();

        self::assertCount(34, $logs);

        $logsGroupByMessage = $this->getLogsGroupByMessage($logs);

        self::assertArrayHasKey(ProcessesExecutor::LOG_START, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_START]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START] as $key => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_START_ITERATION_CALLBACK, $logsGroupByMessage);
        self::assertCount(16, $logsGroupByMessage[ProcessesExecutor::LOG_START_ITERATION_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START_ITERATION_CALLBACK] as $key => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_STOP_ITERATION_CALLBACK, $logsGroupByMessage);
        self::assertCount(16, $logsGroupByMessage[ProcessesExecutor::LOG_STOP_ITERATION_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_STOP_ITERATION_CALLBACK] as $key => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_FINISHED, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_FINISHED]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_FINISHED] as $key => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertCount(20, $outputs);
        self::assertSame(480, strlen(implode($outputs)), 'Output length is invalid!');

        self::assertCount(0, $errorOutputs);
    }

    public function testExecuteWithTwentyProcessesAndFinishCallback()
    {
        $logger = new TestLogger();

        $childProcessPath = 'path/to/child/process';

        $processes = [];
        for ($key = 0; $key < 20; ++$key) {
            $processes[] = $this->getProcessWithOutput(
                $childProcessPath.' '.$key,
                [
                    ['return' => true],
                    ['return' => true],
                    ['return' => true],
                    ['return' => true],
                    ['return' => false],
                ],
                [
                    ['return' => 'Yet another output line'.PHP_EOL],
                ],
                [
                    ['return' => ''],
                ]
            );
        }

        $outputs = [];
        $errorOutputs = [];

        $executor = new ProcessesExecutor($logger);
        $executor->execute(
            $processes,
            null,
            null,
            function (Process $process, int $key) use (&$outputs, &$errorOutputs, $processes) {
                if ('' !== $output = $process->getOutput()) {
                    $outputs[$key] = $output;
                }
                if ('' !== $errorOutput = $process->getErrorOutput()) {
                    $errorOutputs[$key] = $errorOutput;
                }

                self::assertSame($process, $processes[$key]);
            }
        );

        $logs = $logger->getLogs();

        self::assertCount(42, $logs);

        $logsGroupByMessage = $this->getLogsGroupByMessage($logs);

        self::assertArrayHasKey(ProcessesExecutor::LOG_START, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_START]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START] as $key => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_START_FINISH_CALLBACK, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_START_FINISH_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START_FINISH_CALLBACK] as $key => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$key], $log['context']['process']);
            self::assertSame($key, $log['context']['key']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_STOP_FINISH_CALLBACK, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_STOP_FINISH_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_STOP_FINISH_CALLBACK] as $key => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$key], $log['context']['process']);
            self::assertSame($key, $log['context']['key']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_FINISHED, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_FINISHED]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_FINISHED] as $key => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertCount(20, $outputs);
        self::assertSame(480, strlen(implode($outputs)), 'Output length is invalid!');

        self::assertCount(0, $errorOutputs);
    }

    /**
     * @param string $commandline
     * @param array  $isRunningStack
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Process
     */
    private function getProcess(
        string $commandline,
        array $isRunningStack
    ) {
        /** @var Process|\PHPUnit_Framework_MockObject_MockObject $process */
        $process = $this
            ->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getCommandLine',
                'start',
                'isRunning',
            ])
            ->getMock()
        ;

        $process
            ->expects(self::any())
            ->method('getCommandLine')
            ->willReturn($commandline);

        $process
            ->expects(self::any())
            ->method('start')
        ;

        $isRunningCounter = 0;
        $process
            ->expects(self::any())
            ->method('isRunning')
            ->willReturnCallback(
                function () use (&$isRunningStack, &$isRunningCounter) {
                    ++$isRunningCounter;

                    $isRunning = array_shift($isRunningStack);

                    self::assertNotNull(
                        $isRunning,
                        sprintf('There is no information left within isRunningStack at call %d', $isRunningCounter)
                    );

                    return $isRunning['return'];
                }
            )
        ;

        return $process;
    }

    /**
     * @param string $commandline
     * @param array  $isRunningStack
     * @param array  $getIncrementalOutputStack
     * @param array  $getIncrementalErrorOutputStack
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Process
     */
    private function getProcessIncrementalOutput(
        string $commandline,
        array $isRunningStack,
        array $getIncrementalOutputStack,
        array $getIncrementalErrorOutputStack
    ) {
        /** @var Process|\PHPUnit_Framework_MockObject_MockObject $process */
        $process = $this
            ->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getCommandLine',
                'start',
                'isRunning',
                'getIncrementalOutput',
                'getIncrementalErrorOutput',
            ])
            ->getMock()
        ;

        $process
            ->expects(self::any())
            ->method('getCommandLine')
            ->willReturn($commandline);

        $process
            ->expects(self::any())
            ->method('start')
        ;

        $isRunningCounter = 0;
        $process
            ->expects(self::any())
            ->method('isRunning')
            ->willReturnCallback(
                function () use (&$isRunningStack, &$isRunningCounter) {
                    ++$isRunningCounter;

                    $isRunning = array_shift($isRunningStack);

                    self::assertNotNull(
                        $isRunning,
                        sprintf('There is no information left within isRunningStack at call %d', $isRunningCounter)
                    );

                    return $isRunning['return'];
                }
            )
        ;

        $getIncrementalOutputCounter = 0;
        $process
            ->expects(self::any())
            ->method('getIncrementalOutput')
            ->willReturnCallback(
                function () use (&$getIncrementalOutputStack, &$getIncrementalOutputCounter) {
                    ++$getIncrementalOutputCounter;

                    $getIncrementalOutput = array_shift($getIncrementalOutputStack);

                    self::assertNotNull(
                        $getIncrementalOutput,
                        sprintf(
                            'There is no information left within getIncrementalOutputStack at call %d',
                            $getIncrementalOutputCounter
                        )
                    );

                    return $getIncrementalOutput['return'];
                }
            )
        ;

        $getIncrementalErrorOutputCounter = 0;
        $process
            ->expects(self::any())
            ->method('getIncrementalErrorOutput')
            ->willReturnCallback(
                function () use (&$getIncrementalErrorOutputStack, &$getIncrementalErrorOutputCounter) {
                    ++$getIncrementalErrorOutputCounter;

                    $getIncrementalErrorOutput = array_shift($getIncrementalErrorOutputStack);

                    self::assertNotNull(
                        $getIncrementalErrorOutput,
                        sprintf(
                            'There is no information left within getIncrementalErrorOutputStack at call %d',
                            $getIncrementalErrorOutputCounter
                        )
                    );

                    return $getIncrementalErrorOutput['return'];
                }
            )
        ;

        return $process;
    }

    /**
     * @param string $commandline
     * @param array  $isRunningStack
     * @param array  $getOutputStack
     * @param array  $getErrorOutputStack
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Process
     */
    private function getProcessWithOutput(
        string $commandline,
        array $isRunningStack,
        array $getOutputStack,
        array $getErrorOutputStack
    ) {
        /** @var Process|\PHPUnit_Framework_MockObject_MockObject $process */
        $process = $this
            ->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getCommandLine',
                'start',
                'isRunning',
                'getOutput',
                'getErrorOutput',
            ])
            ->getMock()
        ;

        $process
            ->expects(self::any())
            ->method('getCommandLine')
            ->willReturn($commandline);

        $process
            ->expects(self::any())
            ->method('start')
        ;

        $isRunningCounter = 0;
        $process
            ->expects(self::any())
            ->method('isRunning')
            ->willReturnCallback(
                function () use (&$isRunningStack, &$isRunningCounter) {
                    ++$isRunningCounter;

                    $isRunning = array_shift($isRunningStack);

                    self::assertNotNull(
                        $isRunning,
                        sprintf('There is no information left within isRunningStack at call %d', $isRunningCounter)
                    );

                    return $isRunning['return'];
                }
            )
        ;

        $getOutputCounter = 0;
        $process
            ->expects(self::any())
            ->method('getOutput')
            ->willReturnCallback(
                function () use (&$getOutputStack, &$getOutputCounter) {
                    ++$getOutputCounter;

                    $getOutput = array_shift($getOutputStack);

                    self::assertNotNull(
                        $getOutput,
                        sprintf('There is no information left within getOutputStack at call %d', $getOutputCounter)
                    );

                    return $getOutput['return'];
                }
            )
        ;

        $getErrorOutputCounter = 0;
        $process
            ->expects(self::any())
            ->method('getErrorOutput')
            ->willReturnCallback(
                function () use (&$getErrorOutputStack, &$getErrorOutputCounter) {
                    ++$getErrorOutputCounter;

                    $getErrorOutput = array_shift($getErrorOutputStack);

                    self::assertNotNull(
                        $getErrorOutput,
                        sprintf(
                            'There is no information left within getErrorOutputStack at call %d',
                            $getErrorOutputCounter
                        )
                    );

                    return $getErrorOutput['return'];
                }
            )
        ;

        return $process;
    }

    /**
     * @param array $logs
     *
     * @return array
     */
    private function getLogsGroupByMessage(array $logs): array
    {
        $logsGroupByMessage = [];
        foreach ($logs as $log) {
            if (!isset($logsGroupByMessage[$log['message']])) {
                $logsGroupByMessage[$log['message']] = [];
            }
            $logsGroupByMessage[$log['message']][] = $log;
        }

        return $logsGroupByMessage;
    }
}

class TestLogger extends AbstractLogger
{
    /**
     * @var array[]
     */
    private $logs = [];

    public function log($level, $message, array $context = array())
    {
        $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    /**
     * @return array[]
     */
    public function getLogs(): array
    {
        return $this->logs;
    }
}
