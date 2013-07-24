<?php

namespace bugfree\output;


class OutputMuxer implements OutputFormatter
{
    private $formatters = [];

    public function add(OutputFormatter $formatter)
    {
        $this->formatters[] = $formatter;

        return $this;
    }

    public function each($method, array $args = [])
    {
        foreach ($this->formatters as $formatter) {
            call_user_func_array([$formatter, $method], $args);
        }
    }

    public function begin($expectedTests)
    {
        $this->each('begin', [$expectedTests]);
    }

    public function testPassed($testNumber, $filename)
    {
        $this->each('testPassed', [$testNumber, $filename]);
    }

    public function testFailed($testNumber, $filename, array $errors, array $warnings)
    {
        $this->each('testFailed', [$testNumber, $filename, $errors, $warnings]);
    }

    public function end($status)
    {
        $this->each('end', [$status]);
    }
}
