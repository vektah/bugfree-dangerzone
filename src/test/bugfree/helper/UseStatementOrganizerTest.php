<?php

namespace bugfree\helper;


use Phake;

class UseStatementOrganizerTest extends \PHPUnit_Framework_TestCase
{
    public function testNoUseStatements()
    {
        $useStatements = [];
        $useStatementOrganizer = new UseStatementOrganizer($useStatements);

        $this->assertTrue($useStatementOrganizer->areOrganized());
    }

    public function testOrganizedUseStatements()
    {
        $useStatements = [
            $this->getMockUseStatement("a\b\c\AClass"),
            $this->getMockUseStatement("a\b\c\BClass"),
            $this->getMockUseStatement("a\b\c\BClass"),
            $this->getMockUseStatement("a\b\c\d\AClass"),
            $this->getMockUseStatement("a\b\c\d\BClass")
        ];
        $useStatementOrganizer = new UseStatementOrganizer($useStatements);

        $this->assertTrue($useStatementOrganizer->areOrganized());
    }

    public function testUnorganizedUseStatements()
    {
        $useStatements = [
            $this->getMockUseStatement("a\b\c\AClass"),
            $this->getMockUseStatement("a\b\c\d\AClass"),
            $this->getMockUseStatement("a\b\c\BClass"),
            $this->getMockUseStatement("a\b\c\d\BClass")
        ];
        $useStatementOrganizer = new UseStatementOrganizer($useStatements);

        $this->assertFalse($useStatementOrganizer->areOrganized());
    }

    public function testGetLineSwaps()
    {
        $useStatements = [
            $this->getMockUseStatement("a\b\c\AClass", 3),
            $this->getMockUseStatement("a\B\c\BClass", 4),
            $this->getMockUseStatement("a\b\c\d\BClass", 6),
            $this->getMockUseStatement("a\b\c\d\CClass", 9),
            $this->getMockUseStatement("a\b\DClass", 11),
            $this->getMockUseStatement("a\b\c\DClass", 13)
        ];
        $useStatementOrganizer = new UseStatementOrganizer($useStatements);
        $lineSwaps = $useStatementOrganizer->getLineSwaps();

        $this->assertEquals(4, count($lineSwaps));
        $this->assertEquals(9, $lineSwaps[13]);
        $this->assertEquals(6, $lineSwaps[11]);
        $this->assertEquals(4, $lineSwaps[6]);
        $this->assertEquals(3, $lineSwaps[4]);
    }

    public function testGetLineSwaps2()
    {
        $useStatements = [
            $this->getMockUseStatement("a\b\AClass", 5),
            $this->getMockUseStatement("a\b\BClass", 6),
            $this->getMockUseStatement("a\b\CClass", 8),
            $this->getMockUseStatement("a\b\DClass", 9),
            $this->getMockUseStatement("a\b\c\d\DClass", 11),
            $this->getMockUseStatement("a\b\c\d\CClass", 12),
            $this->getMockUseStatement("a\b\EClass", 14),
            $this->getMockUseStatement("a\b\c\AClass", 16),
            $this->getMockUseStatement("a\b\c\BClass", 17)
        ];
        $useStatementOrganizer = new UseStatementOrganizer($useStatements);
        $lineSwaps = $useStatementOrganizer->getLineSwaps();

        $this->assertEquals(3, count($lineSwaps));
        $this->assertEquals(11, $lineSwaps[17]);
        $this->assertEquals(12, $lineSwaps[16]);
        $this->assertEquals(11, $lineSwaps[14]);
    }

    private function getMockUseStatement($namepace, $line = 0)
    {
        $name = Phake::mock("PHPParser_Node_Name");
        Phake::when($name)->__get("parts")->thenReturn(explode("\\", $namepace));
        Phake::when($name)->toString()->thenReturn($namepace);

        $useStatement = Phake::mock("PHPParser_Node_Stmt_UseUse");
        Phake::when($useStatement)->__get("name")->thenReturn($name);
        Phake::when($useStatement)->getLine()->thenReturn($line);

        return $useStatement;
    }
}
