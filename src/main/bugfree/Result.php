<?php

namespace bugfree;


use bugfree\config\Config;
use bugfree\fix\Fix;

class Result
{
    /** @var string */
    private $name;

    /** @var string[] */
    private $errors = [];

    /** @var string[] */
    private $warnings = [];

    /** @var Fix[] */
    private $fixes = [];

    /** @var Config */
    private $config;

    public function __construct($name, Config $config)
    {
        $this->config = $config;
        $this->name = $name;
    }

    /**
     * Adds an error
     *
     * @param string $type Constant from ErrorType::*
     * @param int $line
     * @param string $message
     * @throws \UnexpectedValueException
     * @param string $message
     */
    public function error($type, $line, $message)
    {
        if (!isset($this->config->emitLevel->$type)) {
            throw new \UnexpectedValueException("$type must be one of the ErrorType::* constants");
        }
        $level = $this->config->emitLevel->$type;

        if ($level == ErrorType::SUPPRESS) {
            return;
        }

        $locator = $this->name;
        if ($line) {
            $locator .= ":$line";
        }

        if ($level == ErrorType::ERROR) {
            $this->errors[] = "$locator $message";
        } elseif ($level == ErrorType::WARNING) {
            $this->warnings[] = "$locator $message";
        }
    }

    public function fix(Fix $fix)
    {
        $this->fixes[] = $fix;
    }

    /**
     * @return string[] all of the errors in this file.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return string[] all of the warnings in this file.
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * @return Fix[] all of the suggested fixes for this file
     */
    public function getFixes()
    {
        return $this->fixes;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }
}
