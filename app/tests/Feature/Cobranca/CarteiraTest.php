<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Domain\Cobranca\AnaliseJaConcluida;
use App\Domain\Cobranca\AssinaturaVigenteJaExiste;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\Origem;
use App\Domain\Cobranca\SaldoInsuficiente;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\StatusLote;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\ConsumoAnalise;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\LoteCredito;
use App\Models\Plano;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STORY-073 — a carteira: interruptor, consumo (cota, depois crédito por vencimento), bloqueio,
 * registro de cada consumo e a interface de concessão para as estórias de pagamento.
 */
function _payloadCarteira(): array
{
    return [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '80000', 'Q04' => '60000', 'Q05' => '300000', 'Q06' => '40000',
        'Q07' => '30000', 'Q08' => '50000', 'Q09' => '100000', 'Q10' => 45, 'Q11' => 30, 'Q12' => 30,
        'Q13' => '2', 'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ];
}

function _ligarCobranca(bool $ligada = true): void
{
    CobrancaConfig::atual()->update(['ativa' => $ligada]);
}

function _analisar(EmpresaAnalisada $empresa, ?string $quizId = null): Diagnostico
{
    return app(CalcularDiagnostico::class)->execute($empresa, _payloadCarteira(), quizId: $quizId);
}

function _carteira(): Carteira
{
    return app(Carteira::class);
}

beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
});

it('nasce desligado e não consome nada nem bloqueia', function () {
    expect(CobrancaConfig::ligada())->toBeFalse();

    $diagnostico = _analisar($this->empresa);

    expect($diagnostico->exists)->toBeTrue()->and(ConsumoAnalise::query()->count())->toBe(0);
});

it('desligado, não toca no saldo existente', function () {
    $assinatura = _carteira()->concederCota($this->usuario->id, 2, now()->subDay(), now()->addMonth());

    _analisar($this->empresa);

    expect($assinatura->fresh()->cota_restante_ciclo)->toBe(2);
});

it('ligado, debita 1 da cota e registra análise, empresa e origem', function () {
    _ligarCobranca();
    $assinatura = _carteira()->concederCota($this->usuario->id, 3, now()->subDay(), now()->addMonth());

    $diagnostico = _analisar($this->empresa);

    $consumo = ConsumoAnalise::query()->sole();
    expect($assinatura->fresh()->cota_restante_ciclo)->toBe(2)
        ->and($consumo->diagnostico_id)->toBe($diagnostico->id)
        ->and($consumo->empresa_analisada_id)->toBe($this->empresa->id)
        ->and($consumo->usuario_id)->toBe($this->usuario->id)
        ->and($consumo->origem)->toBe(Origem::Cota)
        ->and($consumo->assinatura_id)->toBe($assinatura->id)
        ->and($consumo->lote_credito_id)->toBeNull();
});

it('esgotada a cota, debita o crédito do lote que vence antes e marca o lote esgotado ao zerar', function () {
    _ligarCobranca();
    $cota = _carteira()->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());
    $tarde = _carteira()->concederCreditos($this->usuario->id, 2, now()->addMonths(10));
    $cedo = _carteira()->concederCreditos($this->usuario->id, 1, now()->addMonths(2));

    _analisar($this->empresa);
    _analisar($this->empresa);
    _analisar($this->empresa);

    $origens = ConsumoAnalise::query()->orderBy('consumido_em')->orderBy('id')->get();
    expect($cota->fresh()->cota_restante_ciclo)->toBe(0)
        ->and($cedo->fresh()->quantidade_restante)->toBe(0)
        ->and($cedo->fresh()->status)->toBe(StatusLote::Esgotado)
        ->and($tarde->fresh()->quantidade_restante)->toBe(1)
        ->and($tarde->fresh()->status)->toBe(StatusLote::Ativo)
        ->and($origens->where('origem', Origem::Cota)->count())->toBe(1)
        ->and($origens->where('lote_credito_id', $cedo->id)->count())->toBe(1)
        ->and($origens->where('lote_credito_id', $tarde->id)->count())->toBe(1);
});

