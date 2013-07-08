<?php

namespace bugfree\cli;


use bugfree\config\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateConfig extends Command
{
    const SUCCESS = 0;
    const WARNING = 1;
    const ERROR = 2;

    protected function configure()
    {
        $this->setName('generateConfig')
            ->setDescription('Reads the config if one exists, then updates it with any missing keys.')
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                "The config file to generate/update",
                'bugfree.json'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config_filename = $input->getOption('config');
        if (is_file($config_filename)) {
            $config = Config::load($config_filename);
        } else {
            $config = new Config();
        }

        $config->save($config_filename);
    }
}
