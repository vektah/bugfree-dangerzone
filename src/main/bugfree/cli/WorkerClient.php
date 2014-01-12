<?php

namespace bugfree\cli;


use Symfony\Component\Process\Process;

class WorkerClient
{
    /** @var Process */
    private $process;

    /** @var boolean */
    private $is_busy;

    public function __construct() {
        $this->process = new Process(__DIR__ . '/../../../../bin/bugfree worker');
    }

    public function start($filename) {
        if (!$this->is_busy) {
            throw new \LogicException('This worker is busy.');
        }

        if (!$this->process->isRunning()) {
            $this->process->start();
        }
    }
}
