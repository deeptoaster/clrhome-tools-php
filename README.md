# clrhome-tools-php

A set of utilities for manipulating Texas Instruments graphing calculator
variables and files, implemented in PHP

## Installation

clrhome-tools-php requires PHP 5.5 or above.

The easiest way to use clrhome-tools-php is simply to clone the repository
(such as with a [git submodule](https://git-scm.com/docs/git-submodule)) and
[include](https://www.php.net/manual/en/function.include.php) or
[require](https://www.php.net/manual/en/function.require.php) it in your code.
No fancy package managers here.

## Quick Start: Lists

    <?php
    include(__DIR__ . '/List.class.php');

    $list = new \ClrHome\List();
    $list->setElements(array(1, array(2, 3), '4-5i', array(6)));
    $list[] = 7;
    $list[] = array(8, 9);
    $list[] = '10i-11';
    $list[] = array(0);
    $list[7] = 12;
    $list->setName('L1');
    $list->toFile('L1.8xl');
    ?>

## Quick Start: Matrices

    <?php
    include(__DIR__ . '/Matrix.class.php');

    $matrix = new \ClrHome\Matrix();
    $matrix->setElements(array(array(1, 2, 3), array(4, 5, 6)));
    $matrix[]
    $matrix['3,2'] = 12;
    $matrix->setName('[A]');
    $matrix->toFile('A.8xm');
    ?>

## Quick Start: Numbers

    <?php
    include(__DIR__ . '/Number.class.php');

    $number = new \ClrHome\Number();
    $number->setAsEpression('4-5i');
    $number->setReal(cos(M_PI / 4));
    $number->setImaginary(sin(M_PI / 4));
    $number->setName('theta');
    $number->toFile('theta.8xc');
    ?>

A simple expression engine supporting real and complex numbers is built into
the library. To interface with it, use the `getAsExpression` and `set` methods.

## Quick Start: Pictures

    <?php
    include(__DIR__ . '/Picture.class.php');

    $picture = new \ClrHome\Picture();
    $picture->setRowCount(64);
    $picture['31,47'] = true;
    $picture->setName('Pic1');
    $picture->toFile('Pic1.8xi');
    ?>

## Quick Start: Programs

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
