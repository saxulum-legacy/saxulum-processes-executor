<?php

namespace Saxulum\Tests\ProcessesExecutor;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Saxulum\MessageQueue\SystemV\SystemVReceive;
use Saxulum\ProcessesExecutor\ProcessesExecutor;
use Saxulum\Tests\ProcessesExecutor\Resources\SampleMessage;
use Symfony\Component\Process\Process;

/**
 * @group integration
 * @covers Saxulum\ProcessesExecutor\ProcessesExecutor
 */
class ProcessesExecutorIntegrationTest extends TestCase
{
    public function testExecuteWithoutLogger()
    {
        $executor = new ProcessesExecutor();
        $executor->execute([]);
    }

    public function testExecuteWithoutProcesses()
    {
        $logger = new TestLogger();

        $output = '';
        $errorOutput = '';

        $executor = new ProcessesExecutor($logger);
        $executor->execute([], $this->getStartCallback($output, $errorOutput));

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

        self::assertEmpty($output);
        self::assertEmpty($errorOutput);
    }

    public function testExecuteWithTwentyProcesses()
    {
        $logger = new TestLogger();

        $output = '';
        $errorOutput = '';

        $subProcessPath = __DIR__.'/Resources/SubProcess.php';

        $processes = [];
        for ($i = 0; $i < 20; ++$i) {
            $processes[] = new Process($subProcessPath.' '.$i);
        }

        $executor = new ProcessesExecutor($logger);
        $executor->execute($processes, $this->getStartCallback($output, $errorOutput));

        $logs = $logger->getLogs();

        self::assertCount(22, $logs);

        self::assertEquals(
            ['level' => LogLevel::INFO, 'message' => $executor::LOG_START, 'context' => []],
            array_shift($logs)
        );

        for ($i = 0; $i < 20; ++$i) {
            self::assertEquals(
                [
                    'level' => LogLevel::DEBUG,
                    'message' => $executor::LOG_PROCESS_STARTED,
                    'context' => ['process' => $processes[$i]],
                ],
                array_shift($logs)
            );
        }

        self::assertEquals(
            ['level' => LogLevel::INFO, 'message' => $executor::LOG_FINISHED, 'context' => []],
            array_shift($logs)
        );

        self::assertSame(78800, strlen($output), 'Output length is invalid!');
        self::assertEmpty($errorOutput);
    }

    public function testExecuteWithTwentyProcessesAndFinishCallback()
    {
        $logger = new TestLogger();

        $outputs = [];
        $errorOutputs = [];

        $subProcessPath = __DIR__.'/Resources/SubProcess.php';

        $processes = [];
        for ($i = 0; $i < 20; ++$i) {
            $processes[] = new Process($subProcessPath.' '.$i);
        }

        $executor = new ProcessesExecutor($logger);
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

        $logs = $logger->getLogs();

        self::assertCount(62, $logs);

        $logsGroupByMessage = $this->getLogsGroupByMessage($logs);

        self::assertArrayHasKey(ProcessesExecutor::LOG_START, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_START]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START] as $i => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_PROCESS_STARTED, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_PROCESS_STARTED]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_PROCESS_STARTED] as $i => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$i], $log['context']['process']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_START_FINISH_CALLBACK, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_START_FINISH_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START_FINISH_CALLBACK] as $i => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$i], $log['context']['process']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_STOP_FINISH_CALLBACK, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_STOP_FINISH_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_STOP_FINISH_CALLBACK] as $i => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$i], $log['context']['process']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_FINISHED, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_FINISHED]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_FINISHED] as $i => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertCount(20, $outputs);
        self::assertSame(78800, strlen(implode($outputs)), 'Output length is invalid!');

        self::assertCount(0, $errorOutputs);
    }

    public function testExecuteWithTwentyProcessesAndFinishAndIterationCallback()
    {
        $logger = new TestLogger();

        $outputs = [];
        $errorOutputs = [];

        $subProcessPath = __DIR__.'/Resources/SubProcessWithMessageQueue.php 1';

        $processes = [];
        for ($i = 0; $i < 20; ++$i) {
            $processes[] = new Process($subProcessPath.' '.$i);
        }

        $messages = [];

        $receiver = new SystemVReceive(SampleMessage::class, 1);

        // make sure the queue is empty
        while (null !== $message = $receiver->receive()) {
        }

        $executor = new ProcessesExecutor($logger);
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
            },
            function (array $processes) use ($receiver, &$messages) {
                self::assertLessThanOrEqual(10, count($processes));

                while (null !== $message = $receiver->receive()) {
                    $messages[] = $message;
                }
            },
            10,
            100
        );

        self::assertCount(2000, $messages);

        $logs = $logger->getLogs();

        self::assertGreaterThanOrEqual(65, count($logs));

        $logsGroupByMessage = $this->getLogsGroupByMessage($logs);

        self::assertArrayHasKey(ProcessesExecutor::LOG_START, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_START]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START] as $i => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_PROCESS_STARTED, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_PROCESS_STARTED]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_PROCESS_STARTED] as $i => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$i], $log['context']['process']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_START_FINISH_CALLBACK, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_START_FINISH_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START_FINISH_CALLBACK] as $i => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$i], $log['context']['process']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_STOP_FINISH_CALLBACK, $logsGroupByMessage);
        self::assertCount(20, $logsGroupByMessage[ProcessesExecutor::LOG_STOP_FINISH_CALLBACK]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_STOP_FINISH_CALLBACK] as $i => $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
            self::assertSame($processes[$i], $log['context']['process']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_START_ITERATION_CALLBACK, $logsGroupByMessage);

        // there are at least 2 runs (20/10)
        self::assertGreaterThanOrEqual(2, count($logsGroupByMessage[ProcessesExecutor::LOG_START_ITERATION_CALLBACK]));

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_START_ITERATION_CALLBACK] as $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_STOP_ITERATION_CALLBACK, $logsGroupByMessage);

        // there are at least 2 runs (20/10)
        self::assertGreaterThanOrEqual(2, count($logsGroupByMessage[ProcessesExecutor::LOG_STOP_ITERATION_CALLBACK]));

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_STOP_ITERATION_CALLBACK] as $log) {
            self::assertSame(LogLevel::DEBUG, $log['level']);
        }

        self::assertArrayHasKey(ProcessesExecutor::LOG_FINISHED, $logsGroupByMessage);
        self::assertCount(1, $logsGroupByMessage[ProcessesExecutor::LOG_FINISHED]);

        foreach ($logsGroupByMessage[ProcessesExecutor::LOG_FINISHED] as $i => $log) {
            self::assertSame(LogLevel::INFO, $log['level']);
        }

        self::assertCount(20, $outputs);
        self::assertSame(78800, strlen(implode($outputs)), 'Output length is invalid!');

        self::assertCount(0, $errorOutputs);
    }

    /**
     * @param string $output
     * @param string $errorOutput
     *
     * @return \Closure
     */
    private function getStartCallback(string &$output, string &$errorOutput): \Closure
    {
        return function ($type, $buffer) use (&$output, &$errorOutput) {
            if (Process::OUT === $type) {
                $output .= $buffer;
            } elseif (Process::ERR === $type) {
                $errorOutput .= $buffer;
            }
        };
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
