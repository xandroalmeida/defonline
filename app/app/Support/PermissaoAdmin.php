<?php

declare(strict_types=1);

namespace App\Support;

/**
 * STORY-067 / PDR-013 — permissões selecionáveis de um admin do backoffice.
 * Quem está em BACKOFFICE_EMAILS tem todas.
 */
enum PermissaoAdmin: string
{
    case VerUsuarios = 'usuarios.ver';
    case LogarComo = 'usuarios.logar_como';
    case GerenciarAdmins = 'admins.gerenciar';
    case EditarGlossario = 'glossario.editar';
    case EditarRecomendacoes = 'recomendacoes.editar';
    case GerenciarPlanos = 'planos.gerenciar';
    case GerenciarCupons = 'cupons.gerenciar';
    case GerenciarFinanceiro = 'financeiro.gerenciar';

    public function rotulo(): string
    {
        return match ($this) {
            self::VerUsuarios => 'Ver usuários',
            self::LogarComo => 'Logar como usuário',
            self::GerenciarAdmins => 'Gerenciar admins',
            self::EditarGlossario => 'Manter glossário',
            self::EditarRecomendacoes => 'Manter recomendações',
            self::GerenciarPlanos => 'Gerenciar planos e pacotes',
            self::GerenciarCupons => 'Gerenciar cupons',
            self::GerenciarFinanceiro => 'Gerenciar assinaturas, reembolsos e notas fiscais',
        };
    }
}
