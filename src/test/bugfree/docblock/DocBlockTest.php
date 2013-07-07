<?php
/**
 * Created by IntelliJ IDEA.
 * User: adam
 * Date: 7/07/13
 * Time: 7:37 PM
 * To change this template use File | Settings | File Templates.
 */

namespace bugfree\docblock;


class DocBlockTest extends \PHPUnit_Framework_TestCase
{
    public function testSingleParamAnnotation()
    {
        $doc = new DocBlock("/** @param string \$foo a foo */");
        $param = $doc->getAnnotations('param')[0];
        $this->assertInstanceOf('\bugfree\docblock\Param', $param);
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
        $param = $doc->getAnnotations('param')[0];
        $this->assertInstanceOf('\bugfree\docblock\Param', $param);
        $this->assertEquals('string', $param->getType());
        $this->assertEquals('$foo', $param->getName());
        $this->assertEquals('a foo', $param->getDescription());

        $param = $doc->getAnnotations('param')[1];
        $this->assertInstanceOf('\bugfree\docblock\Param', $param);
        $this->assertEquals('turtle', $param->getType());
        $this->assertEquals('$t', $param->getName());
        $this->assertEquals('a turtle', $param->getDescription());
    }
}
