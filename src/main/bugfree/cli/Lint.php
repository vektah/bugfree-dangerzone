<?php

namespace bugfree\cli;


use bugfree\AutoloaderResolver;
use bugfree\Bugfree;
use bugfree\config\Config;
use bugfree\fix\Fix;
use bugfree\output\OutputFormatter;
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
                'dir',
                InputArgument::REQUIRED,
                'Path to the base source directory'
            )->addOption(
                'bootstrap',
                'b',
                InputOption::VALUE_OPTIONAL,
                "Run this file before starting analysis, Can be used on your projects vendor/autoload.php directly"
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
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                "The config file to generate/update",
                'bugfree.json'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasOption('tap') && $input->getOption('tap')) {
            $formatter = new TapFormatter($output);
        } else {
            $formatter = new XUnitFormatter($output);
        }

        if ($input->hasOption('bootstrap') && is_string($input->getOption('bootstrap'))) {
            $bootstrap = $input->getOption('bootstrap');


            if (!file_exists(stream_resolve_include_path($bootstrap))) {
                $output->writeln("Bootstrap '$bootstrap' does not exist!");
            } else {
                require_once($bootstrap);
            }
        }

        $config = new Config();

        if ($input->hasOption('config') && is_string($input->getOption('config'))) {
            $configFilename = $input->getOption('config');


            if (file_exists(stream_resolve_include_path($configFilename))) {
                $output->writeln("Config loaded from '$configFilename'\n");
                $config = Config::load($configFilename);
            }
        }

        if ($input->hasOption('autoFix') && $input->getOption('autoFix')) {
            $config->autoFix = true;
        }

        $exclude = null;
        if ($input->hasOption('exclude') && is_string($input->getOption('exclude'))) {
            $exclude = $input->getOption('exclude');
        }

        $basedir = $input->getArgument('dir');
        if (is_dir($basedir)) {
            $directory = new \RecursiveDirectoryIterator($basedir);
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
            $files = [$basedir];
        }

        return $this->lintFiles($basedir, $config, $formatter, $files);
    }

    public function lintFiles($basedir, Config $config, OutputFormatter $formatter, array $files)
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
                // Sort them and apply them in reverse order.
                /** @var $fixLines Fix[] */
                $fixLines = [];
                foreach ($fixes as $fix) {
                    $fixLines[$fix->getLine()] = $fix;
                }

                krsort($fixLines);

                $fileLines = preg_split('/\r|\r\n|\n/', $rawFileContents);
                foreach ($fixLines as $fix) {
                    $fix->run($fileLines);
                }

                var_dump($fileLines);

                file_put_contents($file, implode("\n", $fileLines));
            }
        }

        $formatter->end($status);

        return $status;
    }
}
