<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Support\Personificacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * STORY-066 / PDR-012 — entrar e sair do "Logar como". Alvo que não pode ser
 * assumido recebe 404, como o resto do backoffice (IDR-009).
 */
final class PersonificacaoController
{
    public function iniciar(Request $request, Usuario $usuario): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof Usuario && $usuario->podeSerPersonificadoPor($admin), 404);

        Personificacao::iniciar($admin, $usuario);

        return redirect()->route('home');
    }

    public function encerrar(): RedirectResponse
    {
        return Personificacao::voltarAoAdmin()
            ? redirect()->route('backoffice.usuarios')
            : redirect()->route('home');
    }
}
