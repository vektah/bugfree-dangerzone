<?php

namespace bugfree\annotation;
use Doctrine\Common\Annotations\DocLexer;

require_once(__DIR__ . "/../../../../vendor/autoload.php");

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
    private static $common_tags_regex = '/
        abstract|access|author|category|copyright|deprecated|example|final|filesource|global|ignore|internal|license|
        link|method|name|package|param|property|return|see|since|static|staticvar|subpackage|todo|tutorial|uses|var|
        version
    /ix';

    /**
     * @param string $docblock a docblock
     */
    public function __construct($docblock)
    {
        $lexer = new DocLexer();
        $lexer->setInput($docblock);
        $lastType = DocLexer::T_NONE;

        while ($token = $lexer->peek()) {
            $token = new DoctrineAnnotationToken($token);

            if ($token->getType() == DocLexer::T_IDENTIFIER) {
                // ignore common phpdocumentor tags.
                if ($lastType == DocLexer::T_AT && preg_match(self::$common_tags_regex, $token->getValue())) {
                    while ($next = $lexer->peek()) {
                        if ($next['type'] == DocLexer::T_AT) {
                            break;
                        }
                    }
                } else {
                    $this->types[$token->getValue()] = $token->getValue();
                }
            }

            // Ignore the identifier just before an equals. left hand side should never be a type.
            if ($token->getType() == DocLexer::T_EQUALS) {
                array_pop($this->types);
            }

            $lastType = $token->getType();
        }
    }

    /**
     * @return string[] a list of all types seen in this docblock.
     */
    public function getTypes()
    {
        return array_values($this->types);
    }
}
