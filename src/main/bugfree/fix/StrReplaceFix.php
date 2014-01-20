<?php

namespace bugfree\fix;


class StrReplaceFix extends AbstractFix
{
    /** @var string */
    private $search;

    /** @var string */
    private $replacement;

    public function __construct($line, $reason, $search, $replacement)
    {
        parent::__construct($line, $reason);
        $this->search = $search;
        $this->replacement = $replacement;
    }

    public function run(array &$fileLines)
    {
        $index = $this->getLine() - 1;

        $fileLines[$index] = str_replace($this->search, $this->replacement, $fileLines[$index]);
    }
}
