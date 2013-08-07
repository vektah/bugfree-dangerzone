<?php

namespace bugfree\annotation;


class DocblockTest extends \PHPUnit_Framework_TestCase
{
    public function docblockProvider()
    {
        return [
            ["/** @param string \$foo a foo */", [['type' => 'string', 'line' => 0]]],

            ["/** @param \$foo a foo */", [['type' => 'a', 'line' => 0]]],

            ["/**
              * @param string \$foo a foo
              * @param turtle \$t a turtle
              */",
              [['type' => 'string', 'line' => 1],
               ['type' => 'turtle', 'line' => 2]]
            ],

            ['/** @return string a foo */', [['type' => 'string', 'line' => 0]]],

            ['/** @var string a foo */', [['type' => 'string', 'line' => 0]]],

            ['/** @method string foobar(Asdf $foo) */', [['type' => 'string', 'line' => 0]]],

            ['/** @param string $foo a foo */', [['type' => 'string', 'line' => 0]]],

            [
            "/**
              * Foo bar baz This is some commentary.
              *
              * @param string \$foo a foo
              * @param turtle \$t a turtle
              */",
                [
                    ['type' => 'string', 'line' => 3],
                    ['type' => 'turtle', 'line' => 4]
                ]
            ],

            ['/**
               * @Entity
               * @InheritanceType("JOINED")
               * @DiscriminatorColumn(name="discr", type="string")
               * @DiscriminatorMap({"person" = "Person", "employee" = "Employee"})
               */',
                [
                    ['type' => 'Entity', 'line' => 1],
                    ['type' => 'InheritanceType', 'line' => 2],
                    ['type' => 'DiscriminatorColumn', 'line' => 3],
                    ['type' => 'DiscriminatorMap', 'line' => 4]
                ]
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
                [
                    ['type' => 'ManyToMany', 'line' => 3],
                    ['type' => 'JoinTable', 'line' => 4],
                    ['type' => 'JoinColumn', 'line' => 5],
                    ['type' => 'JoinColumn', 'line' => 6],
                ]
            ],
            [
               '/**
                 * @Foo(PHP_EOL)
                 * @Bar(Bar::FOO)
                 * @Foo({SomeClass1::FOO, SomeClass3::BAR})
                 * @Bar({SomeClass2::FOO_KEY = SomeClass4::BAR_VALUE})
                 */',
                [
                    ['type' => 'Foo',       'line' => 1],
                    ['type' => 'Bar',       'line' => 2],
                    ['type' => 'Bar',       'line' => 2],
                    ['type' => 'Foo',       'line' => 3],
                    ['type' => 'SomeClass1','line' => 3],
                    ['type' => 'SomeClass3','line' => 3],
                    ['type' => 'Bar',       'line' => 4],
                    ['type' => 'SomeClass2','line' => 4],
                    ['type' => 'SomeClass4','line' => 4],
                ]
            ],
            [
                '**
                 *(PHP 5 &gt;= 5.0.0)<br/>
                 * Retrieve an external iterator
                 *
                 * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
                 * @return Traversable An instance of an object implementing <b>Iterator</b> or
                 * <b>Traversable</b>
                 */',
                [
                    ['type' => 'Traversable', 'line' => 5]
                ]
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
