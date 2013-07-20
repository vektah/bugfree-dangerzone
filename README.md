bugfree-dangerzone [![Build Status](https://travis-ci.org/Vektah/bugfree-dangerzone.png?branch=master)](https://travis-ci.org/Vektah/bugfree-dangerzone)
==================

Bugfree Dnagerzone is a PHP namespace validator written in PHP. It uses your autoloader to verify that:
 - all of the use statements are valid
 - that all exception catch blocks and type hints have a valid type
 - all doc block types are valid (eg @param Foo $foo)
 - all Doctrine annotations can be resolved (eg @FOobar()) 
 - Finally that all use statements are actually used.
 
 
