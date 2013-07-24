<?php

namespace bugfree\output;


class JunitOutputFormatter implements OutputFormatter
{
    private $resultXml = '';
    private $filename;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function begin($expectedTests)
    {
        $this->resultXml .= "<testsuite tests=\"$expectedTests\">\n";
    }

    public function testPassed($testNumber, $filename)
    {
        $this->resultXml .= "\t<testcase name=\"$filename\" />\n";
    }

    public function testFailed($testNumber, $filename, array $errors, array $warnings)
    {
        $this->resultXml .= "\t<testcase name=\"$filename\">\n";
        foreach ($errors as $msg) {
            $this->resultXml .= "\t\t<failure type=\"Failure\">$msg</failure>\n";
        }
        foreach ($warnings as $msg) {
            $this->resultXml .= "\t\t<failure type=\"Warning\">$msg</failure>\n";
        }
        $this->resultXml .= "\t</testcase>\n";
    }

    public function end($status)
    {
        $this->resultXml .= "</testsuite>\n";
        file_put_contents($this->filename, $this->resultXml);
    }
}
