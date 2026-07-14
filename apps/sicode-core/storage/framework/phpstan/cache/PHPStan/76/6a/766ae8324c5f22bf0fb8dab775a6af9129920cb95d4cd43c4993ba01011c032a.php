<?php declare(strict_types = 1);

// odsl-/var/www/html/app/CoreAudit/RecordCoreAuditEvent.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\CoreAudit\RecordCoreAuditEvent
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.4.23-5cd835fbd0d20fd703813ec0dd990a6c0f303f24fbf78ad8c11c1782045e40c5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'filename' => '/var/www/html/app/CoreAudit/RecordCoreAuditEvent.php',
      ),
    ),
    'namespace' => 'App\\CoreAudit',
    'name' => 'App\\CoreAudit\\RecordCoreAuditEvent',
    'shortName' => 'RecordCoreAuditEvent',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 116,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'MAX_REASON_BYTES' => 
      array (
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'name' => 'MAX_REASON_BYTES',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '500',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 13,
            'startTokenPos' => 48,
            'startFilePos' => 216,
            'endTokenPos' => 48,
            'endFilePos' => 218,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 5,
        'endColumn' => 45,
      ),
      'MAX_DETAILS_BYTES' => 
      array (
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'name' => 'MAX_DETAILS_BYTES',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '8192',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 61,
            'startFilePos' => 264,
            'endTokenPos' => 61,
            'endFilePos' => 267,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'SENSITIVE_DETAIL_KEY_FRAGMENTS' => 
      array (
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'name' => 'SENSITIVE_DETAIL_KEY_FRAGMENTS',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '[\'authorization\', \'client_secret\', \'cookie\', \'password\', \'secret\', \'token\']',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 24,
            'startTokenPos' => 74,
            'startFilePos' => 328,
            'endTokenPos' => 94,
            'endFilePos' => 457,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      '__invoke' => 
      array (
        'name' => '__invoke',
        'parameters' => 
        array (
          'record' => 
          array (
            'name' => 'record',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\CoreAudit\\CoreAuditRecord',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 30,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\CoreAuditEvent',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 26,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\CoreAudit',
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'currentClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'aliasName' => NULL,
      ),
      'validateActor' => 
      array (
        'name' => 'validateActor',
        'parameters' => 
        array (
          'record' => 
          array (
            'name' => 'record',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\CoreAudit\\CoreAuditRecord',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 47,
            'endLine' => 47,
            'startColumn' => 36,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 47,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\CoreAudit',
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'currentClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'aliasName' => NULL,
      ),
      'validateReason' => 
      array (
        'name' => 'validateReason',
        'parameters' => 
        array (
          'record' => 
          array (
            'name' => 'record',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\CoreAudit\\CoreAuditRecord',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 37,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 58,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\CoreAudit',
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'currentClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'aliasName' => NULL,
      ),
      'validateDetails' => 
      array (
        'name' => 'validateDetails',
        'parameters' => 
        array (
          'record' => 
          array (
            'name' => 'record',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\CoreAudit\\CoreAuditRecord',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 38,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 65,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\CoreAudit',
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'currentClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'aliasName' => NULL,
      ),
      'assertNoSensitiveDetailKeys' => 
      array (
        'name' => 'assertNoSensitiveDetailKeys',
        'parameters' => 
        array (
          'details' => 
          array (
            'name' => 'details',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 91,
            'endLine' => 91,
            'startColumn' => 50,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<array-key, mixed>  $details
 */',
        'startLine' => 91,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\CoreAudit',
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'currentClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'aliasName' => NULL,
      ),
      'isSensitiveDetailKey' => 
      array (
        'name' => 'isSensitiveDetailKey',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 104,
            'endLine' => 104,
            'startColumn' => 43,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 104,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\CoreAudit',
        'declaringClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'implementingClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'currentClassName' => 'App\\CoreAudit\\RecordCoreAuditEvent',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));