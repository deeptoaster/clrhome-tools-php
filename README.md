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


Quick Start: Lists
------------------

    <?php
    include(__DIR__ . '/ListVariable.class.php');

    $list = new \ClrHome\ListVariable();
    $list->setElements(array(4, array(5, 6), 3));
    $list[] = 1;
    $list[] = array(2, 3);
    $list[] = array(0);
    $list->setName('L1');
    $list->toFile('L1.8xl');
    ?>


Quick Start: Matrices
------------------

    <?php
    include(__DIR__ . '/Matrix.class.php');

    $matrix = new \ClrHome\Matrix();
    $matrix->setElements(array(array(1, 2, 3), array(4, 5, 6)));
    $matrix['3,2'] = 12;
    $matrix->setName('[A]');
    $matrix->toFile('A.8xl');
    ?>


Quick Start: Numbers
------------------

    <?php
    include(__DIR__ . '/Number.class.php');

    $number = new \ClrHome\Number();
    $number->setReal(cos(M_PI / 4));
    $number->setImaginary(sin(M_PI / 4));
    $number->setName('theta');
    $number->toFile('theta.8xl');
    ?>


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
