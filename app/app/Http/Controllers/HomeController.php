<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Home autenticada do Usuário (STORY-011 CA-4).
 *
 * Versão mínima — entrega "Olá, {primeiro_nome}" + logout. A versão com
 * "Minhas Empresas" entra na STORY-016.
 */
final class HomeController
{
    public function show(): View
    {
        /** @var Usuario $usuario */
        $usuario = Auth::user();

        return view('home', ['usuario' => $usuario]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
