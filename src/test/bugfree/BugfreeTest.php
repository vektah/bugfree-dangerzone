<?php

namespace bugfree;


use bugfree\config\Config;
use Phake;

class BugfreeTest extends \PHPUnit_Framework_TestCase
{
    private $resolver;

    /** @var  Bugfree */
    private $bugfree;

    public function setUp()
    {
        $this->resolver = Phake::mock(Resolver::_CLASS);
        Phake::when($this->resolver)->isValid(Phake::anyParameters())->thenReturn(true);

        $this->bugfree = new Bugfree($this->resolver, new Config());
    }

    private function assertArrayValuesContains(array $array, $string)
    {
        $found = false;
        foreach ($array as $value) {
            if (strstr($value, $string) !== false) {
                $found = true;
            }
        }

        if (!$found) {
            throw new \PHPUnit_Framework_AssertionFailedError("Element '$string' not found in array: " . print_r($array, true));
        }
    }

    public function testNamespaceError()
    {
        $result = $this->bugfree->parse('test', '<?php use asdf,hjkl;', $this->resolver);
        // Make sure no namespace raises an error
        $this->assertArrayValuesContains($result->getErrors(), 'Every source file should have a namespace');

        // But also make sure parsing continues!
        $this->assertArrayValuesContains($result->getWarnings(), 'Multiple uses in one statement is discouraged');
    }

    public function testMultiPartUseWarning()
    {
        $result = $this->bugfree->parse('test', '<?php namespace foo; use asdf, hjkl;', $this->resolver);
        $this->assertArrayValuesContains($result->getWarnings(), 'Multiple uses in one statement is discouraged');
    }

    public function testUnresolvingUseStatement()
    {
        Phake::when($this->resolver)->isValid('\asdf')->thenReturn(false);

        $result = $this->bugfree->parse('test', '<?php namespace foo; use asdf, hjkl;', $this->resolver);

        $this->assertArrayValuesContains($result->getErrors(), "Use '\\asdf' could not be resolved");
    }

    public function testUnusedUse()
    {
        $result = $this->bugfree->parse('test', '<?php namespace foo; use asdf;', $this->resolver);

        $this->assertArrayValuesContains($result->getWarnings(), "Use 'asdf' is not being used");
    }

    public function testDuplicateAlias()
    {
        $src = '<?php namespace foo;
            use asdf;
            use foo\asdf;
        ';
        $result = $this->bugfree->parse('test', $src, $this->resolver);

        $this->assertArrayValuesContains($result->getErrors(), "Alias 'asdf' is already in use on line 2");
    }

    public function testMultipleNamespaces()
    {
        Phake::when($this->resolver)->isValid('\foo\Foo')->thenReturn(false);
        Phake::when($this->resolver)->isValid('\baz\Baz')->thenReturn(false);

        $src = "<?php
        namespace foo {
            function test(Foo \$f) {}
        }

        namespace baz {
            function test(Baz \$f) {}
        }
        ";

        $result = $this->bugfree->parse('test', $src, $this->resolver);

        $this->assertArrayValuesContains($result->getErrors(), "Type '\\foo\\Foo' could not be resolved");
        $this->assertArrayValuesContains($result->getErrors(), "Type '\\baz\\Baz' could not be resolved");
    }

    public function useProvider()
    {
        return [
            [[
                'invalid'   => ['\testns\DoesNotExist'],
                'type'      => 'DoesNotExist',
                'errors'    => ["Type '\\testns\\DoesNotExist' could not be resolved"],
                'warnings'  => [],
            ]],
            [[
                'invalid'   => [],
                'type'      => 'DoesNotExist',
                'errors'    => [],
                'warnings'  => [],
            ]],
            [[
                'invalid'   => ['\testns\DoesNotExist'],
                'type'      => '\testns\DoesNotExist',
                'errors'    => ["Type '\\testns\\DoesNotExist' could not be resolved"],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => [],
                'type'      => '\testns\DoesNotExist',
                'errors'    => [],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => ['\foo\bar\baz\DoesNotExist'],
                'type'      => 'baz\DoesNotExist',
                'errors'    => ["Type '\\foo\\bar\\baz\\DoesNotExist' could not be resolved"],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => [],
                'type'      => 'baz\DoesNotExist',
                'errors'    => [],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => ['\testns\boo\DoesNotExist'],
                'type'      => 'boo\DoesNotExist',
                'errors'    => ["Type '\\testns\\boo\\DoesNotExist' could not be resolved"],
                'warnings'  => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => [],
                'type'      => 'Thing',
                'errors'    => [],
                'warnings'  => [],
            ]],
            [[
                'invalid'   => ['\Thing'],
                'type'      => '\Thing',
                'errors'    => ["Type '\\Thing' could not be resolved"],
                'warnings'  => [],
            ]],
            [[
                'invalid'   => [],
                'type'      => '\Thing',
                'errors'    => [],
                'warnings'  => [],
            ]],
        ];
    }

