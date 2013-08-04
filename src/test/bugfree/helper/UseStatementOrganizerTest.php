<?php

namespace bugfree\helper;


use BadMethodCallException;
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

    public function testSameLastNamespacePartWithAlias()
    {
        $useStatements = [
            $this->getMockUseStatement("a\b\c\AClass", 1),
            $this->getMockUseStatement("a\b\c\aclass", 2, "awful"),
            $this->getMockUseStatement("a\b\c\aclass", 3, "horrid")
        ];
        $useStatementOrganizer = new UseStatementOrganizer($useStatements);

        $this->assertTrue($useStatementOrganizer->areOrganized());
    }

    public function testSameLastNamespacePartWithAliasNotSorted()
    {
        $useStatements = [
            $this->getMockUseStatement("a\b\c\AClass", 1),
            $this->getMockUseStatement("a\b\c\aclass", 2, "horrid"),
            $this->getMockUseStatement("a\b\c\aclass", 3, "awful")
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

        $mapping = [
            3 => 4,
            4 => 6,
            6 => 11,
            9 => 13,
            11 => 3,
            13 => 9
        ];

        $this->assertEquals($mapping, $useStatementOrganizer->getLineNumberMovements());
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

        $mapping = [
            5 => 5,
            6 => 6,
            8 => 8,
            9 => 9,
            11 => 17,
            12 => 16,
            14 => 11,
            16 => 12,
            17 => 14
        ];

        $this->assertEquals($mapping, $useStatementOrganizer->getLineNumberMovements());
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testGetLineNumberMappingWithoutLineSwap()
    {
        $useStatementOrganizer = new UseStatementOrganizer([]);
        $useStatementOrganizer->getLineNumberMovements();
    }

    private function getMockUseStatement($namepace, $line = 0, $alias = null)
    {
        $parts = explode("\\", $namepace);
        $lastPart = $parts[count($parts) - 1];

        if (!$alias) {
            $alias = $parts[count($parts) - 1];
        }

        $name = Phake::mock("PHPParser_Node_Name");
        Phake::when($name)->__get("parts")->thenReturn($parts);
        Phake::when($name)->toString()->thenReturn($namepace);

        $useStatement = Phake::mock("PHPParser_Node_Stmt_UseUse");
        Phake::when($useStatement)->__get("name")->thenReturn($name);
        Phake::when($useStatement)->__get("alias")->thenReturn($alias);
        Phake::when($useStatement)->getLine()->thenReturn($line);

        return $useStatement;
    }
}
