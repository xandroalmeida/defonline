<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;

/**
 * Policy multi-tenancy (ADR-003 §Decisão 1) + STORY-014 CA-4.
 *
 * Recusa view/update/delete quando o `usuario_id` da empresa diverge do
 * Usuário autenticado. O controller faz `authorize('view', $empresa)` e
 * retorna 403 (com audit log `empresa.acesso_negado`) na falha.
 *
 * No MVP `update`/`delete` ainda não estão acessíveis pela UI (epic declara
 * "visualização apenas"), mas a policy já cobre — protege contra rota
 * acidental futura.
 */
final class EmpresaAnalisadaPolicy
{
    public function view(Usuario $usuario, EmpresaAnalisada $empresa): bool
    {
        return $empresa->usuario_id === $usuario->id;
    }

    public function update(Usuario $usuario, EmpresaAnalisada $empresa): bool
    {
        return $empresa->usuario_id === $usuario->id;
    }

    public function delete(Usuario $usuario, EmpresaAnalisada $empresa): bool
    {
        return $empresa->usuario_id === $usuario->id;
    }
}