    private function verifySource($src, $options)
    {
        foreach ($options['invalid'] as $invalid) {
            Phake::when($this->resolver)->isValid($invalid)->thenReturn(false);
        }

        Phake::when($this->resolver)->isValid('\\foo\\bar\\baz')->thenReturn(true);
        Phake::when($this->resolver)->isValid('\\foo\\Thing')->thenReturn(true);

        $result = $this->bugfree->parse('test', $src, $this->resolver);

        foreach ($options['errors'] as $error) {
            $this->assertArrayValuesContains($result->getErrors(), $error);
        }
        $this->assertEquals(
            count($options['errors']),
            count($result->getErrors()),
            print_r($result->getErrors(), true)
        );

        foreach ($options['warnings'] as $warning) {
            $this->assertArrayValuesContains($result->getWarnings(), $warning);
        }
        $this->assertEquals(
            count($options['warnings']),
            count($result->getWarnings()),
            print_r($result->getWarnings(), true)
        );

        foreach ($options['invalid'] as $resolveCall) {
            Phake::verify($this->resolver)->isValid($resolveCall);
        }
    }

    /**
     * @dataProvider useProvider
     */
    public function testResolutionOnMethod(array $options)
    {
        $src = "<?php namespace testns;
                use foo\\bar\\baz;
                use foo\\Thing;

                function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

                function asdf({$options['type']} \$foo) {}";

        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testClassImplements($options)
    {

        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        class far implements {$options['type']} {}
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testClassExtends($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        class far extends {$options['type']} {}
        ";

        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testMethod($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        class far {
            public function boo({$options['type']} \$foo) {}
        }
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testCatch($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        try {

        } catch({$options['type']} \$e) {}
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testNew($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        \$f = new {$options['type']}();
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testConstant($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        \$f = {$options['type']}::ASDF;
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testConstantInFunctionCall($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        foo({$options['type']}::ASDF);
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testConstantInStaticMethodCall($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        baz::foo({$options['type']}::ASDF);
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testConstantInInstanceMethodCall($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        \$f = new Thing();
        \$f->foo({$options['type']}::ASDF);
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testUseResolutionInFunctionDocBlockTypeHint($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        /**
         * @param {$options['type']} \$a A thing
         */
        function foo(\$a) {}
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testUseResolutionInFunctionDocBlockReturnTypeHint($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        /**
         * @return {$options['type']} a return value
         */
        function foo(\$a) {}
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testUseResolutionInFunctionDocBlockTypeHintArraySyntax($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        /**
         * @param {$options['type']}[] \$a A thing
         */
        function foo(\$a) {}
        ";
        $this->verifySource($src, $options);
    }

    /**
    * @dataProvider useProvider
    */
    public function testUseResolutionInAtVarAnnotationSyntax($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        /**
        * @var {$options['type']}[] \$a A thing
        */
        \$foo = 'asdf';
        ";
        $this->verifySource($src, $options);
    }

    /**
    * @dataProvider useProvider
    */
    public function testUseResolutionInAtVarAnnotationSyntaxOnClassPrivates($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        class Faz {
            /**
            * @var {$options['type']}[] \$a A thing
            */
            private \$foo = 'asdf';
        }
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testUseResolutionInTraitUse($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        class Faz {
            use {$options['type']};
        }
        ";
        $this->verifySource($src, $options);
    }

    public function builtinTypeProvider()
    {
        return [
            ['string'],
            ['integer'],
            ['int'],
            ['boolean'],
            ['bool'],
            ['float'],
            ['double'],
            ['object'],
            ['mixed'],
            ['array'],
            ['resource'],
            ['void'],
            ['null'],
            ['callback'],
            ['false'],
            ['true'],
            ['self']
        ];
    }

    /**
     * @dataProvider builtinTypeProvider
     */
    public function testBuiltinTypesIgnoredInDocParams($builtinType)
    {
        Phake::when($this->resolver)->isValid("\\foo\\$builtinType")->thenReturn(false);

        $src = "<?php namespace foo;
            /**
             * @param $builtinType \$a ggg
             */
            function foo(\$a) {}
        ";

        $result = $this->bugfree->parse('test', $src, $this->resolver);

        $this->assertEquals(0, count($result->getWarnings()), print_r($result->getWarnings(), true));
        $this->assertEquals(0, count($result->getErrors()), print_r($result->getErrors(), true));
    }

    public function testTypesCombinedWithOrInDocTypeHint()
    {
        Phake::when($this->resolver)->isValid("\\foo\\string")->thenReturn(false);
        Phake::when($this->resolver)->isValid("\\foo\\integer")->thenReturn(false);

        $src = "<?php namespace foo;
            /**
             * @param string|integer \$a ggg
             */
            function foo(\$a) {}
        ";

        $result = $this->bugfree->parse('test', $src, $this->resolver);

        $this->assertEquals(0, count($result->getWarnings()), print_r($result->getWarnings(), true));
        $this->assertEquals(0, count($result->getErrors()), print_r($result->getErrors(), true));
    }
}
