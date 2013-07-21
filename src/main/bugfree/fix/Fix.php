<?php

namespace bugfree\fix;


interface Fix
{
    /**
     * @return int the line that will be affected by this fix.
     */
    public function getLine();

    /**
     * Actually do the fix.
     *
     * @param array $fileLines  The file broken into lines.
     */
    public function run(array &$fileLines);

    /**
     * @return string the reason for this fix.
     */
    public function getReason();
}
