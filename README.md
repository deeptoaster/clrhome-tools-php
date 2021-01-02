clrhome-tools-php
=================

A set of utilities for manipulating Texas Instruments graphing calculator
variables and files, implemented in PHP


Installation
------------

The easiest way to use clrhome-tools-php is simply to clone the repository
(such as with a [git submodule](https://git-scm.com/docs/git-submodule)) and
[include](https://www.php.net/manual/en/function.include.php) or
[require](https://www.php.net/manual/en/function.require.php) it in your code.
No fancy package managers here.


Quick Start: Programs
---------------------

    <?php
    include(__DIR__ . '/Program.class.php');

    $program = new \ClrHome\Program();
    $program->setBodyAsChars('Disp "Hello, World!"');
    $program->setName('HELLO');
    $program->toFile('HELLO.8xp');
    ?>

A robust tokenizer based on the [Catalog](https://clrhome.org/catalog/) is
built into the library. To interface with it, use the `getBodyAsChars` and
`setBodyAsChars` methods.
