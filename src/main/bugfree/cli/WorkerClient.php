<?php

namespace bugfree\cli;


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

    private $php_options = [];

    public function __construct($options, array $php_options = [])
    {
        $this->options = $options;
        $this->php_options = $php_options;
    }

    public function sendTask($filename)
    {
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

    public function getResult()
    {
        $this->stdoutBuffer .= $this->read(1024);
        if (($result = strstr($this->stdoutBuffer, "\n", true)) !== false) {
            $this->stdoutBuffer = substr($this->stdoutBuffer, strlen($result) + 1);
            $this->currentFile = null;
            return $result;
        }

        return null;
    }

    private function arrayToOptions(array $array) {
        $options = '';

        foreach ($array as $option_name => $option_value) {
            if ($option_value === true) {
                $options .= " $option_name";
            } elseif (is_array($option_value)) {
                foreach ($option_value as $value) {
                    $options .= " $option_name $value";
                }
            } else {
                $options .= " $option_name=$option_value";
            }
        }

        return trim($options);
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

        $php_options = $this->arrayToOptions($this->php_options);

        $options = $this->arrayToOptions($this->options);

        $this->stdoutBuffer = '';
        $this->process = proc_open("/usr/bin/env php $php_options $basedir/bin/bugfree worker $options", $descriptors, $this->pipes);
        $this->isRunning = true;
    }

    public function write($string)
    {
        return fwrite($this->pipes[0], $string);
    }

    public function read($length)
    {
        return fread($this->pipes[1], $length);
    }

    public function readAll()
    {
        $buffer = $this->stdoutBuffer;
        $this->stdoutBuffer = '';
        return $buffer . stream_get_contents($this->pipes[1]);
    }

    public function readError($length)
    {
        return fread($this->pipes[2], $length);
    }

    public function readAllError()
    {
        return stream_get_contents($this->pipes[2]);
    }

    public function stop()
    {
        if (!$this->isRunning) {
            return;
        }

        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->process);
        $this->isRunning = false;
        $this->currentFile = null;
    }

    public function isRunning()
    {
        return $this->isRunning;
    }

    public function isBusy()
    {
        return (boolean)$this->currentFile;
    }

    public function getCurrentFile()
    {
        return $this->currentFile;
    }
}