it('sem cota nem créditos, bloqueia sem gravar diagnóstico nem consumo', function () {
    _ligarCobranca();

    try {
        _analisar($this->empresa);
        $this->fail('Deveria bloquear.');
    } catch (SaldoInsuficiente $e) {
        expect($e->bloqueio->motivo)->toBe(MotivoBloqueio::SemNada);
    }

    expect(Diagnostico::withoutGlobalScopes()->count())->toBe(0)->and(ConsumoAnalise::query()->count())->toBe(0);
});

it('não consome crédito vencido e marca o lote como expirado', function () {
    _ligarCobranca();
    $vencido = LoteCredito::query()->create([
        'usuario_id' => $this->usuario->id, 'quantidade_comprada' => 2, 'quantidade_restante' => 2,
        'status' => StatusLote::Ativo, 'data_compra' => now()->subYear()->subDay(), 'data_expiracao' => now()->subDay(),
    ]);

    try {
        _analisar($this->empresa);
        $this->fail('Deveria bloquear.');
    } catch (SaldoInsuficiente $e) {
        expect($e->bloqueio->motivo)->toBe(MotivoBloqueio::CreditosVencidos);
    }

    expect($vencido->fresh()->quantidade_restante)->toBe(2);
});

it('a varredura marca vencidos como expirados sem mexer no saldo, de um Usuário ou de todos', function () {
    $outro = Usuario::factory()->create();
    $lote = fn (string $usuarioId) => LoteCredito::query()->create([
        'usuario_id' => $usuarioId, 'quantidade_comprada' => 2, 'quantidade_restante' => 2,
        'status' => StatusLote::Ativo, 'data_compra' => now()->subYear()->subDay(), 'data_expiracao' => now()->subDay(),
    ]);
    $meu = $lote($this->usuario->id);
    $dele = $lote($outro->id);
    $valido = _carteira()->concederCreditos($this->usuario->id, 1, now()->addMonth());

    expect(_carteira()->expirarLotesVencidos($this->usuario->id))->toBe(1)
        ->and($meu->fresh()->status)->toBe(StatusLote::Expirado)
        ->and($dele->fresh()->status)->toBe(StatusLote::Ativo);

    expect(_carteira()->expirarLotesVencidos())->toBe(1)
        ->and($dele->fresh()->status)->toBe(StatusLote::Expirado)
        ->and($dele->fresh()->quantidade_restante)->toBe(2)
        ->and($valido->fresh()->status)->toBe(StatusLote::Ativo);
});

it('usa o lote válido e deixa o vencido marcado como expirado', function () {
    _ligarCobranca();
    $vencido = LoteCredito::query()->create([
        'usuario_id' => $this->usuario->id, 'quantidade_comprada' => 2, 'quantidade_restante' => 2,
        'status' => StatusLote::Ativo, 'data_compra' => now()->subYear()->subDay(), 'data_expiracao' => now()->subDay(),
    ]);
    $bom = _carteira()->concederCreditos($this->usuario->id, 1, now()->addMonth());

    _analisar($this->empresa);

    expect($vencido->fresh()->status)->toBe(StatusLote::Expirado)
        ->and($vencido->fresh()->quantidade_restante)->toBe(2)
        ->and($bom->fresh()->status)->toBe(StatusLote::Esgotado);
});

it('falha na emissão do diagnóstico não consome saldo', function () {
    _ligarCobranca();
    $assinatura = _carteira()->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());
    $lote = _carteira()->concederCreditos($this->usuario->id, 1, now()->addMonth());

    expect(fn () => _carteira()->consumir($this->usuario->id, $this->empresa->id, null, function (): never {
        throw new RuntimeException('motor quebrou');
    }))->toThrow(RuntimeException::class, 'motor quebrou');

    expect($assinatura->fresh()->cota_restante_ciclo)->toBe(1)
        ->and($lote->fresh()->quantidade_restante)->toBe(1)
        ->and(ConsumoAnalise::query()->count())->toBe(0);
});

it('falha no cálculo do motor não consome saldo', function () {
    _ligarCobranca();
    $assinatura = _carteira()->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());
    $payloadInvalido = ['Q01' => 99];

    expect(fn () => app(CalcularDiagnostico::class)->execute($this->empresa, $payloadInvalido, 'setor-inexistente'))
        ->toThrow(InvalidArgumentException::class);

    expect($assinatura->fresh()->cota_restante_ciclo)->toBe(1)->and(ConsumoAnalise::query()->count())->toBe(0);
});

