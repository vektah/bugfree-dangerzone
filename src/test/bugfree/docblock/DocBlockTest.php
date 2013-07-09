<?php

namespace bugfree\docblock;


class DocBlockTest extends \PHPUnit_Framework_TestCase
{
    public function testSingleParamAnnotation()
    {
        $doc = new DocBlock("/** @param string \$foo a foo */");
        $param = $doc->getParams()[0];
        $this->assertInstanceOf(Param::_CLASS, $param);
        $this->assertEquals('string', $param->getType());
        $this->assertEquals('$foo', $param->getName());
        $this->assertEquals('a foo', $param->getDescription());
    }

    public function testMultipleParamAnnotations()
    {
        $doc = new DocBlock(
            "/**
              * @param string \$foo a foo
              * @param turtle \$t a turtle
              */"
        );
        $param = $doc->getParams()[0];
        $this->assertInstanceOf(Param::_CLASS, $param);
        $this->assertEquals('string', $param->getType());
        $this->assertEquals('$foo', $param->getName());
        $this->assertEquals('a foo', $param->getDescription());

        $param = $doc->getParams()[1];
        $this->assertInstanceOf(Param::_CLASS, $param);
        $this->assertEquals('turtle', $param->getType());
        $this->assertEquals('$t', $param->getName());
        $this->assertEquals('a turtle', $param->getDescription());
    }

    public function testReturnAnnotation()
    {
        $doc = new DocBlock("/** @return string a foo */");
        $return = $doc->getReturn();
        $this->assertInstanceOf(Returns::_CLASS, $return);
        $this->assertEquals('string', $return->getType());
        $this->assertEquals('a foo', $return->getDescription());
    }
}
