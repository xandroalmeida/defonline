<?php

declare(strict_types=1);

use App\Jobs\HelloWorldEmail;
use App\Livewire\HelloWorld;
use App\Models\EventoProduto;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('renders the hello world page with version and OK status', function () {
    Livewire::test(HelloWorld::class)
        ->assertSee('hello')
        ->assertSee('OK');
});

it('emits hello_world_visualizado event once per session', function () {
    Livewire::test(HelloWorld::class);

    expect(EventoProduto::where('nome_evento', 'hello_world_visualizado')->count())->toBe(1);

    // Second mount in same session must not emit again.
    Livewire::test(HelloWorld::class);
    expect(EventoProduto::where('nome_evento', 'hello_world_visualizado')->count())->toBe(1);
});

it('dispatches HelloWorldEmail when the button is clicked', function () {
    Queue::fake();

    Livewire::test(HelloWorld::class)
        ->call('dispararEmail')
        ->assertSee('Job enfileirado');

    Queue::assertPushed(HelloWorldEmail::class);
});
