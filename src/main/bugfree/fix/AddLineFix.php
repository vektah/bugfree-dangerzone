<?php

namespace bugfree\fix;

class AddLineFix extends AbstractFix
{
    private $newLine;

    public function __construct($line, $reason, $newLine)
    {
        parent::__construct($line, $reason);
        $this->newLine = $newLine;
    }

    public function run(array &$fileLines)
    {
        array_splice($fileLines, $this->getLine() - 1, 0, $this->newLine);
    }

    public function getRank()
    {
        return 75;
    }

    public function getOrder()
    {
        return -$this->getLine();
    }
}
