<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use App\Models\Assinatura;
use App\Models\ConsumoAnalise;
use App\Models\LoteCredito;
use App\Models\TrialEmpresa;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * STORY-073 / espec §4.3 — a carteira do Usuário (não da Empresa Analisada).
 *
 * **Consumo.** {@see consumir()} faz tudo numa transação só, sob um lock da linha do Usuário:
 * confere se a conclusão já foi cobrada, decide a origem ({@see RegrasConsumo}), debita, emite o
 * Diagnóstico (callback do chamador) e registra o consumo. Falha em qualquer passo desfaz o
 * débito (CA-5); duas conclusões simultâneas passam uma por vez (CA-6), e os CHECKs do banco
 * impedem saldo negativo mesmo se a aplicação errar.
 *
 * **Concessão.** {@see concederCota()}, {@see renovarCota()}, {@see mudarStatus()} e
 * {@see concederCreditos()} são a interface para o trial (STORY-074) e os pagamentos
 * (STORY-076, 077, 079): quem paga ou ativa só chama estes métodos.
 */
final class Carteira
{
    /**
     * Consulta sem lock, para decidir se a tela do quiz abre ou bloqueia. Com `$empresaId`, vale também a
     * regra do trial por CNPJ (STORY-074 CA-2).
     */
    public function verificar(string $usuarioId, ?string $empresaId = null): Decisao
    {
        return RegrasConsumo::decidir($this->snapshot($usuarioId, $this->agora(), $empresaId));
    }

    /** STORY-084 — o Usuário pode usar o Raio X do Negócio agora? `null` = sim; senão, o motivo. */
    public function bloqueioDoDrm(string $usuarioId): ?Bloqueio
    {
        return RegrasConsumo::bloqueioDoDrm($this->snapshot($usuarioId, $this->agora()));
    }

    /**
     * STORY-074 — o aviso do trial para a Home, ou nulo se o último vínculo do Usuário não é um trial.
     *
     * @return array{severidade: 'info'|'bloqueio', texto: string, acao: string, rotulo: string}|null
     */
    public function avisoDoTeste(string $usuarioId): ?array
    {
        $foto = $this->snapshot($usuarioId, $this->agora());

        if ($foto->assinatura === null) {
            return null;
        }

        $temCredito = array_filter(
            $foto->lotes,
            fn (LoteVisto $l): bool => $l->status === StatusLote::Ativo && $l->restante > 0 && $l->expiraEm->greaterThan($foto->agora),
        ) !== [];

        return AvisoTrial::para($foto->assinatura, $foto->agora, $temCredito);
    }

    /**
     * @template T of Model
     *
     * @param  Closure(): T  $emitir  cria o Diagnóstico; só roda com saldo garantido
     * @return T
     *
     * @throws SaldoInsuficiente se não há cota nem crédito válido
     * @throws AnaliseJaConcluida se `$quizId` já foi cobrado
     */
    public function consumir(string $usuarioId, ?string $empresaId, ?string $quizId, Closure $emitir): Model
    {
        return DB::transaction(function () use ($usuarioId, $empresaId, $quizId, $emitir): Model {
            $this->travar($usuarioId);

            if ($quizId !== null) {
                $existente = ConsumoAnalise::query()->where('quiz_id', $quizId)->first();

                if ($existente !== null) {
                    throw new AnaliseJaConcluida($existente->diagnostico_id);
                }
            }

            $agora = $this->agora();
            $this->expirarLotesVencidos($usuarioId);

            $decisao = RegrasConsumo::decidir($this->snapshot($usuarioId, $agora, $empresaId));

            if ($decisao->bloqueio !== null) {
                throw new SaldoInsuficiente($decisao->bloqueio);
            }

            $this->registrarEmpresaDeTrial($decisao, $usuarioId, $empresaId, $agora);
            $this->debitar($decisao);
            $diagnostico = $emitir();

            ConsumoAnalise::query()->create([
                'usuario_id' => $usuarioId,
                'diagnostico_id' => $diagnostico->getKey(),
                'empresa_analisada_id' => $empresaId,
                'origem' => $decisao->origem,
                'assinatura_id' => $decisao->assinaturaId,
                'lote_credito_id' => $decisao->loteId,
                'quiz_id' => $quizId,
                'consumido_em' => $agora,
            ]);

            return $diagnostico;
        });
    }

