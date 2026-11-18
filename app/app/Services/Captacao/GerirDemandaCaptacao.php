<?php

declare(strict_types=1);

namespace App\Services\Captacao;

use App\Domain\Captacao\StatusDemanda;
use App\Models\DemandaCaptacao;
use App\Models\DemandaCaptacaoEvento;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * STORY-103 — ações do CRM de captação. Cada mudança grava, na mesma transação, o evento na linha do
 * tempo da demanda e a linha de auditoria. Repetir a etapa ou o responsável atual não grava nada.
 */
final class GerirDemandaCaptacao
{
    public const NOTA_MAX = 2000;

    public function mover(DemandaCaptacao $demanda, StatusDemanda $para, Usuario $autor): void
    {
        $de = $demanda->status;

        if ($de === $para) {
            return;
        }

        DB::transaction(function () use ($demanda, $de, $para, $autor): void {
            $demanda->update(['status' => $para, 'status_alterado_em' => now()]);
            $this->evento($demanda, $autor, DemandaCaptacaoEvento::STATUS, "Moveu de {$de->rotulo()} para {$para->rotulo()}.");
            $this->auditar('captacao.demanda_movida', $demanda, $autor, ['status' => $de->value], ['status' => $para->value]);
        });
    }

    public function atribuir(DemandaCaptacao $demanda, ?Usuario $responsavel, Usuario $autor): void
    {
        if ($responsavel !== null && ! $responsavel->temPermissao(PermissaoAdmin::GerenciarCaptacao)) {
            throw new InvalidArgumentException('O responsável precisa ter acesso à captação.');
        }

        $antes = $demanda->responsavel_id;

        if ($antes === $responsavel?->id) {
            return;
        }

        DB::transaction(function () use ($demanda, $responsavel, $autor, $antes): void {
            $demanda->update(['responsavel_id' => $responsavel?->id]);
            $texto = $responsavel === null ? 'Removeu o responsável.' : "Atribuiu a {$responsavel->nome}.";
            $this->evento($demanda, $autor, DemandaCaptacaoEvento::RESPONSAVEL, $texto);
            $this->auditar('captacao.demanda_atribuida', $demanda, $autor, ['responsavel_id' => $antes], ['responsavel_id' => $responsavel?->id]);
        });
    }

    public function anotar(DemandaCaptacao $demanda, string $texto, Usuario $autor): void
    {
        $texto = trim($texto);

        if ($texto === '' || mb_strlen($texto) > self::NOTA_MAX) {
            throw new InvalidArgumentException('Nota vazia ou longa demais.');
        }

        DB::transaction(function () use ($demanda, $texto, $autor): void {
            $evento = $this->evento($demanda, $autor, DemandaCaptacaoEvento::NOTA, $texto);
            // O texto da nota pode ter dado pessoal: a auditoria guarda só o id do evento.
            $this->auditar('captacao.demanda_anotada', $demanda, $autor, null, ['evento_id' => $evento->id]);
        });
    }

    /**
     * Quem pode ser responsável: quem tem a permissão de captação ou é da equipe por configuração.
     *
     * @return Collection<int, Usuario>
     */
    public static function responsaveisPossiveis(): Collection
    {
        /** @var list<string> $equipe */
        $equipe = config('backoffice.equipe_emails', []);

        return Usuario::query()
            ->where(fn ($q) => $q->whereJsonContains('permissoes_admin', PermissaoAdmin::GerenciarCaptacao->value)
                ->when($equipe !== [], fn ($w) => $w->orWhereIn(DB::raw('lower(email)'), $equipe)))
            ->orderBy('nome')
            ->get();
    }

    private function evento(DemandaCaptacao $demanda, Usuario $autor, string $tipo, string $texto): DemandaCaptacaoEvento
    {
        return DemandaCaptacaoEvento::query()->create([
            'demanda_captacao_id' => $demanda->id, 'autor_id' => $autor->id, 'tipo' => $tipo, 'texto' => $texto,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function auditar(string $action, DemandaCaptacao $demanda, Usuario $autor, ?array $antes, ?array $depois): void
    {
        AuditLogger::log(
            action: $action,
            subjectType: 'DemandaCaptacao',
            subjectId: $demanda->id,
            actorType: 'user',
            actorId: $autor->id,
            usuarioId: $autor->id,
            before: $antes,
            after: $depois,
        );
    }
}
