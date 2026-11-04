<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Observabilidade\AuditLogger;
use App\Support\Personificacao;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * STORY-066 CA-5 — durante o "Logar como", toda requisição vira uma linha em
 * `audit_logs`: páginas, formulários e ações Livewire (com os campos enviados).
 * Só as credenciais são mascaradas; o audit log preserva PII (ADR-003).
 *
 * Os dados são lidos antes do `$next` porque a própria requisição pode encerrar
 * a personificação (voltar ao admin, logout).
 */
final class AuditarPersonificacao
{
    private const CREDENCIAL = '/senha|password|token|secret/i';

    public function handle(Request $request, Closure $next): Response
    {
        $ativa = Personificacao::ativa();
        if ($ativa === null) {
            return $next($request);
        }

        if (Personificacao::admin($ativa) === null) {
            Personificacao::registrarFim('admin_sem_acesso');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login');
        }

        $entrada = $this->entrada($request);
        $response = $next($request);

        AuditLogger::log(
            action: 'personificacao.requisicao',
            subjectType: 'Usuario',
            subjectId: $ativa['usuario_id'],
            actorType: 'user',
            actorId: $ativa['admin_id'],
            usuarioId: $ativa['usuario_id'],
            context: [
                'personificacao' => $ativa,
                'metodo' => $request->method(),
                'caminho' => $request->path(),
                'query' => $this->semCredenciais($request->query()),
                'rota' => $request->route()?->getName(),
                'status' => $response->getStatusCode(),
                'entrada' => $entrada,
            ],
        );

        return $response;
    }

    /** @return array<array-key, mixed> */
    private function entrada(Request $request): array
    {
        /** @var array<array-key, mixed> $dados */
        $dados = $request->post();
        $componentes = $dados['components'] ?? null;

        return $this->semCredenciais(is_array($componentes)
            ? array_map($this->componenteLivewire(...), $componentes)
            : $dados);
    }

    /**
     * Tira do payload do Livewire o que descreve a ação; o snapshot é estado
     * assinado da tela, não algo que o usuário fez.
     *
     * @return array{componente: mixed, campos: mixed, chamadas: mixed}
     */
    private function componenteLivewire(mixed $componente): array
    {
        $componente = is_array($componente) ? $componente : [];
        $snapshot = json_decode((string) ($componente['snapshot'] ?? ''), true);

        return [
            'componente' => is_array($snapshot) ? ($snapshot['memo']['name'] ?? null) : null,
            'campos' => $componente['updates'] ?? [],
            'chamadas' => $componente['calls'] ?? [],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $dados
     * @return array<array-key, mixed>
     */
    private function semCredenciais(array $dados): array
    {
        foreach ($dados as $chave => $valor) {
            if (preg_match(self::CREDENCIAL, (string) $chave) === 1) {
                $dados[$chave] = '[REDACTED]';
            } elseif (is_array($valor)) {
                $dados[$chave] = $this->semCredenciais($valor);
            }
        }

        return $dados;
    }
}
