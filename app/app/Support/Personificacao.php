<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * STORY-066 / PDR-012 — "Logar como": a equipe assume o acesso de um usuário.
 *
 * O estado mora na sessão e só vale enquanto o usuário logado for o alvo: uma
 * chave velha (alvo excluído, outro login no mesmo navegador) é descartada, e
 * ninguém volta a ser admin por causa dela.
 *
 * @phpstan-type Dados array{sessao_id: string, admin_id: string, usuario_id: string, iniciada_em: string}
 */
final class Personificacao
{
    private const CHAVE_SESSAO = 'personificacao';

    /** @return Dados|null */
    public static function ativa(): ?array
    {
        $request = request();
        if (! $request->hasSession() || ! $request->session()->has(self::CHAVE_SESSAO)) {
            return null;
        }

        /** @var Dados $dados */
        $dados = $request->session()->get(self::CHAVE_SESSAO);
        if ($dados['usuario_id'] !== Auth::id()) {
            $request->session()->forget(self::CHAVE_SESSAO);

            return null;
        }

        return $dados;
    }

    public static function iniciar(Usuario $admin, Usuario $alvo): void
    {
        Auth::login($alvo);
        session()->put(self::CHAVE_SESSAO, [
            'sessao_id' => (string) Str::uuid7(),
            'admin_id' => $admin->id,
            'usuario_id' => $alvo->id,
            'iniciada_em' => now()->toIso8601String(),
        ]);

        self::auditar('personificacao.iniciada', $alvo->id, []);
    }

    /** Devolve a sessão ao admin. Falso se não há personificação ou o admin perdeu o acesso. */
    public static function voltarAoAdmin(): bool
    {
        $ativa = self::ativa();
        $admin = $ativa === null ? null : self::admin($ativa);
        if ($admin === null) {
            return false;
        }

        self::registrarFim('voltou_ao_admin');
        session()->forget(self::CHAVE_SESSAO);
        Auth::login($admin);

        return true;
    }

    /**
     * O admin só vale enquanto existir e continuar na allowlist.
     *
     * @param  Dados  $ativa
     */
    public static function admin(array $ativa): ?Usuario
    {
        $admin = Usuario::find($ativa['admin_id']);

        return $admin?->ehEquipeBackoffice() ? $admin : null;
    }

    /** Sem efeito fora de uma personificação. */
    public static function registrarFim(string $motivo): void
    {
        $ativa = self::ativa();
        if ($ativa !== null) {
            self::auditar('personificacao.encerrada', $ativa['usuario_id'], ['motivo' => $motivo]);
        }
    }

    /** @param  array<string, mixed>  $context */
    private static function auditar(string $action, string $usuarioId, array $context): void
    {
        // O AuditLogger troca o ator pelo admin e acrescenta os dados da sessão.
        AuditLogger::log(
            action: $action,
            subjectType: 'Usuario',
            subjectId: $usuarioId,
            actorType: 'user',
            actorId: $usuarioId,
            usuarioId: $usuarioId,
            context: [
                ...$context,
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ],
        );
    }
}
