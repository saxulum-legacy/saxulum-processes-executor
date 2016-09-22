<?php

namespace Saxulum\Tests\ProcessesExecutor\Resources;

use Symfony\Component\Process\Process;

final class CallbackObject
{
    /**
     * @var string[]|array
     */
    private $startedCommandLines = [];

    /**
     * @var string[]|array
     */
    private $outputs = [];

    /**
     * @var string[]|array
     */
    private $errorOutputs = [];

    /**
     * @param Process $process
     * @param mixed   $key
     */
    public function start(Process $process, $key)
    {
        $this->startedCommandLines[$key] = $process->getCommandLine();
    }

    /**
     * @param Process[]|array $processes
     */
    public function iteration(array $processes)
    {
        foreach ($processes as $key => $process) {
            /* @var Process $process */
            if ('' !== $output = $process->getIncrementalOutput()) {
                if (!isset($this->outputs[$key])) {
                    $this->outputs[$key] = '';
                }

                $this->outputs[$key] .= $output;
            }
            if ('' !== $errorOutput = $process->getIncrementalErrorOutput()) {
                if (!isset($this->errorOutputs[$key])) {
                    $errorOutputs[$key] = '';
                }

                $this->errorOutputs[$key] .= $errorOutput;
            }
        }
    }

    /**
     * @param Process $process
     * @param mixed   $key
     */
    public function finish(Process $process, $key)
    {
        if ('' !== $output = $process->getOutput()) {
            $this->outputs[$key] = $output;
        }
        if ('' !== $errorOutput = $process->getErrorOutput()) {
            $this->errorOutputs[$key] = $errorOutput;
        }
    }

    /**
     * @return array|\string[]
     */
    public function getStartedCommandLines(): array
    {
        return $this->startedCommandLines;
    }

    /**
     * @return array|\string[]
     */
    public function getOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * @return array|\string[]
     */
    public function getErrorOutputs(): array
    {
        return $this->errorOutputs;
    }
}
