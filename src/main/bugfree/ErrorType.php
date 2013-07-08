<?php

namespace bugfree;


class ErrorType
{
    const UNABLE_TO_RESOLVE_TYPE        = 'unableToResolveType';
    const UNABLE_TO_RESOLVE_USE         = 'unableToResolveUse';
    const USE_OF_UNQUALIFIED_TYPE       = 'useOfUnqualifiedType';
    const DUPLICATE_ALIAS               = 'duplicateAlias';
    const MALFORMED_USE                 = 'malformedUse';
    const MULTI_STATEMENT_USE           = 'multiStatementUse';
    const MISSING_NAMESPACE             = 'missingNamespace';
    const UNUSED_USE                    = 'unusedUse';

    const WARNING = 'warning';
    const ERROR = 'error';
    const SUPPRESS = 'suppress';
}