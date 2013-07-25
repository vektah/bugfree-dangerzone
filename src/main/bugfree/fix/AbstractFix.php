<?php

namespace bugfree\fix;


abstract class AbstractFix implements Fix
{
    /** @var int */
    private $line;

    /** @var string */
    private $reason;

    public function __construct($line, $reason)
    {
        $this->line = $line;
        $this->reason = $reason;
    }

    public function getLine()
    {
        return $this->line;
    }

    abstract public function run(array &$fileLines);

    public function getReason()
    {
        return $this->reason;
    }
}