it('a carteira é do Usuário: empresas diferentes consomem o mesmo saldo', function () {
    _ligarCobranca();
    $outra = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
    $assinatura = _carteira()->concederCota($this->usuario->id, 2, now()->subDay(), now()->addMonth());

    _analisar($this->empresa);
    _analisar($outra);

    expect($assinatura->fresh()->cota_restante_ciclo)->toBe(0)
        ->and(ConsumoAnalise::query()->pluck('empresa_analisada_id')->sort()->values()->all())
        ->toBe(collect([$this->empresa->id, $outra->id])->sort()->values()->all());

    expect(fn () => _analisar($this->empresa))->toThrow(SaldoInsuficiente::class);
});

it('o saldo de um Usuário não vale para outro', function () {
    _ligarCobranca();
    _carteira()->concederCota($this->usuario->id, 5, now()->subDay(), now()->addMonth());
    $outroUsuario = Usuario::factory()->create();
    $empresaDele = EmpresaAnalisada::factory()->create(['usuario_id' => $outroUsuario->id]);

    expect(fn () => _analisar($empresaDele))->toThrow(SaldoInsuficiente::class);
});

it('a mesma conclusão repetida não cobra duas vezes e aponta o diagnóstico já emitido', function () {
    _ligarCobranca();
    $assinatura = _carteira()->concederCota($this->usuario->id, 5, now()->subDay(), now()->addMonth());
    $quizId = (string) Str::uuid7();

    $primeiro = _analisar($this->empresa, $quizId);

    try {
        _analisar($this->empresa, $quizId);
        $this->fail('Deveria reconhecer a conclusão anterior.');
    } catch (AnaliseJaConcluida $e) {
        expect($e->diagnosticoId)->toBe($primeiro->id);
    }

    expect($assinatura->fresh()->cota_restante_ciclo)->toBe(4)
        ->and(Diagnostico::withoutGlobalScopes()->count())->toBe(1);
});

it('quizzes diferentes são cobrados cada um', function () {
    _ligarCobranca();
    $assinatura = _carteira()->concederCota($this->usuario->id, 5, now()->subDay(), now()->addMonth());

    _analisar($this->empresa, (string) Str::uuid7());
    _analisar($this->empresa, (string) Str::uuid7());

    expect($assinatura->fresh()->cota_restante_ciclo)->toBe(3);
});

it('verificar devolve o motivo do bloqueio sem consumir', function () {
    $semNada = _carteira()->verificar($this->usuario->id);
    expect($semNada->permitido())->toBeFalse()
        ->and($semNada->bloqueio->motivo)->toBe(MotivoBloqueio::SemNada)
        ->and($semNada->bloqueio->trialElegivel)->toBeTrue();

    _carteira()->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());
    $comCota = _carteira()->verificar($this->usuario->id);
    expect($comCota->permitido())->toBeTrue()->and($comCota->origem)->toBe(Origem::Cota);
    expect(Assinatura::query()->sole()->cota_restante_ciclo)->toBe(1);
});

it('o teste grátis deixa de estar disponível quando o trial está desligado ou já foi usado', function () {
    DB::table('trial_config')->update(['ativo' => false]);
    expect(_carteira()->verificar($this->usuario->id)->bloqueio->trialElegivel)->toBeFalse();

    DB::table('trial_config')->update(['ativo' => true]);
    _carteira()->concederCota($this->usuario->id, 1, now()->subDays(10), now()->subDays(3), trial: true);
    $teste = _carteira()->verificar($this->usuario->id);
    expect($teste->bloqueio->motivo)->toBe(MotivoBloqueio::TesteEncerrado);

    $outro = Usuario::factory()->create();
    expect(_carteira()->verificar($outro->id)->bloqueio->trialElegivel)->toBeTrue();
});

// ---- Concessão: interface para trial e pagamentos ----

