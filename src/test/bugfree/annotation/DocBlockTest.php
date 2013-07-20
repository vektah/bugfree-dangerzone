<?php

namespace bugfree\annotation;


class DocblockTest extends \PHPUnit_Framework_TestCase
{
    public function docblockProvider()
    {
        return [
            ["/** @param string \$foo a foo */", ['string']],

            ["/** @param \$foo a foo */", ['$foo']],    // Even though type is missing we need to display something.

            ["/**
              * @param string \$foo a foo
              * @param turtle \$t a turtle
              */", ['string', 'turtle']
            ],

            ['/** @return string a foo */', ['string']],

            ['/** @var string a foo */', ['string']],

            ['/** @method string foobar(Asdf $foo) */', ['string']],

            ['/** @param string $foo a foo */', ['string']],

            [
            "/**
              * Foo bar baz This is some commentary.
              *
              * @param string \$foo a foo
              * @param turtle \$t a turtle
              */",
                ['string', 'turtle']
            ],

            ['/**
               * @Entity
               * @InheritanceType("JOINED")
               * @DiscriminatorColumn(name="discr", type="string")
               * @DiscriminatorMap({"person" = "Person", "employee" = "Employee"})
               */',
                ['Entity', 'InheritanceType', 'DiscriminatorColumn', 'DiscriminatorMap']
            ],

            [
               '/**
                 * Owning Side
                 *
                 * @ManyToMany(targetEntity="Group", inversedBy="features")
                 * @JoinTable(name="user_groups",
                 *      joinColumns={@JoinColumn(name="user_id", referencedColumnName="id")},
                 *      inverseJoinColumns={@JoinColumn(name="group_id", referencedColumnName="id")}
                 *      )
                 */',
                ['ManyToMany', 'JoinTable', 'JoinColumn']
            ],
            [
               '/**
                 * @Foo(PHP_EOL)
                 * @Bar(Bar::FOO)
                 * @Foo({SomeClass1::FOO, SomeClass3::BAR})
                 * @Bar({SomeClass2::FOO_KEY = SomeClass4::BAR_VALUE})
                 */',
                ['Foo', 'Bar', 'SomeClass1', 'SomeClass3', 'SomeClass2', 'SomeClass4']
            ]
        ];
    }

    /**
     * @dataProvider docblockProvider
     */
    public function testDocblocks($docblock, $types)
    {
        $docblock = new Docblock($docblock);

        $this->assertEquals($types, $docblock->getTypes());
    }
}
