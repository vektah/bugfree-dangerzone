<?php

namespace bugfree\cli;


use bugfree\AutoloaderResolver;
use bugfree\Bugfree;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CliTool extends Command
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasOption('bootstrap') && is_string($input->getOption('bootstrap'))) {
            $bootstrap = $input->getOption('bootstrap');


            if (!file_exists(stream_resolve_include_path($bootstrap))) {
                $output->writeln("Bootstrap '$bootstrap' does not exist!");
            } else {
                require_once($bootstrap);
            }
        }
        $basedir = $input->getArgument('dir');
        if (is_dir($basedir)) {
            $directory = new \RecursiveDirectoryIterator($basedir);
            $iterator = new \RecursiveIteratorIterator($directory);
            $phpFiles = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

            $files = [];
            foreach ($phpFiles as $it) {
                $files[] = (string)$it[0];
            }
        } else {
            $files = [$basedir];
        }

        $count = count($files);
        $output->writeln("TAP version 13");
        $output->writeln("1..{$count}");

        $status = self::SUCCESS;
        foreach ($files as $index => $file) {
            $testNumber = $index+1;
            try {
                $bugfree = new Bugfree($file, file_get_contents($file), new AutoloaderResolver($basedir));
            } catch (\Exception $e) {
                $output->writeln("not ok $testNumber - $file");

                $output->writeln("\t---");
                $output->writeln("\t - {$e->getMessage()}");
                $output->writeln("\t...");
                continue;
            }

            if (count($bugfree->getErrors()) > 0 || count($bugfree->getWarnings()) > 0) {
                $output->writeln("not ok $testNumber - $file");

                $output->writeln("\t---");
                foreach ($bugfree->getErrors() as $error) {
                    $status = self::ERROR;
                    $output->writeln("\t" . $error);
                }
                foreach ($bugfree->getWarnings() as $warning) {
                    if ($status < self::WARNING) {
                        $status = self::WARNING;
                    }
                    $output->writeln("\t" . $warning);
                }
                $output->writeln("\t...");
            } else {
                $output->writeln("ok $testNumber - $file");
            }
        }

        return $status;
    }
}
