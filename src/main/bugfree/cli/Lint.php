<?php

namespace bugfree\cli;


use bugfree\Error;
use Exception;
use bugfree\AutoloaderResolver;
use bugfree\Bugfree;
use bugfree\config\Config;
use bugfree\output\CheckStyleOutputFormatter;
use bugfree\output\JunitOutputFormatter;
use bugfree\output\OutputFormatter;
use bugfree\output\OutputMuxer;
use bugfree\output\TapFormatter;
use bugfree\output\XUnitFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use vektah\common\json\InvalidJsonException;
use vektah\common\json\Json;
use vektah\common\System;

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
            'bootstrap',
            'b',
            InputOption::VALUE_REQUIRED,
            "Run this file before starting analysis, Can be used on your projects vendor/autoload.php directly"
        );
        $this->addOption(
            'workers',
            'w',
            InputOption::VALUE_REQUIRED,
            "The number of concurrent workers to run",
            System::cpuCount() + 1
        );
        $this->addOption(
            'basedir',
            'd',
            InputOption::VALUE_REQUIRED,
            "The start of the namespace path, used to validate partial uses.",
            'src'
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
            'config',
            'c',
            InputOption::VALUE_OPTIONAL,
            "The config file to generate/update",
            'bugfree.json'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = new OutputMuxer();
        if ($input->getOption('tap')) {
            $formatter->add(new TapFormatter($output));
        } else {
            $formatter->add(new XUnitFormatter($output));
        }

        if (is_string($bootstrap = $input->getOption('bootstrap'))) {
            if (!file_exists(stream_resolve_include_path($bootstrap))) {
                $output->writeln("Bootstrap '$bootstrap' does not exist!");
            } else {
                require_once($bootstrap);
            }
        }

        $options = [];

        if (is_string($configFilename = $input->getOption('config'))) {
            if (file_exists(stream_resolve_include_path($configFilename))) {
                $options['config'] = $configFilename;
            } else {
                if ($input->getOption('config') != 'bugfree.json') {
                    throw new Exception("Unable to find config file '$configFilename'");
                }
            }
        }

        if ($input->getOption('autoFix')) {
            $options['autofix'] = true;
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

        $options['basedir'] = realpath($input->getOption('basedir'));

        $workers = [];

        for ($i = 0; $i < $input->getOption('workers'); $i++) {
            $workers[] = new WorkerClient($options);
        }

        return $this->lintFiles($formatter, $fileList);
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

        $errors = self::SUCCESS;
        $testNumber = 0;

        while (true) {
            foreach ($workers as $worker) {
                if (!$worker->isBusy()) {
                    if (!empty($files)) {
                        $worker->sendTask(array_pop($files));
                    }

                    if ($result = $worker->getResult()) {
                        $testNumber++;
                        try {
                            $decoded = Json::decode($result);

                            foreach ($decoded as $file => $errors) {
                                if (is_array($errors)) {
                                    $errors = self::ERROR;
                                    $converted_errors = [];

                                    foreach ($errors as $error) {
                                        $converted_errors[] = new Error();
                                    }

                                    $formatter->testFailed($testNumber, $file, $errors);
                                } else {
                                    $formatter->testPassed($testNumber, $file);
                                }
                            }

                        } catch (InvalidJsonException $e) {
                            $formatter->testFailed($testNumber, $worker->getCurrentFile(), ['Communication error with worker:' . $result]);
                            $worker->stop();
                            $worker->start();
                        }
                    }
                }
            }
        }

        foreach ($files as $index => $file) {
            $testNumber = $index+1;

            try {
                $rawFileContents = file_get_contents($file);
                $result = $bugfree->parse($file, $rawFileContents);
            } catch (\Exception $e) {
                $formatter->testFailed($testNumber, $file, [$e->getMessage()], []);
                continue;
            }

            if (count($result->getErrors()) > 0) {
                $errors = self::ERROR;
            }

            if (count($result->getErrors()) > 0) {
                $formatter->testFailed($testNumber, $file, $result->getErrors());
            } else {
                $formatter->testPassed($testNumber, $file);
            }

            if (count($result->getFixes()) > 0) {
                $fixes = $result->getFixes();

                $fileLines = preg_split('/\r|\r\n|\n/', $rawFileContents);
                foreach ($fixes as $fix) {
                    $fix->run($fileLines);
                }

                file_put_contents($file, implode("\n", $fileLines));
            }
        }

        $formatter->end($errors);

        return $errors;
    }
}