it('concede cota de plano e de trial', function () {
    $plano = Plano::query()->firstOrFail();

    $assinatura = _carteira()->concederCota($this->usuario->id, 10, now(), now()->addMonth(), $plano->id, 'mensal');

    expect($assinatura->status)->toBe(StatusAssinatura::Ativa)
        ->and($assinatura->cota_snapshot)->toBe(10)
        ->and($assinatura->cota_restante_ciclo)->toBe(10)
        ->and($assinatura->plano_id)->toBe($plano->id)
        ->and($assinatura->trial)->toBeFalse();

    $trial = _carteira()->concederCota(Usuario::factory()->create()->id, 1, now(), now()->addDays(7), trial: true);
    expect($trial->trial)->toBeTrue()->and($trial->plano_id)->toBeNull();
});

it('recusa uma segunda assinatura vigente e aceita outra depois de encerrar a primeira', function () {
    $primeira = _carteira()->concederCota($this->usuario->id, 1, now()->subMonth(), now()->addDay());

    expect(fn () => _carteira()->concederCota($this->usuario->id, 1, now(), now()->addMonth()))
        ->toThrow(AssinaturaVigenteJaExiste::class);

    _carteira()->mudarStatus($primeira->id, StatusAssinatura::Expirada);
    $segunda = _carteira()->concederCota($this->usuario->id, 1, now(), now()->addMonth());

    expect($segunda->exists)->toBeTrue();
});

it('valida o que se concede', function (Closure $acao) {
    expect($acao)->toThrow(InvalidArgumentException::class);
})->with([
    'cota zero' => [fn () => _carteira()->concederCota(Usuario::factory()->create()->id, 0, now(), now()->addDay())],
    'vencimento antes do início' => [fn () => _carteira()->concederCota(Usuario::factory()->create()->id, 1, now(), now()->subDay())],
    'créditos zero' => [fn () => _carteira()->concederCreditos(Usuario::factory()->create()->id, 0, now()->addDay())],
    'lote já vencido' => [fn () => _carteira()->concederCreditos(Usuario::factory()->create()->id, 1, now()->subDay())],
    'renovar com cota zero' => [fn () => _carteira()->renovarCota(
        _carteira()->concederCota(Usuario::factory()->create()->id, 1, now(), now()->addDay())->id, 0, now()->addMonth(),
    )],
    'renovar para o passado' => [fn () => _carteira()->renovarCota(
        _carteira()->concederCota(Usuario::factory()->create()->id, 1, now(), now()->addDay())->id, 1, now()->addHour(),
    )],
]);

it('renova a cota sem acumular a sobra', function () {
    $assinatura = _carteira()->concederCota($this->usuario->id, 3, now()->subMonth(), now()->addDay());
    $assinatura->update(['cota_restante_ciclo' => 2]);

    $renovada = _carteira()->renovarCota($assinatura->id, 5, CarbonImmutable::instance(now()->addMonth()));

    expect($renovada->cota_snapshot)->toBe(5)
        ->and($renovada->cota_restante_ciclo)->toBe(5)
        ->and($renovada->proximo_vencimento->isAfter(now()->addDays(20)))->toBeTrue();
});

it('cancelar registra a data e mantém a cota até o vencimento', function () {
    _ligarCobranca();
    $assinatura = _carteira()->concederCota($this->usuario->id, 2, now()->subDay(), now()->addMonth());

    $cancelada = _carteira()->mudarStatus($assinatura->id, StatusAssinatura::Cancelada);
    _analisar($this->empresa);

    expect($cancelada->data_cancelamento)->not->toBeNull()
        ->and($assinatura->fresh()->cota_restante_ciclo)->toBe(1);
});

it('assinatura em atraso mantém a análise enquanto há cota (STORY-092 CA-4) e bloqueia quando ela acaba', function () {
    _ligarCobranca();
    $assinatura = _carteira()->concederCota($this->usuario->id, 2, now()->subDay(), now()->addMonth());
    _carteira()->mudarStatus($assinatura->id, StatusAssinatura::EmAtraso);

    _analisar($this->empresa);
    expect($assinatura->fresh()->cota_restante_ciclo)->toBe(1);

    $assinatura->forceFill(['cota_restante_ciclo' => 0])->save();

    try {
        _analisar($this->empresa);
        $this->fail('Deveria bloquear.');
    } catch (SaldoInsuficiente $e) {
        expect($e->bloqueio->motivo)->toBe(MotivoBloqueio::EmAtraso);
    }
});

