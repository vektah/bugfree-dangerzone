<?php

namespace bugfree\output;


interface OutputFormatter
{
    public function begin($expectedTests);
    public function testPassed($testNumber, $filename);
    public function testFailed($testNumber, $filename, array $errors, array $warnings);
    public function end($status);
}
