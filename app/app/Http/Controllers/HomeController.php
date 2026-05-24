<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Apenas o logout autenticado — a `/home` virou o componente Livewire
 * `MinhasEmpresas` na STORY-016.
 */
final class HomeController
{
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // STORY-019 CA-19 — flash de confirmação após logout para o usuário ver
        // que a ação aconteceu (sem o flash, o redirect para /login parece "perdi a sessão").
        return redirect('/login')
            ->with('logout_sucesso', 'Você saiu da conta com sucesso.');
    }
}
