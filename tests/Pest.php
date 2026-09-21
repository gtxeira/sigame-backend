<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you receive receives the test case as `$this`. This allows
| you to extend the base test case, which is `Tests\TestCase` in a Symfony
| project. You can also add traits or use helper functions here.
|
*/

uses(Tests\TestCase::class)->in('Unit', 'Feature');
