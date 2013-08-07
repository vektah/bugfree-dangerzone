<?php

namespace bugfree\output;


use bugfree\Bugfree;
use bugfree\cli\Lint;
use Symfony\Component\Console\Output\OutputInterface;

class XUnitFormatter implements OutputFormatter
{
    private $output;
    private $errors = [];
    private $warnings = [];
    private $startTime;
    private $line_length = 0;
    private $expectedTests = 0;
    private $tests = 0;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }


    public function begin($expectedTests)
    {
        $this->expectedTests = $expectedTests;
        $this->startTime = microtime(true);
        $this->output->writeln("Bugfree Dangerzone " . Bugfree::VERSION);
        $this->output->writeln('');
    }

    public function testPassed($testNumber, $filename)
    {
        $this->tests++;
        $this->line_length++;
        $this->output->write(".");
        $this->checkLineLength($testNumber);
    }

    public function testFailed($testNumber, $filename, array $errors, array $warnings)
    {
        $this->tests++;
        $this->line_length++;
        if (count($errors) > 0) {
            $this->output->write("F");
        } elseif (count($warnings) > 0) {
            $this->output->write("W");
        } else {
            $this->output->write("?");
        }

        $this->checkLineLength($testNumber);

        foreach ($errors as $error) {
            $this->errors[] = $error;
        }

        foreach ($warnings as $warning) {
            $this->warnings[] = $warning;
        }
    }

    private function checkLineLength($testNumber)
    {
        if ($this->line_length >= 63) {
            $this->line_length = 0;
            $progress = $testNumber / $this->expectedTests * 100;
            $this->output->writeln(sprintf("%5d / %5d (%3d%%)", $testNumber, $this->expectedTests, $progress));
        }
    }

    public function end($status)
    {
        $elapsed = microtime(true) - $this->startTime;

        $this->output->writeln('');
        $this->output->writeln('');
        $memoryUsage = memory_get_peak_usage() / 1024 / 1024;

        $this->output->writeln(sprintf('Time: %0.2f seconds, Memory: %0.2fMb', $elapsed, $memoryUsage));

        $this->output->writeln('');

        $errorCount = count($this->errors);
        if ($errorCount > 0) {
            $this->output->writeln("There were $errorCount failures:");
            $this->output->writeln('');

            foreach ($this->errors as $errorNumber => $error) {
                $errorNumber++;
                $this->output->writeln("{$errorNumber}) {$error}");
            }

            $this->output->writeln('');
        }

        $warningCount = count($this->warnings);
        if ($warningCount > 0) {
            $this->output->writeln("There were $warningCount warnings:");
            $this->output->writeln('');

            foreach ($this->warnings as $warningNumber => $warning) {
                $warningNumber++;
                $this->output->writeln("{$warningNumber}) {$warning}");
            }
            $this->output->writeln('');
        }

        if ($status == Lint::SUCCESS) {
            $this->output->writeln("OK ({$this->tests} tests)");
        }
    }
}
