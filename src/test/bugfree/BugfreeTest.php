<?php

namespace bugfree;


use Phake;

class FileAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    private $resolver;

    public function setUp()
    {
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

        $this->assertTrue($found, "Element '$string' not found in array: " . print_r($array, true));
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

        $this->assertTrue(true);
        $this->assertArrayValuesContains($analyzer->getErrors(), "Use '\\asdf' could not be resolved");
    }

    public function useProvider()
    {
        return [
            [[
                'invalid'   => ['\testns\DoesNotExist'],
                'valid'     => [],
                'type'      => 'DoesNotExist',
                'errors'    => ["Type '\\testns\\DoesNotExist' could not be resolved"],
                'warnings'  => [],
            ]],
            [[
                'invalid'   => [],
                'valid'     => ['\testns\DoesNotExist'],
                'type'      => 'DoesNotExist',
                'errors'    => [],
                'warnings'  => [],
            ]],
            [[
                'invalid'   => ['\testns\DoesNotExist'],
                'valid'     => [],
                'type'      => '\testns\DoesNotExist',
                'errors'    => ["Type '\\testns\\DoesNotExist' could not be resolved"],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => [],
                'valid'     => ['\testns\DoesNotExist'],
                'type'      => '\testns\DoesNotExist',
                'errors'    => [],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => ['\foo\bar\baz\DoesNotExist'],
                'valid'     => [],
                'type'      => 'baz\DoesNotExist',
                'errors'    => ["Type '\\foo\\bar\\baz\\DoesNotExist' could not be resolved"],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => [],
                'valid'     => ['\foo\bar\baz\DoesNotExist'],
                'type'      => 'baz\DoesNotExist',
                'errors'    => [],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => [],
                'valid'     => [],
                'type'      => 'boo\DoesNotExist',
                'errors'    => ["Type '\\testns\\boo\\DoesNotExist' could not be resolved"],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => [],
                'valid'     => [],
                'type'      => 'Thing',
                'errors'    => [],
                'warnings'  => [],
            ]],
            [[
                'invalid'   => ['\Thing'],
                'valid'     => [],
                'type'      => '\Thing',
                'errors'    => ["Type '\\Thing' could not be resolved"],
                'warnings'  => [],
            ]],
            [[
                'invalid'   => [],
                'valid'     => ['\Thing'],
                'type'      => '\Thing',
                'errors'    => [],
                'warnings'  => [],
            ]],
        ];
    }

    /**
     * @dataProvider useProvider
     */
    public function testResolutionOnMethod(array $options)
    {
        foreach ($options['invalid'] as $invalid) {
            Phake::when($this->resolver)->isValid($invalid)->thenReturn(false);
        }

        foreach ($options['valid'] as $valid) {
            Phake::when($this->resolver)->isValid($valid)->thenReturn(true);
        }

        Phake::when($this->resolver)->isValid('\\foo\\bar\\baz')->thenReturn(true);
        Phake::when($this->resolver)->isValid('\\foo\\Thing')->thenReturn(true);

        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function asdf({$options['type']} \$foo) {}
        ";
        $analyzer = new Bugfree('test', $src, $this->resolver);

        foreach ($options['errors'] as $error) {
            $this->assertArrayValuesContains($analyzer->getErrors(), $error);
        }
        $this->assertEquals(
            count($options['errors']),
            count($analyzer->getErrors()),
            print_r($analyzer->getErrors(), true)
        );

        foreach ($options['warnings'] as $warning) {
            $this->assertArrayValuesContains($analyzer->getWarnings(), $warning);
        }
        $this->assertEquals(
            count($options['warnings']),
            count($analyzer->getWarnings()),
            print_r($analyzer->getWarnings(), true)
        );

        foreach (array_merge($options['invalid'], $options['valid']) as $resolveCall) {
            Phake::verify($this->resolver)->isValid($resolveCall);
        }
    }

    /**
     * @dataProvider useProvider
     */
    public function testClassImplements($options)
    {
        foreach ($options['invalid'] as $invalid) {
            Phake::when($this->resolver)->isValid($invalid)->thenReturn(false);
        }

        foreach ($options['valid'] as $valid) {
            Phake::when($this->resolver)->isValid($valid)->thenReturn(true);
        }

        Phake::when($this->resolver)->isValid('\\foo\\bar\\baz')->thenReturn(true);
        Phake::when($this->resolver)->isValid('\\foo\\Thing')->thenReturn(true);

        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        class far implements {$options['type']} {}
        ";
        $analyzer = new Bugfree('test', $src, $this->resolver);

        foreach ($options['errors'] as $error) {
            $this->assertArrayValuesContains($analyzer->getErrors(), $error);
        }
        $this->assertEquals(
            count($options['errors']),
            count($analyzer->getErrors()),
            print_r($analyzer->getErrors(), true)
        );

        foreach ($options['warnings'] as $warning) {
            $this->assertArrayValuesContains($analyzer->getWarnings(), $warning);
        }
        $this->assertEquals(
            count($options['warnings']),
            count($analyzer->getWarnings()),
            print_r($analyzer->getWarnings(), true)
        );

        foreach (array_merge($options['invalid'], $options['valid']) as $resolveCall) {
            Phake::verify($this->resolver)->isValid($resolveCall);
        }
    }

    /**
     * @dataProvider useProvider
     */
    public function testClassExtends($options)
    {
        foreach ($options['invalid'] as $invalid) {
            Phake::when($this->resolver)->isValid($invalid)->thenReturn(false);
        }

        foreach ($options['valid'] as $valid) {
            Phake::when($this->resolver)->isValid($valid)->thenReturn(true);
        }

        Phake::when($this->resolver)->isValid('\\foo\\bar\\baz')->thenReturn(true);
        Phake::when($this->resolver)->isValid('\\foo\\Thing')->thenReturn(true);

        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        class far extends {$options['type']} {}
        ";
        $analyzer = new Bugfree('test', $src, $this->resolver);

        foreach ($options['errors'] as $error) {
            $this->assertArrayValuesContains($analyzer->getErrors(), $error);
        }
        $this->assertEquals(
            count($options['errors']),
            count($analyzer->getErrors()),
            print_r($analyzer->getErrors(), true)
        );

        foreach ($options['warnings'] as $warning) {
            $this->assertArrayValuesContains($analyzer->getWarnings(), $warning);
        }
        $this->assertEquals(
            count($options['warnings']),
            count($analyzer->getWarnings()),
            print_r($analyzer->getWarnings(), true)
        );

        foreach (array_merge($options['invalid'], $options['valid']) as $resolveCall) {
            Phake::verify($this->resolver)->isValid($resolveCall);
        }
    }

    /**
     * @dataProvider useProvider
     */
    public function testMethod($options)
    {
        foreach ($options['invalid'] as $invalid) {
            Phake::when($this->resolver)->isValid($invalid)->thenReturn(false);
        }

        foreach ($options['valid'] as $valid) {
            Phake::when($this->resolver)->isValid($valid)->thenReturn(true);
        }

        Phake::when($this->resolver)->isValid('\\foo\\bar\\baz')->thenReturn(true);
        Phake::when($this->resolver)->isValid('\\foo\\Thing')->thenReturn(true);

        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        class far {
            public function boo({$options['type']} \$foo) {}
        }
        ";
        $analyzer = new Bugfree('test', $src, $this->resolver);

        foreach ($options['errors'] as $error) {
            $this->assertArrayValuesContains($analyzer->getErrors(), $error);
        }
        $this->assertEquals(
            count($options['errors']),
            count($analyzer->getErrors()),
            print_r($analyzer->getErrors(), true)
        );

        foreach ($options['warnings'] as $warning) {
            $this->assertArrayValuesContains($analyzer->getWarnings(), $warning);
        }
        $this->assertEquals(
            count($options['warnings']),
            count($analyzer->getWarnings()),
            print_r($analyzer->getWarnings(), true)
        );

        foreach (array_merge($options['invalid'], $options['valid']) as $resolveCall) {
            Phake::verify($this->resolver)->isValid($resolveCall);
        }
    }
}
