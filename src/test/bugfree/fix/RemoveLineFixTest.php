<?php

namespace bugfree\fix;

class RemoveLineFixTest extends \PHPUnit_Framework_TestCase
{
    const LINE_1 = "line 1";
    const LINE_2 = "line 2";
    const LINE_3 = "line 3";
    const LINE_4 = "line 4";
    const LINE_5 = "line 5";

    /** @var array */
    private $fileLines;

    public function setUp()
    {
        $this->fileLines = [
            self::LINE_1,
            self::LINE_2,
            self::LINE_3,
            self::LINE_4,
            self::LINE_5
        ];
    }

    public function testRemoveLine()
    {
        $fix = new RemoveLineFix(1, "");
        $fix->run($this->fileLines);

        $this->assertEquals(self::LINE_2, $this->fileLines[0]);
    }

    public function testMultipleRemoveLine()
    {
        $fix = new RemoveLineFix(1, "");
        $fix->run($this->fileLines);
        $fix = new RemoveLineFix(3, "");
        $fix->run($this->fileLines);

        $this->assertEquals(self::LINE_2, $this->fileLines[0]);
        $this->assertEquals(self::LINE_3, $this->fileLines[1]);
        $this->assertEquals(self::LINE_5, $this->fileLines[2]);
    }
}