    /**
     * Abre uma assinatura (ou o trial, com `$trial` e sem plano) com a cota do ciclo.
     *
     * @throws AssinaturaVigenteJaExiste
     */
    public function concederCota(
        string $usuarioId,
        int $cota,
        CarbonInterface $inicio,
        CarbonInterface $vencimento,
        ?string $planoId = null,
        ?string $ciclo = null,
        bool $trial = false,
    ): Assinatura {
        if ($cota < 1) {
            throw new InvalidArgumentException('A cota concedida deve ser de pelo menos 1 análise.');
        }

        if ($vencimento->lessThanOrEqualTo($inicio)) {
            throw new InvalidArgumentException('O vencimento deve ser depois do início.');
        }

        try {
            return DB::transaction(function () use ($usuarioId, $cota, $inicio, $vencimento, $planoId, $ciclo, $trial): Assinatura {
                $vigente = Assinatura::query()
                    ->where('usuario_id', $usuarioId)
                    ->whereIn('status', [StatusAssinatura::Ativa->value, StatusAssinatura::EmAtraso->value])
                    ->lockForUpdate()
                    ->first();

                if ($vigente !== null) {
                    // Assinar durante o trial encerra o trial (STORY-074 CA-8): a análise que sobrou não
                    // soma à cota nova, porque a nova assinatura tem a própria cota.
                    if (! $vigente->trial || $trial) {
                        throw new AssinaturaVigenteJaExiste($usuarioId);
                    }

                    $vigente->forceFill(['status' => StatusAssinatura::Expirada])->save();
                }

                return Assinatura::query()->create([
                    'usuario_id' => $usuarioId,
                    'plano_id' => $planoId,
                    'trial' => $trial,
                    'status' => StatusAssinatura::Ativa,
                    'ciclo' => $ciclo,
                    'data_inicio' => $inicio,
                    'proximo_vencimento' => $vencimento,
                    'cota_snapshot' => $cota,
                    'cota_restante_ciclo' => $cota,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Corrida entre duas concessões: o índice parcial barra a segunda.
            throw new AssinaturaVigenteJaExiste($usuarioId);
        }
    }

    /** Novo ciclo: a cota volta cheia e não acumula (espec §2.1, `analise_cota_acumula` = false). */
    public function renovarCota(string $assinaturaId, int $cota, CarbonInterface $novoVencimento): Assinatura
    {
        if ($cota < 1) {
            throw new InvalidArgumentException('A cota concedida deve ser de pelo menos 1 análise.');
        }

        return DB::transaction(function () use ($assinaturaId, $cota, $novoVencimento): Assinatura {
            $assinatura = Assinatura::query()->lockForUpdate()->findOrFail($assinaturaId);

            if ($novoVencimento->lessThanOrEqualTo($assinatura->proximo_vencimento)) {
                throw new InvalidArgumentException('O novo vencimento deve ser depois do atual.');
            }

            $assinatura->forceFill([
                'cota_snapshot' => $cota,
                'cota_restante_ciclo' => $cota,
                'proximo_vencimento' => $novoVencimento,
            ])->save();

            return $assinatura;
        });
    }

    /**
     * STORY-077 CA-6 — ciclo anual: a cota volta cheia todo mês, no dia de aniversário (sem acumular).
     * Roda todo dia e é idempotente: só toca assinatura cuja `proxima_cota_em` já chegou, e a avança
     * para o próximo aniversário (ou para nulo, se o ano pago acaba antes). Mensal não usa isto:
     * a cota renova junto com o pagamento do mês ({@see renovarCota()}).
     *
     * @return int quantas assinaturas tiveram a cota renovada
     */
    public function renovarCotasMensais(): int
    {
        $agora = $this->agora();
        $ids = Assinatura::query()
            ->where('status', StatusAssinatura::Ativa->value)
            ->whereNotNull('proxima_cota_em')
            ->where('proxima_cota_em', '<=', $agora->format('Y-m-d H:i:sP'))
            ->pluck('id');

        $renovadas = 0;

        foreach ($ids as $id) {
            $renovadas += DB::transaction(function () use ($id, $agora): int {
                $a = Assinatura::query()->lockForUpdate()->find($id);

                // Outra execução (ou o webhook) já cuidou dela entre a consulta e o lock.
                if ($a === null || $a->proxima_cota_em === null || $a->proxima_cota_em->greaterThan($agora) || $a->status !== StatusAssinatura::Ativa) {
                    return 0;
                }

                $a->forceFill([
                    'cota_restante_ciclo' => $a->cota_snapshot,
                    'proxima_cota_em' => CotaMensal::proxima($a->data_inicio, $agora, $a->proximo_vencimento),
                ])->save();

                return 1;
            });
        }

        return $renovadas;
    }

    /** Atraso, cancelamento, reativação e encerramento. Cancelar registra a data e mantém o acesso até o vencimento. */
    public function mudarStatus(string $assinaturaId, StatusAssinatura $status): Assinatura
    {
        $assinatura = Assinatura::query()->findOrFail($assinaturaId);
        $assinatura->status = $status;

        if ($status === StatusAssinatura::Cancelada) {
            $assinatura->data_cancelamento = now();
        }

        // Reativar a renovação (STORY-081 CA-5) apaga a marca do cancelamento.
        if ($status === StatusAssinatura::Ativa) {
            $assinatura->data_cancelamento = null;
        }

        $assinatura->save();

        return $assinatura;
    }

    public function concederCreditos(
        string $usuarioId,
        int $quantidade,
        CarbonInterface $expiraEm,
        ?string $invoiceId = null,
        ?CarbonInterface $compradoEm = null,
    ): LoteCredito {
        $compradoEm ??= $this->agora();

        if ($quantidade < 1) {
            throw new InvalidArgumentException('O lote deve ter pelo menos 1 crédito.');
        }

        if ($expiraEm->lessThanOrEqualTo($compradoEm)) {
            throw new InvalidArgumentException('O lote deve vencer depois da compra.');
        }

        return LoteCredito::query()->create([
            'usuario_id' => $usuarioId,
            'invoice_id' => $invoiceId,
            'quantidade_comprada' => $quantidade,
            'quantidade_restante' => $quantidade,
            'status' => StatusLote::Ativo,
            'data_compra' => $compradoEm,
            'data_expiracao' => $expiraEm,
        ]);
    }

    private function agora(): CarbonImmutable
    {
        return CarbonImmutable::instance(now());
    }

    /**
     * Serializa as operações do Usuário. `FOR NO KEY UPDATE` não conflita com o `FOR KEY SHARE`
     * que as FKs de outras tabelas tomam na linha do Usuário, então só os consumos esperam entre si.
     */
    private function travar(string $usuarioId): void
    {
        DB::select('select id from usuarios where id = ? for no key update', [$usuarioId]);
    }

    /**
     * Marca como expirados os lotes cujo prazo passou (todos, ou só os do Usuário). A decisão de
     * consumo já ignora o lote vencido pela data; a marca serve a relatórios e a uma varredura
     * agendada futura.
     */
    public function expirarLotesVencidos(?string $usuarioId = null): int
    {
        return LoteCredito::query()
            ->when($usuarioId !== null, fn ($q) => $q->where('usuario_id', $usuarioId))
            ->where('status', StatusLote::Ativo->value)
            ->where('data_expiracao', '<=', $this->agora())
            ->update(['status' => StatusLote::Expirado->value]);
    }

    private function debitar(Decisao $decisao): void
    {
        $linhas = $decisao->origem === Origem::Cota
            ? DB::update('update assinaturas set cota_restante_ciclo = cota_restante_ciclo - 1, updated_at = now() where id = ? and cota_restante_ciclo > 0', [$decisao->assinaturaId])
            : DB::update("update lotes_credito set quantidade_restante = quantidade_restante - 1, status = case when quantidade_restante - 1 = 0 then 'esgotado' else status end, updated_at = now() where id = ? and status = 'ativo' and quantidade_restante > 0", [$decisao->loteId]);

        if ($linhas !== 1) {
            throw new LogicException('O saldo decidido não estava mais disponível sob o lock.');
        }
    }

    private function snapshot(string $usuarioId, CarbonImmutable $agora, ?string $empresaId = null): SnapshotCarteira
    {
        $assinatura = Assinatura::query()
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('data_inicio')
            ->orderByDesc('created_at')
            // Desempate determinístico (a decisão de consumo depende de qual assinatura vale): com o mesmo
            // início e criação no mesmo instante — trial encerrado e plano pago nascem juntos — vale a
            // paga sobre o trial, a não encerrada sobre a encerrada, e por fim o maior id.
            ->orderBy('trial')
            ->orderByRaw("status = 'expirada'")
            ->orderByDesc('id')
            ->first();

        $lotes = LoteCredito::query()
            ->where('usuario_id', $usuarioId)
            ->where('quantidade_restante', '>', 0)
            ->get()
            ->map(fn (LoteCredito $l): LoteVisto => new LoteVisto(
                $l->id,
                $l->status,
                $l->quantidade_restante,
                CarbonImmutable::instance($l->data_compra),
                CarbonImmutable::instance($l->data_expiracao),
            ))
            ->all();

        return new SnapshotCarteira(
            $agora,
            $assinatura === null ? null : new AssinaturaVista(
                $assinatura->id,
                $assinatura->trial,
                $assinatura->status,
                $assinatura->cota_snapshot,
                $assinatura->cota_restante_ciclo,
                CarbonImmutable::instance($assinatura->proximo_vencimento),
            ),
            $lotes,
            $this->trialElegivel($usuarioId),
            $this->empresaJaEmTesteDeOutraConta($usuarioId, $empresaId),
        );
    }

    /** O Usuário pode ativar o trial agora? (ligado, nunca usado por ele nem pelo CPF, sem assinatura vigente) */
    public function trialElegivel(string $usuarioId): bool
    {
        return (new ElegibilidadeTrial)->motivo($usuarioId) === null;
    }

    /**
     * STORY-074 CA-2 — a empresa (por CNPJ) já recebeu análise de trial de OUTRA conta? Só olha empresas de
     * CNPJ; o mesmo Usuário pode reanalisar a sua.
     */
    private function empresaJaEmTesteDeOutraConta(string $usuarioId, ?string $empresaId): bool
    {
        $hash = $this->hashDoCnpj($empresaId);

        return $hash !== null
            && TrialEmpresa::query()->where('cnpj_hash', $hash)->where(fn ($q) => $q->whereNull('usuario_id')->orWhere('usuario_id', '!=', $usuarioId))->exists();
    }

    private function hashDoCnpj(?string $empresaId): ?string
    {
        if ($empresaId === null) {
            return null;
        }

        $documento = DB::table('empresas_analisadas')->where('id', $empresaId)->where('tipo_documento', 'cnpj')->value('documento');

        return is_string($documento) && $documento !== '' ? DocumentoHash::de($documento) : null;
    }

    /**
     * Ao gastar a análise do trial, grava o CNPJ como "já analisado em teste". O índice único serializa
     * duas contas que analisem a mesma empresa ao mesmo tempo: a segunda espera a primeira e, se o CNPJ
     * já for de outra conta, a transação inteira desfaz (nada é cobrado) e a tela explica.
     */
    private function registrarEmpresaDeTrial(Decisao $decisao, string $usuarioId, ?string $empresaId, CarbonImmutable $agora): void
    {
        if ($decisao->origem !== Origem::Cota) {
            return;
        }

        $hash = $this->hashDoCnpj($empresaId);

        if ($hash === null || ! Assinatura::query()->whereKey($decisao->assinaturaId)->where('trial', true)->exists()) {
            return;
        }

        DB::table('trial_empresas')->insertOrIgnore([
            'id' => (string) Str::uuid7(), 'cnpj_hash' => $hash, 'usuario_id' => $usuarioId, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        if ($this->empresaJaEmTesteDeOutraConta($usuarioId, $empresaId)) {
            throw new SaldoInsuficiente(new Bloqueio(MotivoBloqueio::TesteEmpresaJaAnalisada));
        }
    }
}
