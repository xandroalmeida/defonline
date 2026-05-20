<?php

/*
|--------------------------------------------------------------------------
| Test Case (Spike)
|--------------------------------------------------------------------------
| Faz Pest usar a TestCase do Laravel (com bootstrap completo) tanto em
| tests/Feature quanto em tests/Unit. Para suíte de produção, considerar
| usar TestCase só em Feature/ e manter Unit/ rápido com PHPUnit base.
*/

pest()->extend(Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function something() {}
