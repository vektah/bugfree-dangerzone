<?php

namespace bugfree\config;


use bugfree\ErrorType;

class EmitLevel
{
    public $unableToResolveType = ErrorType::ERROR;
    public $unableToResolveTypeInComment = ErrorType::WARNING;
    public $unableToResolveUse = ErrorType::ERROR;
    public $useOfUnqualifiedType = ErrorType::WARNING;
    public $useOfUnqualifiedTypeInComment = ErrorType::WARNING;
    public $duplicateAlias = ErrorType::ERROR;
    public $malformedUse = ErrorType::ERROR;
    public $multiStatementUse = ErrorType::WARNING;
    public $missingNamespace = ErrorType::ERROR;
    public $unusedUse = ErrorType::WARNING;

    public function __construct(array $values=[])
    {
        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }
}
