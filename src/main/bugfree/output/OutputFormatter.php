<?php

namespace bugfree\output;


use bugfree\Error;

interface OutputFormatter
{
    /**
     * @param int $expectedTests
     */
    public function begin($expectedTests);

    /**
     * @param int $testNumber
     * @param string $filename
     */
    public function testPassed($testNumber, $filename);

    /**
     * @param int       $testNumber
     * @param string    $filename
     * @param Error[]   $errors
     */
    public function testFailed($testNumber, $filename, array $errors);

    /**
     * @param int $status
     */
    public function end($status);
}
