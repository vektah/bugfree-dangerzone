<?php

namespace bugfree\cli;

use Exception;
use bugfree\AutoloaderResolver;
use bugfree\Bugfree;
use bugfree\config\Config;
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

class Lint extends Command
{
    const SUCCESS = 0;
    const WARNING = 1;
    const ERROR = 2;

    protected function configure()
    {
        $this->setName('lint')
            ->setDescription('Runs the linter over a given directory')
            ->addArgument(
                'files',
                InputArgument::IS_ARRAY,
                'Directory or list of files to scan'
            )->addOption(
                'bootstrap',
                'b',
                InputOption::VALUE_REQUIRED,
                "Run this file before starting analysis, Can be used on your projects vendor/autoload.php directly"
            )->addOption(
                'basedir',
                'd',
                InputOption::VALUE_REQUIRED,
                "The start of the namespace path, used to validate partial uses.",
                'src'
            )->addOption(
                'tap',
                null,
                InputOption::VALUE_NONE,
                "Output in TAP format"
            )->addOption(
                'exclude',
                null,
                InputOption::VALUE_REQUIRED,
                "Do not attempt to check file names that match this regex."
            )->addOption(
                'autoFix',
                'a',
                InputOption::VALUE_NONE,
                'Automatically fix common problems.'
            )->addOption(
                'junitXml',
                'x',
                InputOption::VALUE_REQUIRED,
                'Output junit xml to the file provided.'
            )->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                "The config file to generate/update",
                'bugfree.json'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('tap')) {
            $formatter = new TapFormatter($output);
        } else {
            $formatter = new XUnitFormatter($output);
        }

        if (is_string($bootstrap = $input->getOption('bootstrap'))) {
            if (!file_exists(stream_resolve_include_path($bootstrap))) {
                $output->writeln("Bootstrap '$bootstrap' does not exist!");
            } else {
                require_once($bootstrap);
            }
        }

        $config = new Config();

        if (is_string($configFilename = $input->getOption('config'))) {
            if (file_exists(stream_resolve_include_path($configFilename))) {
                $output->writeln("Config loaded from '$configFilename'\n");
                $config = Config::load($configFilename);
            } else {
                if ($input->getOption('config') != 'bugfree.json') {
                    throw new Exception("Unable to find config file '$configFilename'");
                }
            }
        }

        if ($input->getOption('autoFix')) {
            $config->autoFix = true;
        }

        if (is_string($xmlFilename = $input->getOption('junitXml'))) {
            $stdout = $formatter;

            $formatter = new OutputMuxer();
            $formatter->add($stdout)
                ->add(new JunitOutputFormatter($xmlFilename));
        }

        $files = $input->getArgument('files');
        $exclude = $input->getOption('exclude');

        $fileList = [];
        foreach ($files as $file) {
            $fileList = array_merge($fileList, $this->scan($file, $exclude));
        }

        $basedir = realpath($input->getOption('basedir'));

        return $this->lintFiles($basedir, $config, $formatter, $fileList);
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

    private function lintFiles($basedir, Config $config, OutputFormatter $formatter, array $files)
    {
        $bugfree = new Bugfree(new AutoloaderResolver($basedir), $config);

        $count = count($files);
        $formatter->begin($count);

        $status = self::SUCCESS;
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
                $status = self::ERROR;
            }

            if (count($result->getWarnings()) > 0) {
                if ($status < self::WARNING) {
                    $status = self::WARNING;
                }
            }

            if (count($result->getErrors()) > 0 || count($result->getWarnings()) > 0) {
                $formatter->testFailed($testNumber, $file, $result->getErrors(), $result->getWarnings());
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

        $formatter->end($status);

        return $status;
    }
}
