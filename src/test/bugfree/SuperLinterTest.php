<?php
namespace bugfree;


use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

/**
 * Uses bugfree-dangerzone to parse all of the source in bugfree-dangerzone
 */
class SuperLinterTest extends \PHPUnit_Framework_TestCase
{
    protected function getBasedir()
    {
        return __DIR__ . "/../../../src";
    }

    public function fileProvider()
    {
        $directory = new RecursiveDirectoryIterator($this->getBasedir());
        $iterator = new RecursiveIteratorIterator($directory);
        $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

        $files = [];
        foreach ($phpFiles as $it) {
            $files[] = [(string)$it[0]];
        }

        return $files;
    }

    /**
     * @dataProvider fileProvider
     */
    public function testFile($file)
    {
        $bugfree = new Bugfree($file, file_get_contents($file), new AutoloaderResolver($this->getBasedir()));

        if (count($bugfree->getErrors()) > 0) {
            $summary = "\n - " . implode("\n - ", $bugfree->getErrors());
            throw new \PHPUnit_Framework_ExpectationFailedException(
                "There were errors while validating $file: $summary \n"
            );
        }

        if (count($bugfree->getWarnings()) > 0) {
            $summary = "\n - " . implode("\n - ", $bugfree->getWarnings());
            throw new \PHPUnit_Framework_ExpectationFailedException(
                "There were warnings while validating $file: $summary \n"
            );
        }
    }
}