<?php

namespace bugfree\output;


use Symfony\Component\Console\Output\OutputInterface;

class TapFormatter implements OutputFormatter
{
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }


    public function begin($expectedTests)
    {
        $this->output->writeln("TAP version 13");
        $this->output->writeln("1..{$expectedTests}");
    }

    public function testPassed($testNumber, $filename)
    {
        $this->output->writeln("ok $testNumber - $filename");
    }

    public function testFailed($testNumber, $filename, array $errors, array $warnings)
    {
        $message = join("\n", array_merge($errors, $warnings));
        $this->output->writeln("not ok $testNumber - $filename");

        $message = preg_replace('/\r|\r\n|\n/', "\n  ", $message);
        fwrite(STDERR, "  ---\n");
        fwrite(STDERR, "  $message\n");
        fwrite(STDERR, "  ...\n");
    }

    public function end($status)
    {
    }
}
