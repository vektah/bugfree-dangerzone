<?php

namespace bugfree\output;

use bugfree\ErrorType;
use Symfony\Component\Console\Output\OutputInterface;

class PmdXmlOutputFormatter implements OutputFormatter
{
    /** @var OutputInterface */
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function begin($expectedTests)
    {
        $this->output->writeln('<?xml version="1.0" encoding="UTF-8" ?>');
        $this->output->writeln('<pmd version="1.5.0" timestamp="' . date('c') . '">');
    }

    public function testPassed($testNumber, $filename)
    {
    }

    public function testFailed($testNumber, $filename, array $errors)
    {
        $this->output->writeln("\t<file name=\"$filename\">");
        foreach ($errors as $error) {
            $type = 'error';
            if ($error->severity == ErrorType::WARNING) {
                $type = 'warning';
            }

            $line = $error->line ? $error->line : 1;

            $this->output->writeln("\t\t<violation beginline=\"{$line}\" endline=\"{$line}\" rule=\"bugfree_$type\" ruleset=\"bugfree\" priority=\"1\">");
            $this->output->writeln("\t\t\tBugfree: {$error->message}");
            $this->output->writeln("\t\t</violation>");
        }
        $this->output->writeln("\t</file>");
    }

    public function end($status)
    {
        $this->output->writeln('</pmd>');
    }
}
