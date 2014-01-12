<?php

namespace bugfree\cli;


use Symfony\Component\Process\Process;

/**
 * Most of this class should be put into its own repo as vektah/process. Symfony Process just isn't flexible enough with
 * access to pipes.
 */
class WorkerClient
{
    private $isRunning;

    private $currentFile;

    private $process;

    /** @var string */
    private $stdoutBuffer;

    /** @var resource[] */
    private $pipes;

    private $options = [];

    public function __construct($options)
    {
        $this->options = $options;
    }

    public function sendTask($filename) {
        if ($this->currentFile) {
            throw new \LogicException("This worker is busy with $filename");
        }

        if (!$this->isRunning) {
            $this->start();
            $this->setBlocking(0);
        }

        $this->write("$filename\n");
        $this->currentFile = $filename;
    }

    public function setBlocking($blocking)
    {
        stream_set_blocking($this->pipes[1], $blocking);
        stream_set_blocking($this->pipes[2], $blocking);
    }

    public function getResult() {
        $this->stdoutBuffer .= $this->read(1024);
        if ($result = strstr($this->stdoutBuffer, "\n", true) !== false) {
            $this->stdoutBuffer = substr($this->stdoutBuffer, strlen($result) + 1);
            return $result;
        }

        return null;
    }

    public function start() {
        if ($this->isRunning) {
            throw new \LogicException('This worker is already running');
        }

        $basedir = realpath(__DIR__ . '/../../../..');

        $descriptors = [
            ['pipe', 'r'],  // stdin
            ['pipe', 'w'],  // stdout
            ['pipe', 'w'],  // stderr
        ];

        $options = '';

        foreach ($this->options as $option_name => $option_value) {
            $options .= " --$option_name=$option_value";
        }

        $this->process = proc_open("$basedir/bin/bugfree worker $options", $descriptors, $this->pipes, $basedir);
        $this->isRunning = true;
    }

    public function write($string)
    {
        fwrite($this->pipes[0], $string);
    }

    public function read($length)
    {
        return fread($this->pipes[1], $length);
    }

    public function readError($length)
    {
        return fread($this->pipes[2], $length);
    }

    public function stop() {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->process);
        $this->isRunning = false;
    }

    public function isRunning()
    {
        return $this->isRunning;
    }

    public function isBusy()
    {
        return (boolean)$this->currentFile;
    }

    public function getCurrentFile() {
        return $this->currentFile
    }
}
