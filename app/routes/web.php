<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Livewire\Cadastro;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', [HealthController::class, 'health'])->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');

Route::get('/cadastro', Cadastro::class)->name('cadastro');
