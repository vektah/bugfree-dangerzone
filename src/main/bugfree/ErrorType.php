<?php

namespace bugfree;


class ErrorType
{
    const UNABLE_TO_RESOLVE_TYPE                = 'unableToResolveType';
    const UNABLE_TO_RESOLVE_TYPE_IN_COMMENT     = 'unableToResolveTypeInComment';
    const UNABLE_TO_RESOLVE_USE                 = 'unableToResolveUse';
    const USE_OF_UNQUALIFIED_TYPE               = 'useOfUnqualifiedType';
    const USE_OF_UNQUALIFIED_TYPE_IN_COMMENT    = 'useOfUnqualifiedTypeInComment';
    const DUPLICATE_ALIAS                       = 'duplicateAlias';
    const MALFORMED_USE                         = 'malformedUse';
    const MULTI_STATEMENT_USE                   = 'multiStatementUse';
    const MISSING_NAMESPACE                     = 'missingNamespace';
    const UNUSED_USE                            = 'unusedUse';
    const DISORGANIZED_USES                     = 'disorganizedUses';
    const COMMON_TYPOS                          = 'commonTypos';
    const ACCESS_LEVEL                          = 'accessLevel';
    const METHOD_EXISTS                         = 'methodExists';

    const WARNING = 'warning';
    const ERROR = 'error';
    const SUPPRESS = 'suppress';
}
