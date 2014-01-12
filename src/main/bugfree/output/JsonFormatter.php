<?php

namespace bugfree\output;


use Symfony\Component\Console\Output\OutputInterface;
use vektah\common\json\Json;

class JsonFormatter implements OutputFormatter
{
    const END_OF_TEXT = "\003";
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function begin($expectedTests)
    {
    }

    public function testPassed($testNumber, $filename)
    {
        $this->output->writeln(Json::encode([$filename => true]));
    }

    public function testFailed($testNumber, $filename, array $errors)
    {
        $this->output->writeln(Json::encode([$filename => $errors]));
    }

    public function end($status)
    {
    }
}
