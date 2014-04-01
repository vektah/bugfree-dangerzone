bugfree-dangerzone [![Build Status](https://travis-ci.org/Vektah/bugfree-dangerzone.png?branch=master)](https://travis-ci.org/Vektah/bugfree-dangerzone)
==================

Bugfree Dangerzone is a PHP namespace validator written in PHP. It uses your autoloader to verify that:
 - all of the use statements are valid
 - that all exception catch blocks and type hints have a valid type
 - all doc block types are valid (eg @param Foo $foo)
 - all Doctrine annotations can be resolved (eg @FOobar()) 
 - access level validation for constructors and static methods
 - Finally that all use statements are actually used.
 
 
Getting Started
---------------

Add "vektah/bugfree-dangerzone" to your projects composer.json, it should look something like this:
```
    "require-dev": {
        "vektah/bugfree-dangerzone": "0.2.0"
    },
```

Then of course: 
```
composer.phar update
```
to update your dependencies


then run 
```
./vendor/bin/bugfree lint src
```


If your project has its own autoloader you can use it instead:
```
./vendor/bin/bugfree lint --bootstrap yourautoloader.php src
```

XML Output
----------
For use in CI tools like Jenkins some pretty test count output is just not good enough!

To generate machine readable output:
```
./vendor/bin/bugfree lint src --junitXml junit_results.xml --checkstyleXml checkstyle_results.xml
```



Configuration
-------------

Bugfree Dangerzone is rather picky out of the box, but its easy to decrease its verbosity.

from your projects base directory run:
```
./vendor/bin/bugfree generateConfig
```

which will build a config file bugfree.json in your current directory:
```
{
    "emitLevel": {
        "unableToResolveType": "error",
        "unableToResolveTypeInComment": "error",
        "unableToResolveUse": "error",
        "useOfUnqualifiedType": "warning",
        "useOfUnqualifiedTypeInComment": "warning",
        "duplicateAlias": "error",
        "malformedUse": "error",
        "multiStatementUse": "warning",
        "missingNamespace": "error",
        "unusedUse": "warning"
    }
}
```

each of these warning types can be either error, warning, or suppress. For example to
ignore all messages about missing namespaces then just change 
```
"missingNamespace": "error",
``` 
to
```
"missingNamespace": "suppress",
```

If the configuration gets updated in the future to have more options then running generateConfig again will keep your
current settings and add any new defaults.
