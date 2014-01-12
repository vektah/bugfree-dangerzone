<?php

namespace bugfree\cli;


use Exception;
use bugfree\AutoloaderResolver;
use bugfree\Bugfree;
use bugfree\config\Config;
use bugfree\output\JsonFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Worker extends Command
{
    const SUCCESS = 0;
    const WARNING = 1;
    const ERROR = 2;

    protected function configure()
    {

        $this->setName('worker');
        $this->setDescription('Waits for files to run on stdin and returns JSON results as each completes.');
        $this->addOption(
            'bootstrap',
            'b',
            InputOption::VALUE_REQUIRED,
            "Run this file before starting analysis, Can be used on your projects vendor/autoload.php directly"
        );

        $this->addOption(
            'basedir',
            'd',
            InputOption::VALUE_REQUIRED,
            "The start of the namespace path, used to validate partial uses.",
            'src'
        );

        $this->addOption(
            'autoFix',
            'a',
            InputOption::VALUE_NONE,
            'Automatically fix common problems.'
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
        $formatter = new JsonFormatter($output);

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

        $basedir = realpath($input->getOption('basedir'));
        $bugfree = new Bugfree(new AutoloaderResolver($basedir), $config);

        $status = self::SUCCESS;

        while ($file = fgets(STDIN)) {
            $formatter->begin(1);
            $file = trim($file);

            if (!$file) {
                continue;
            }

            if (!file_exists($file)) {
                $formatter->testFailed(1, $file, ["file '$file' does not exist"]);
                continue;
            }

            try {
                $rawFileContents = file_get_contents($file);
                $result = $bugfree->parse($file, $rawFileContents);
            } catch (\Exception $e) {
                $status = self::ERROR;
                $formatter->testFailed(1, $file, [$e->getMessage()], []);
                continue;
            }

            if (count($result->getErrors()) > 0) {
                $status = self::ERROR;
                $formatter->testFailed(1, $file, $result->getErrors());
            } else {
                $formatter->testPassed(1, $file);
            }

            if (count($result->getFixes()) > 0) {
                $fixes = $result->getFixes();

                $fileLines = preg_split('/\r|\r\n|\n/', $rawFileContents);
                foreach ($fixes as $fix) {
                    $fix->run($fileLines);
                }

                file_put_contents($file, implode("\n", $fileLines));
            }

            $formatter->end($status);
        }

        return $status;
    }
}
