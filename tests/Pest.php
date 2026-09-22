<?php

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

uses(Tests\TestCase::class)->in('Unit', 'Feature', 'Api');
