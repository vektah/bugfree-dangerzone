<?php

namespace bugfree\output;


use bugfree\ErrorType;

class CheckStyleOutputFormatter implements OutputFormatter
{
    private $resultXml = '';
    private $filename;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function begin($expectedTests)
    {
        $this->resultXml .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $this->resultXml .= "<checkstyle version=\"4.3\">\n";
    }

    public function testPassed($testNumber, $filename)
    {
        $this->resultXml .= "\t<file name=\"$filename\" />\n";
    }

    public function testFailed($testNumber, $filename, array $errors)
    {
        $this->resultXml .= "\t<file name=\"$filename\">\n";
        foreach ($errors as $error) {
            $type = 'Error';
            if ($error->severity == ErrorType::WARNING) {
                $type = 'Warning';
            }

            $this->resultXml .= "\t\t<error line=\"{$error->line}\" column=\"1\" severity=\"$type\" message=\"{$error->message}\" />\n";
        }
        $this->resultXml .= "\t</file>\n";
    }

    public function end($status)
    {
        $this->resultXml .= "</checkstyle>\n";
        file_put_contents($this->filename, $this->resultXml);
    }
}
