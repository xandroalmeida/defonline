<?php

declare(strict_types=1);

use App\Http\Controllers\EmailConfirmacaoController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Livewire\Cadastro;
use App\Livewire\Login;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', [HealthController::class, 'health'])->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');

Route::get('/cadastro', Cadastro::class)->name('cadastro');
Route::get('/login', Login::class)->middleware('throttle:login')->name('login');

Route::view('/termos/termo-adesao', 'legal.termo-adesao-v1-placeholder')->name('termos.termo-adesao');
Route::view('/termos/politica-privacidade', 'legal.politica-privacidade-v1-placeholder')->name('termos.politica-privacidade');

// STORY-013 — confirmação de email + reenvio.
Route::get('/email/confirmar/{usuario}', [EmailConfirmacaoController::class, 'confirmar'])
    ->middleware('signed')
    ->name('email.confirmar');
Route::view('/email/confirmado', 'email.confirmado')->name('email.confirmado');
Route::view('/email/confirmar-erro', 'email.confirmar-erro')->name('email.confirmar-erro');
Route::post('/email/reenviar-confirmacao', [EmailConfirmacaoController::class, 'reenviar'])
    ->name('email.reenviar');

Route::middleware('auth')->group(function () {
    Route::get('/home', [HomeController::class, 'show'])->name('home');
    Route::post('/logout', [HomeController::class, 'logout'])->name('logout');
});
