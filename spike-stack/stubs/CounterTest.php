<?php

use App\Livewire\Counter;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('renders counter at zero by default', function () {
    Livewire::test(Counter::class)
        ->assertSet('count', 0)
        ->assertSee('Contador Livewire');
});

it('increments the counter when the button is clicked', function () {
    Livewire::test(Counter::class)
        ->call('increment')
        ->assertSet('count', 1)
        ->call('increment')
        ->assertSet('count', 2);
});

it('connects to PostgreSQL and returns now()', function () {
    $row = DB::select('select now() as now')[0];
    expect($row->now)->not->toBeNull();
    expect(config('database.default'))->toBe('pgsql');
});
