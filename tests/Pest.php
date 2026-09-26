<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you receive receives the test case as `$this`. This allows
| you to extend the base test case, which is `Tests\TestCase` (extending
| Symfony's WebTestCase). You can also add traits or use helper functions
| here.
|
*/

use Tests\TestCase;

uses(TestCase::class)
   ->in('Unit', 'Integration');

pest()
    ->group('unit')
    ->in('Unit');

pest()
    ->group('integration')
    ->in('Integration');
