<?php

namespace bugfree;


use Phake;
use bugfree\config\Config;

class BugfreeTest extends \PHPUnit_Framework_TestCase
{
    private $resolver;

    /** @var  Bugfree */
    private $bugfree;

    public function setUp()
    {
        $this->resolver = Phake::mock(Resolver::_CLASS);
        Phake::when($this->resolver)->isValid(Phake::anyParameters())->thenReturn(true);

        $config = new Config();
        $config->emitLevel->{ErrorType::DISORGANIZED_USES} = ErrorType::SUPPRESS;

        $this->bugfree = new Bugfree($this->resolver, $config);
    }

    private function assertErrorWithMessage(array $array, $string)
    {
        $found = false;
        foreach ($array as $value) {
            if (strstr($value->getFormatted(), $string) !== false) {
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
        $this->assertErrorWithMessage($result->getErrors(), 'Every source file should have a namespace');

        // But also make sure parsing continues!
        $this->assertErrorWithMessage($result->getErrors(), 'Multiple uses in one statement is discouraged');
    }

    public function testMultiPartUseWarning()
    {
        $result = $this->bugfree->parse('test', '<?php namespace foo; use asdf, hjkl;', $this->resolver);
        $this->assertErrorWithMessage($result->getErrors(), 'Multiple uses in one statement is discouraged');
    }

    public function testUnresolvingUseStatement()
    {
        Phake::when($this->resolver)->isValid('\asdf')->thenReturn(false);

        $result = $this->bugfree->parse('test', '<?php namespace foo; use asdf, hjkl;', $this->resolver);

        $this->assertErrorWithMessage($result->getErrors(), "Use '\\asdf' could not be resolved");
    }

    public function testUnusedUse()
    {
        $result = $this->bugfree->parse('test', '<?php namespace foo; use asdf;', $this->resolver);

        $this->assertErrorWithMessage($result->getErrors(), "Use 'asdf' is not being used");
    }

    public function testUseInSameNamespace()
    {
        $src = '<?php namespace foo\bar;
            use foo\bar\Blah;
            $blah = new Blah();
        ';
        $result = $this->bugfree->parse('test', $src, $this->resolver);

        $this->assertErrorWithMessage($result->getErrors(), "Use 'foo\bar\Blah' is automatically included as it is in the same namespace");
    }

    public function testDuplicateAlias()
    {
        $src = '<?php namespace foo;
            use asdf;
            use foo\asdf;
        ';
        $result = $this->bugfree->parse('test', $src, $this->resolver);

        $this->assertErrorWithMessage($result->getErrors(), "Alias 'asdf' is already in use on line 2");
    }

    public function testEmailsAreIgnored()
    {
        Phake::verifyNoFurtherInteraction($this->resolver);
        $src = '<?php namespace bar;
            /**
             * @author foo <bar@baz.com>
             */
            function foo() {}
        ';
        $result = $this->bugfree->parse('test', $src, $this->resolver);

        $this->assertEquals([], $result->getErrors());

    }

    public function testMultipleNamespaces()
    {
        Phake::when($this->resolver)->isValid('foo\Foo')->thenReturn(false);
        Phake::when($this->resolver)->isValid('baz\Baz')->thenReturn(false);

        $src = "<?php
        namespace foo {
            function test(Foo \$f) {}
        }

        namespace baz {
            function test(Baz \$f) {}
        }
        ";

        $result = $this->bugfree->parse('test', $src, $this->resolver);

        $this->assertErrorWithMessage($result->getErrors(), "Type 'foo\\Foo' could not be resolved");
        $this->assertErrorWithMessage($result->getErrors(), "Type 'baz\\Baz' could not be resolved");
    }

    public function useProvider()
    {
        return [
            [[
                'invalid'   => ['testns\DoesNotExist'],
                'type'      => 'DoesNotExist',
                'errors'    => ["Type 'testns\\DoesNotExist' could not be resolved"],
            ]],
            [[
                'invalid'   => [],
                'type'      => 'DoesNotExist',
                'errors'    => [],
            ]],
            [[
                'invalid'   => ['testns\DoesNotExist'],
                'type'      => '\testns\DoesNotExist',
                'errors'    => [
                    "Type 'testns\\DoesNotExist' could not be resolved",
                    'Use of qualified type names is discouraged.'
                ],
            ]],
            [[
                'invalid'   => [],
                'type'      => 'testns\DoesNotExist',
                'errors'    => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => ['foo\bar\baz\DoesNotExist'],
                'type'      => 'baz\DoesNotExist',
                'errors'    => [
                    "Type 'foo\\bar\\baz\\DoesNotExist' could not be resolved",
                    'Use of qualified type names is discouraged.'
                ],
            ]],
            [[
                'invalid'   => [],
                'type'      => 'baz\DoesNotExist',
                'errors'    => ['Use of qualified type names is discouraged.'],
            ]],
            [[
                'invalid'   => ['testns\boo\DoesNotExist'],
                'type'      => 'boo\DoesNotExist',
                'errors'    => [
                    "Type 'testns\\boo\\DoesNotExist' could not be resolved",
                    'Use of qualified type names is discouraged.'
                ],
            ]],
            [[
                'invalid'   => [],
                'type'      => 'Thing',
                'errors'    => [],
            ]],
            [[
                'invalid'   => ['Thing'],
                'type'      => '\Thing',
                'errors'    => ["Type 'Thing' could not be resolved"],
            ]],
            [[
                'invalid'   => [],
                'type'      => '\Thing',
                'errors'    => [],
            ]],
        ];
    }

    private function verifySource($src, $options)
    {
        foreach ($options['invalid'] as $invalid) {
            Phake::when($this->resolver)->isValid($invalid)->thenReturn(false);
        }

        $result = $this->bugfree->parse('test', $src, $this->resolver);

        foreach ($options['errors'] as $error) {
            $this->assertErrorWithMessage($result->getErrors(), $error);
        }
        $this->assertEquals(
            count($options['errors']),
            count($result->getErrors()),
            print_r($result->getErrors(), true)
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
    public function testThrow($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        throw new {$options['type']}();
        ";
        $this->verifySource($src, $options);
    }

    /**
     * @dataProvider useProvider
     */
    public function testThrowStatic($options)
    {
        $src = "<?php namespace testns;
        use foo\\bar\\baz;
        use foo\\Thing;

        function doNotWarnAboutUnused(baz \$a, Thing \$b) {}

        class foo {
            protected function bar() {
                throw {$options['type']}::foobar();
            }
        }

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

        $this->assertEquals(0, count($result->getErrors()), print_r($result->getErrors(), true));
    }

    /**
     * @dataProvider builtinTypeProvider
     */
    public function testClassSupport($builtinType)
    {
        Phake::when($this->resolver)->isValid("\\foo\\$builtinType")->thenReturn(false);

        $src = "<?php namespace foo;
            /**
             * @param $builtinType \$a ggg
             */
            function foo(\$a) { \$classname = \$a::class; }
        ";

        $result = $this->bugfree->parse('test', $src, $this->resolver);

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

        $this->assertEquals(0, count($result->getErrors()), print_r($result->getErrors(), true));
    }
}
