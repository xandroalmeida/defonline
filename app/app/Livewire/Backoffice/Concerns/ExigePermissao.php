<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice\Concerns;

use App\Models\Usuario;
use App\Support\PermissaoAdmin;
use Illuminate\Support\Facades\Auth;

/**
 * STORY-067 — o middleware da rota só protege a abertura da tela. As ações do
 * Livewire chegam por outra rota, então a permissão é conferida em toda requisição.
 */
trait ExigePermissao
{
    abstract protected function permissaoExigida(): PermissaoAdmin;

    public function boot(): void
    {
        $usuario = Auth::user();

        abort_unless($usuario instanceof Usuario && $usuario->podeUsarBackoffice($this->permissaoExigida()), 404);
    }
}
