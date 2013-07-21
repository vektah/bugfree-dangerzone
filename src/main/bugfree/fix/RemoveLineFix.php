<?php

namespace bugfree\fix;


class RemoveLineFix implements Fix
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

    public function run(array &$fileLines)
    {
        array_splice($fileLines, $this->line - 1, 1);
    }

    public function getReason()
    {
        return $this->reason;
    }
}
