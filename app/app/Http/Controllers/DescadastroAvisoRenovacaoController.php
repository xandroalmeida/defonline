<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\Personificacao;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * STORY-099 CA-6 — descadastro sem login. A assinatura do link identifica o usuário. Parar de receber pede
 * um clique na página, porque filtros de e-mail abrem os links sozinhos, e abrir não pode descadastrar.
 */
final class DescadastroAvisoRenovacaoController extends Controller
{
    public function mostrar(Request $request, Usuario $usuario): View
    {
        return view('avisos.renovacao-descadastro', [
            'acao' => $request->fullUrl(),
            'descadastrado' => $usuario->aviso_renovacao_descadastrado_em !== null,
        ]);
    }

    public function descadastrar(Request $request, Usuario $usuario): View
    {
        if (Personificacao::ativa() === null && $usuario->aviso_renovacao_descadastrado_em === null) {
            $usuario->forceFill(['aviso_renovacao_descadastrado_em' => now()])->save();

            AuditLogger::log(
                action: 'usuario.aviso_renovacao_descadastrado',
                subjectType: 'Usuario',
                subjectId: $usuario->id,
                actorType: 'user',
                actorId: $usuario->id,
                usuarioId: $usuario->id,
                context: [],
            );
        }

        return view('avisos.renovacao-descadastro', ['acao' => $request->fullUrl(), 'descadastrado' => $usuario->aviso_renovacao_descadastrado_em !== null]);
    }
}
