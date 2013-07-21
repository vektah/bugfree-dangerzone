<?php

namespace bugfree\annotation;

use Doctrine\Common\Annotations\DocLexer;

/**
 * Doctrine's lexer has awful tokens. This wraps it a little.
 */
class DoctrineAnnotationToken
{
    private $value;
    private $type;
    private $position;
    private $line;

    private static $nonDoctrineTags = '/^
        abstract|access|author|category|copyright|deprecated|example|final|filesource|global|ignore|internal|license|
        link|name|package|see|since|static|staticvar|subpackage|todo|tutorial|uses|version|var|param|return|method|
        property|dataProvider|throws|inheritdoc|expectedException|expectedExceptionMessage|api
    $/ix';

    private static $nonDoctrineTagsThatSpecifyType = '/^var|param|return|method|property|expectedException$/i';

    private static $typeNames = [
        DocLexer::T_NONE => 'NONE',
        DocLexer::T_INTEGER => 'INTEGER',
        DocLexer::T_STRING => 'STRING',
        DocLexer::T_FLOAT => 'FLOAT',
        DocLexer::T_IDENTIFIER => 'IDENTIFIER',
        DocLexer::T_AT => 'AT',
        DocLexer::T_CLOSE_CURLY_BRACES => 'CLOSE_CURLY_BRACES',
        DocLexer::T_CLOSE_PARENTHESIS => 'CLOSE_PARENTHESIS',
        DocLexer::T_COMMA => 'COMMA',
        DocLexer::T_EQUALS => 'EQUALS',
        DocLexer::T_FALSE => 'FALSE',
        DocLexer::T_NAMESPACE_SEPARATOR => 'NAMESPACE_SEPARATOR',
        DocLexer::T_OPEN_CURLY_BRACES => 'OPEN_CURLY_BRACES',
        DocLexer::T_OPEN_PARENTHESIS => 'OPEN_PARENTHESIS',
        DocLexer::T_TRUE => 'TRUE',
        DocLexer::T_NULL => 'NULL',
        DocLexer::T_COLON => 'COLON',
    ];

    /**
     * @param array $token the raw doctrine token
     * @param string $docblock the raw docblock that this token was found in.
     */
    public function __construct(array $token, $docblock)
    {
        $this->value = $token['value'];
        $this->position = $token['position'];
        $this->type = $token['type'];
        $this->line = $this->calculateLine($this->position, $docblock);
    }

    /**
     * Returns the line that this character offset is on.
     *
     * @param int $position
     * @param string $docblock
     * @return int
     */
    private function calculateLine($position, $docblock)
    {
        return preg_match_all('/\n|\r\n|\r/', substr($docblock, 0, $position));
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    public function getLine()
    {
        return $this->line;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string the name of this type
     */
    public function getTypeName()
    {
        if (isset(self::$typeNames[$this->type])) {
            return self::$typeNames[$this->type];
        } else {
            return 'UNKNOWN';
        }
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string a formatted string for this token.
     */
    public function __toString()
    {
        return "{$this->getTypeName()}('{$this->value}')";
    }

    /**
     * @return boolean matches non doctrine annotations like atvar and atparam
     */
    public function isNonDoctrineAnnotation()
    {
        return preg_match(self::$nonDoctrineTags, $this->value);
    }

    /**
     * @return boolean
     */
    public function isFollowedByType()
    {
        return preg_match(self::$nonDoctrineTagsThatSpecifyType, $this->value);
    }
}