it('registra a compra do lote com a fatura e a data', function () {
    $fatura = (string) Str::uuid7();
    $lote = _carteira()->concederCreditos($this->usuario->id, 5, now()->addMonths(12), $fatura);

    expect($lote->invoice_id)->toBe($fatura)
        ->and($lote->quantidade_comprada)->toBe(5)
        ->and($lote->quantidade_restante)->toBe(5)
        ->and($lote->status)->toBe(StatusLote::Ativo);
});

// ---- Última defesa: o banco ----

it('o banco recusa saldo negativo e consumo sem origem', function (string $sql, array $params) {
    $this->usuario->refresh();
    expect(fn () => DB::transaction(fn () => DB::statement($sql, $params)))->toThrow(QueryException::class);
})->with([
    'cota negativa' => ['insert into assinaturas (id, usuario_id, trial, status, data_inicio, proximo_vencimento, cota_snapshot, cota_restante_ciclo, created_at, updated_at) select gen_random_uuid(), id, false, \'ativa\', now(), now(), 1, -1, now(), now() from usuarios limit 1', []],
    'cota acima do snapshot' => ['insert into assinaturas (id, usuario_id, trial, status, data_inicio, proximo_vencimento, cota_snapshot, cota_restante_ciclo, created_at, updated_at) select gen_random_uuid(), id, false, \'ativa\', now(), now(), 1, 2, now(), now() from usuarios limit 1', []],
    'lote negativo' => ['insert into lotes_credito (id, usuario_id, quantidade_comprada, quantidade_restante, status, data_compra, data_expiracao, created_at, updated_at) select gen_random_uuid(), id, 1, -1, \'ativo\', now(), now(), now(), now() from usuarios limit 1', []],
    'consumo sem origem' => ['insert into consumos_analise (id, usuario_id, origem, consumido_em, created_at, updated_at) select gen_random_uuid(), id, \'cota\', now(), now(), now() from usuarios limit 1', []],
]);

it('sem a linha de configuração, a cobrança conta como desligada e a linha renasce desligada', function () {
    DB::table('cobranca_config')->delete();

    expect(CobrancaConfig::ligada())->toBeFalse()
        ->and(_analisar($this->empresa)->exists)->toBeTrue()
        ->and(CobrancaConfig::atual()->ativa)->toBeFalse()
        ->and(DB::table('cobranca_config')->count())->toBe(1);
});

it('a corrida entre duas concessões é barrada pelo índice e vira AssinaturaVigenteJaExiste', function () {
    $outroUsuario = $this->usuario->id;
    $concorrente = fn () => DB::table('assinaturas')->insert([
        'id' => (string) Str::uuid7(), 'usuario_id' => $outroUsuario, 'trial' => false, 'status' => 'ativa',
        'data_inicio' => now(), 'proximo_vencimento' => now()->addMonth(), 'cota_snapshot' => 1,
        'cota_restante_ciclo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // A outra concessão entra entre a conferência e o INSERT.
    Assinatura::creating(function () use ($concorrente): void {
        Assinatura::flushEventListeners();
        $concorrente();
    });

    expect(fn () => _carteira()->concederCota($this->usuario->id, 1, now(), now()->addMonth()))
        ->toThrow(AssinaturaVigenteJaExiste::class);
});

it('se o saldo decidido sumiu antes do débito, falha em vez de debitar errado', function () {
    _ligarCobranca();
    $assinatura = _carteira()->concederCota($this->usuario->id, 1, now()->subDay(), now()->addMonth());

    $mexendo = false;
    DB::beforeExecuting(function (string $sql) use (&$mexendo, $assinatura): void {
        if (! $mexendo && str_starts_with($sql, 'update assinaturas set cota_restante_ciclo = cota_restante_ciclo - 1')) {
            $mexendo = true;
            DB::table('assinaturas')->where('id', $assinatura->id)->update(['cota_restante_ciclo' => 0]);
        }
    });

    expect(fn () => _analisar($this->empresa))->toThrow(LogicException::class);
    expect(ConsumoAnalise::query()->count())->toBe(0);
});
