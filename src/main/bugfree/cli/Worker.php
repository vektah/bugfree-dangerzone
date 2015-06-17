<?php

namespace bugfree\cli;


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

    /**
     * Requires the boostrap file. This is in its own function to prevent the bootstrap from altering local variables.
     *
     * @param string $filename
     */
    private function bootstrap($filename) {
        require_once($filename);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = new JsonFormatter($output);

        $config = Config::load($input->getOption('config'));
        $this->bootstrap($config->getBoostrapPath());

        if ($input->getOption('autoFix')) {
            $config->autoFix = true;
        }

        $bugfree = new Bugfree(new AutoloaderResolver($config), $config);

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
                require_once($file);
                $rawFileContents = file_get_contents($file);
                $result = $bugfree->parse($file, $rawFileContents);
            } catch (\Exception $e) {
                $status = self::ERROR;
                $formatter->testFailed(1, $file, ["Exception while parsing $file " . get_class($e) . '("' . $e->getMessage() . "\")\n" . $e->getTraceAsString()], []);
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

                file_put_contents($file, trim(implode("\n", $fileLines)) . "\n");
            }

            $formatter->end($status);
        }

        return $status;
    }
}
