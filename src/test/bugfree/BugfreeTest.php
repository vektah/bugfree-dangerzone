<?php

namespace bugfree;


use Phake;

class FileAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    private $resolver;

    public function setUp() {
        $this->resolver = Phake::mock(Resolver::_CLASS);
        Phake::when($this->resolver)->isValid()->thenReturn(true);
    }

    private function assertArrayValuesContains(array $array, $string)
    {
        $found = false;
        foreach ($array as $value) {
            if (strstr($value, $string) !== false) {
                $found = true;
            }
        }

        $this->assertTrue($found, 'Element not found in array');
    }

    public function testNamespaceError()
    {
        $analyzer = new Bugfree('test', '<?php use asdf,hjkl;', $this->resolver);
        // Make sure no namespace raises an error
        $this->assertArrayValuesContains($analyzer->getErrors(), 'Every source file should have a namespace');

        // But also make sure parsing continues!
        $this->assertArrayValuesContains($analyzer->getWarnings(), 'Multiple uses in one statement is discouraged');
    }

    public function testMultiPartUseWarning()
    {
        $analyzer = new Bugfree('test', '<?php namespace foo; use asdf, hjkl;', $this->resolver);
        $this->assertArrayValuesContains($analyzer->getWarnings(), 'Multiple uses in one statement is discouraged');
    }

    public function testUnresolvingUseStatement()
    {
        Phake::when($this->resolver)->isValid('\asdf')->thenReturn(false);

        $analyzer = new Bugfree('test', '<?php namespace foo; use asdf, hjkl;', $this->resolver);

        $this->assertArrayValuesContains($analyzer->getErrors(), "Use '\\asdf' cannot be resolved");
    }
}
