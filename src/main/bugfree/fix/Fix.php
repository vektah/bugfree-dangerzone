<?php

namespace bugfree\fix;

interface Fix
{
    /**
     * @return int the line that will be affected by this fix.
     */
    public function getLine();

    /**
     * @return int a priority. This should default to the line number. Highest will happen first. Can be negative.
     *             This is only used when comparing similar fixes.
     */
    public function getOrder();

    /**
     * @return int a priority. This is used to compare to different types of fixes. It should not change based on the state.
     */
    public function getRank();

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
