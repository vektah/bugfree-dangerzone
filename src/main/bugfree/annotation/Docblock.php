<?php

namespace bugfree\annotation;

use Doctrine\Common\Annotations\DocLexer;

/**
 * Use Doctrine annotation lexer to work out type information. We don't really care about anything other then
 * the types at this point.
 *
 * Note that this will generate a lot of false positives so failing to resolve a type here probably shouldn't generate
 * an error.
 *
 * Example usage:
 * $reflector = new \ReflectionFunction('some_function');
 * $doc = new Docblock($reflector->getDocComment());
 * var_dump($doc->getTypes());
 */
class Docblock
{
    private $types = [];

    /**
     * @param string $docblock a annotation
     */
    public function __construct($docblock)
    {
        $lexer = new DocLexer();
        $lexer->setInput($docblock);
        $lexer->moveNext();

        while ($token = $lexer->lookahead) {
            $token = new DoctrineAnnotationToken($token);
            if ($token->getType() == DocLexer::T_AT && $lexer->peek()) {
                $this->parseAnnotation($lexer);
            }

            if ($token->getType() == DocLexer::T_OPEN_PARENTHESIS) {
                $this->parseParameterList($lexer, DocLexer::T_CLOSE_PARENTHESIS);
            }

            if ($token->getType() == DocLexer::T_OPEN_CURLY_BRACES) {
                $this->parseParameterList($lexer, DocLexer::T_CLOSE_CURLY_BRACES);
            }

            $lexer->moveNext();
        }
    }

    private function parseAnnotation(DocLexer $lexer)
    {
        $lexer->moveNext();
        $token = new DoctrineAnnotationToken($token = $lexer->lookahead);
        if ($token->isNonDoctrineAnnotation()) {
            if ($token->isFollowedByType()) {
                if ($type = $lexer->peek()) {
                    // Often there are badly formed @param $foo with no type information. Make sure that we give a
                    // reasonable 'type' value for these so the error is understandable.
                    if ($type['value'] == '$') {
                        if ($next = $lexer->peek()) {
                            $typeString = "\${$next['value']}";
                            $this->types[$typeString] = $typeString;
                        }


                    } else {
                        $this->types[$type['value']] = $type['value'];
                    }

                }
            }
        } else {
            $this->types[$token->getValue()] = $token->getValue();
        }
    }

    /**
     * Parses a second of docblock between brackets
     *
     * @param DocLexer $lexer
     * @param int $endTokenType
     */
    public function parseParameterList(DocLexer $lexer, $endTokenType)
    {
        $lexer->moveNext();

        while ($token = $lexer->lookahead) {
            $token = new DoctrineAnnotationToken($token);

            if ($token->getType() == $endTokenType) {
                return;
            }

            // Search for the usual stuff...
            if ($token->getType() == DocLexer::T_AT && $lexer->peek()) {
                $this->parseAnnotation($lexer);
            }

            if ($token->getType() == DocLexer::T_OPEN_PARENTHESIS) {
                $this->parseParameterList($lexer, DocLexer::T_CLOSE_PARENTHESIS);
            }

            if ($token->getType() == DocLexer::T_OPEN_CURLY_BRACES) {
                $this->parseParameterList($lexer, DocLexer::T_CLOSE_CURLY_BRACES);
            }

            // But in here naked identifiers with :: in them are special!
            if ($token->getType() == DocLexer::T_IDENTIFIER) {
                if (preg_match('/(?P<type>.*)::(.*)/', $token->getValue(), $matches)) {
                    $this->types[$matches['type']] = $matches['type'];
                }
            }

            $lexer->moveNext();
        }
    }

    /**
     * @return string[] a list of all types seen in this annotation.
     */
    public function getTypes()
    {
        return array_values($this->types);
    }
}
