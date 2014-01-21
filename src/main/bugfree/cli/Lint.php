<?php

namespace bugfree\cli;

use bugfree\Error;
use bugfree\output\CheckStyleOutputFormatter;
use bugfree\output\JunitOutputFormatter;
use bugfree\output\OutputFormatter;
use bugfree\output\OutputMuxer;
use bugfree\output\PmdXmlOutputFormatter;
use bugfree\output\TapFormatter;
use bugfree\output\XUnitFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use vektah\common\System;
use vektah\common\json\InvalidJsonException;
use vektah\common\json\Json;

class Lint extends Command
{
    const SUCCESS = 0;
    const WARNING = 1;
    const ERROR = 2;

    protected function configure()
    {
        $this->setName('lint');
        $this->setDescription('Runs the linter over a given directory');
        $this->addArgument(
            'files',
            InputArgument::IS_ARRAY,
            'Directory or list of files to scan'
        );
        $this->addOption(
            'workers',
            'w',
            InputOption::VALUE_REQUIRED,
            "The number of concurrent workers to run",
            System::cpuCount() + 1
        );
        $this->addOption(
            'tap',
            null,
            InputOption::VALUE_NONE,
            "Output in TAP format"
        );
        $this->addOption(
            'exclude',
            null,
            InputOption::VALUE_REQUIRED,
            "Do not attempt to check file names that match this regex."
        );
        $this->addOption(
            'autoFix',
            'a',
            InputOption::VALUE_NONE,
            'Automatically fix common problems.'
        );
        $this->addOption(
            'junitXml',
            'x',
            InputOption::VALUE_REQUIRED,
            'Output junit xml to the file provided.'
        );
        $this->addOption(
            'checkstyleXml',
            'X',
            InputOption::VALUE_REQUIRED,
            'Output checkstyle xml to the file provided.'
        );
        $this->addOption(
            'pmdXml',
            null,
            InputOption::VALUE_NONE,
            'Output PMD/PHPMD xml to stdout.'
        );
        $this->addOption(
            'config',
            'c',
            InputOption::VALUE_OPTIONAL,
            "The config file to generate/update",
            'bugfree.json'
        );
        $this->addOption(
            'ini_set',
            'd',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            "Ini settings to pass through to the worker"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = new OutputMuxer();
        if ($input->getOption('tap')) {
            $formatter->add(new TapFormatter($output));
        } elseif ($input->getOption('pmdXml')) {
            $formatter->add(new PmdXmlOutputFormatter($output));
        } else {
            $formatter->add(new XUnitFormatter($output));
        }

        $options = [];

        if (is_string($configFilename = $input->getOption('config'))) {
            $options['--config'] = $configFilename;
        }

        $php_options = [];

        if ($ini = $input->getOption('ini_set')) {
            $php_options['-d'] = $ini;
        }

        if ($input->getOption('autoFix')) {
            $options['--autoFix'] = true;
        }

        if (is_string($xmlFilename = $input->getOption('junitXml'))) {
            $formatter->add(new JunitOutputFormatter($xmlFilename));
        }

        if (is_string($xmlFilename = $input->getOption('checkstyleXml'))) {
            $formatter->add(new CheckStyleOutputFormatter($xmlFilename));
        }

        $files = $input->getArgument('files');
        $exclude = $input->getOption('exclude');

        $fileList = [];
        foreach ($files as $file) {
            $fileList = array_merge($fileList, $this->scan($file, $exclude));
        }

        $workers = [];

        for ($i = 0; $i < $input->getOption('workers'); $i++) {
            $workers[] = new WorkerClient($options, $php_options);
        }

        $status = $this->lintFiles($workers, $formatter, $fileList);

        foreach ($workers as $worker) {
            $worker->stop();
        }

        return $status;
    }

    /**
     * Scans a file, expanding directories as needed
     *
     * @param string $file the name of the file to scan
     * @param string $exclude regex of files to exclude
     *
     * @return string[] a list of filenames
     */
    private function scan($file, $exclude)
    {
        if (is_dir($file)) {
            $directory = new \RecursiveDirectoryIterator($file);
            $iterator = new \RecursiveIteratorIterator($directory);
            $phpFiles = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

            $files = [];
            foreach ($phpFiles as $it) {
                $filename = (string)$it[0];
                if ($exclude && preg_match("$exclude", $filename)) {
                    continue;
                }
                $files[] = $filename;
            }
        } else {
            $files = [$file];
        }

        return $files;
    }

    /**
     * @param WorkerClient[] $workers
     * @param OutputFormatter $formatter
     * @param array $files
     * @return int
     */
    private function lintFiles(array $workers, OutputFormatter $formatter, array $files)
    {
        $count = count($files);
        $formatter->begin($count);

        $exit_status = self::SUCCESS;
        $testNumber = 0;

        while (true) {
            $all_idle = true;
            foreach ($workers as $worker) {
                if (!$worker->isBusy()) {
                    if (!empty($files)) {
                        $worker->sendTask(array_pop($files));
                        $all_idle = false;
                    }
                } else {
                    $all_idle = false;
                    if ($error = $worker->readAllError()) {
                        $formatter->testFailed($testNumber, $worker->getCurrentFile(), ["Error from worker: $error"]);
                        $worker->stop();
                        continue;
                    }

                    if ($result = $worker->getResult()) {
                        $testNumber++;
                        try {
                            $decoded = Json::decode($result);

                            if (!is_array($decoded)) {
                                $formatter->testFailed($testNumber, $worker->getCurrentFile(), ["Invalid response from worker: $result"]);
                                $exit_status = 1;
                                continue;
                            }

                            foreach ($decoded as $file => $errors) {
                                if (is_array($errors)) {
                                    $exit_status = self::ERROR;
                                    $converted_errors = [];
                                    foreach ($errors as $error) {
                                        $converted_errors[] = new Error($error);
                                    }

                                    $formatter->testFailed($testNumber, $file, $converted_errors);
                                } else {
                                    $formatter->testPassed($testNumber, $file);
                                }
                            }

                        } catch (InvalidJsonException $e) {
                            $result .= $worker->readAll();
                            $formatter->testFailed($testNumber, $worker->getCurrentFile(), ['Communication error with worker:' . $result]);
                            $worker->stop();
                            $exit_status = 1;
                        }
                    }
                }
            }

            if ($all_idle && empty($files)) {
                foreach ($workers as $worker) {
                    if ($worker->isRunning()) {
                        $stdout = $worker->readAll();
                        $stderr = $worker->readAllError();

                        if ($stdout || $stderr) {
                            $formatter->testFailed($testNumber, $worker->getCurrentFile(), ["Worker error: \n" . $stdout . $stderr]);
                            $exit_status = 1;
                        }
                    }
                }
                break;
            }
            usleep(10e3);
        }

        $formatter->end($exit_status);

        return $exit_status;
    }
}
