<?php

declare(strict_types=1);

use App\Actions\Drm\CompetenciaNaoLancada;
use App\Actions\Drm\RetificacaoConflitante;
use App\Actions\Drm\RetificacaoSemMudanca;
use App\Actions\Drm\RetificarLancamento;
use App\Domain\Drm\Competencia;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Lancamento;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Ocorrencia;
use App\Domain\Drm\Setor;
use App\Models\AuditLog;
use App\Models\DrmLancamento;
use App\Models\DrmPreferencia;
use App\Models\DrmRascunho;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/*
|-------------------------------------------------------------------------
| STORY-050 — retificação de competência (ADR-007, IDR-016).
| CA-2, CA-3, CA-4, CA-5, CA-7 e CA-9, verificados linha a linha no banco.
|-------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->usuario = Usuario::factory()->create();
    $this->actingAs($this->usuario);
    $this->empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
});

/** O mesmo lançamento da factory (tudo agregado), com D02 (impostos) e outros campos trocados. */
function lancamentoCom(array $texto = []): Lancamento
{
    $modos = array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Agregado);

    return Lancamento::deTexto(Setor::Comercio, $modos, [
        'D01' => '84.300,00', 'D02' => '5.058,00', 'D03' => '41.200,00', 'D08' => '9.000,00',
        'D15' => '20.702,00', 'D37' => '1.100,00', 'D40' => '4.000,00', 'D41' => '', 'D42' => '',
        ...$texto,
    ]);
}

function retificar(EmpresaAnalisada $empresa, string $competencia, Lancamento $lancamento, int $base, array $alertas = [], ?DrmRascunho $rascunho = null): DrmLancamento
{
    return app(RetificarLancamento::class)->execute($empresa, Competencia::deTexto($competencia), $lancamento, $alertas, $base, $rascunho);
}

function lancadoEm(EmpresaAnalisada $empresa, string $competencia, array $extra = []): DrmLancamento
{
    return DrmLancamento::factory()->create([
        'empresa_analisada_id' => $empresa->id, 'competencia' => $competencia.'-01',
        'lancamento_payload' => lancamentoCom()->payload(), ...$extra,
    ]);
}

describe('retificação simples (CA-2, CA-3, CA-4)', function () {
    it('insere a versão 2, aponta a 1 para ela e preserva a 1 intacta', function () {
        $v1 = lancadoEm($this->empresa, '2026-09');
        $antes = DB::table('drm_lancamentos')->where('id', $v1->id)->first();

        $v2 = retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);

        expect($v2->versao)->toBe(2)
            ->and($v2->substituido_por_id)->toBeNull()
            ->and(DrmLancamento::vigenteDaCompetencia($this->empresa, '2026-09-01')?->id)->toBe($v2->id);

        $depois = DB::table('drm_lancamentos')->where('id', $v1->id)->first();
        foreach (['versao', 'lancamento_payload', 'payload_hash', 'demonstracao', 'alertas_aceitos', 'motor_drm_version', 'gerado_em', 'setor', 'competencia'] as $coluna) {
            expect($depois->{$coluna})->toBe($antes->{$coluna}, "coluna {$coluna} da versão 1 mudou");
        }
        expect($depois->substituido_por_id)->toBe($v2->id)
            ->and(DrmLancamento::query()->where('competencia', '2026-09-01')->count())->toBe(2);
    });

    it('a versão anterior continua recuperável, em ordem de versão', function () {
        lancadoEm($this->empresa, '2026-09');
        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);

        $cadeia = DrmLancamento::query()->where('empresa_analisada_id', $this->empresa->id)->where('competencia', '2026-09-01')->orderBy('versao')->get();

        expect($cadeia->pluck('versao')->all())->toBe([1, 2])
            ->and($cadeia[0]->lancamento_payload['D02'])->toBe('5058.00')
            ->and($cadeia[1]->lancamento_payload['D02'])->toBe('6000.00');
    });

    it('a demonstração da nova versão reflete os novos valores, calculada pelo motor', function () {
        $v1 = lancadoEm($this->empresa, '2026-09');
        $v2 = retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);

        $l2 = fn (DrmLancamento $l) => collect($l->demonstracao['linhas']['fora'])->firstWhere('codigo', 'L2');

        expect($l2($v1)['nominal'])->toBe('5058.00')
            ->and($l2($v2)['nominal'])->toBe('6000.00')
            ->and($v2->demonstracao['motor_drm_version'])->toBe($v2->motor_drm_version)
            ->and($v2->payload_hash)->not->toBe($v1->payload_hash);
    });

    it('sinalização sai da linha vigente: versao > 1, versao - 1 e gerado_em', function () {
        $v1 = lancadoEm($this->empresa, '2026-09');
        expect($v1->foiRetificado())->toBeFalse()
            ->and($v1->vezesRetificado())->toBe(0)
            ->and($v1->retificadoEm())->toBeNull();

        $v2 = retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);

        expect($v2->foiRetificado())->toBeTrue()
            ->and($v2->vezesRetificado())->toBe(1)
            ->and($v2->retificadoEm()?->equalTo($v2->gerado_em))->toBeTrue();
    });

    it('guarda os alertas aceitos da nova versão, fora do hash', function () {
        lancadoEm($this->empresa, '2026-09');
        $v2 = retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1, [new Ocorrencia('A1', 'D01', [], 6000_00)]);

        expect($v2->alertas_aceitos)->toHaveCount(1)
            ->and($v2->alertas_aceitos[0]['regra'])->toBe('A1')
            ->and($v2->alertas_aceitos[0]['valor_envolvido'])->toBe('6000.00');
    });

    it('encerra o rascunho de retificação, preservando a trilha (soft delete)', function () {
        lancadoEm($this->empresa, '2026-09');
        $rascunho = DrmRascunho::query()->create([
            'usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $this->empresa->id, 'competencia' => '2026-09-01',
            'origem' => 'retificacao', 'base_versao' => 1, 'rascunho_payload' => [], 'expires_at' => now()->addDays(90),
        ]);

        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1, [], $rascunho);

        expect(DrmRascunho::query()->count())->toBe(0)
            ->and(DrmRascunho::withTrashed()->count())->toBe(1);
    });

    it('não mexe nas preferências de modo da empresa', function () {
        lancadoEm($this->empresa, '2026-09');
        DrmPreferencia::gravarModos($this->empresa, array_fill_keys(array_map(fn (Grupo $g) => $g->value, Grupo::cases()), Modo::Detalhado));

        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);

        expect(DrmPreferencia::daEmpresa($this->empresa)->modos())->each->toBe(Modo::Detalhado);
    });
});

describe('retificação múltipla (CA-9)', function () {
    it('v1 → v2 → v3: só a última é vigente e o contador acompanha', function () {
        lancadoEm($this->empresa, '2026-09');
        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);
        $v3 = retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.100,00']), 2);

        $vigentes = DrmLancamento::query()->vigente()->where('competencia', '2026-09-01')->get();

        expect($vigentes)->toHaveCount(1)
            ->and($vigentes[0]->id)->toBe($v3->id)
            ->and($v3->versao)->toBe(3)
            ->and($v3->vezesRetificado())->toBe(2)
            ->and(DrmLancamento::query()->where('competencia', '2026-09-01')->orderBy('versao')->pluck('versao')->all())->toBe([1, 2, 3]);

        // cada versão aponta para a seguinte, a última para ninguém
        $ids = DrmLancamento::query()->where('competencia', '2026-09-01')->orderBy('versao')->get();
        expect($ids[0]->substituido_por_id)->toBe($ids[1]->id)
            ->and($ids[1]->substituido_por_id)->toBe($ids[2]->id);
    });

    it('voltar ao valor da versão 1 é uma retificação válida (o hash é comparado só com a vigente)', function () {
        lancadoEm($this->empresa, '2026-09');
        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);
        $v3 = retificar($this->empresa, '2026-09', lancamentoCom(), 2);

        expect($v3->versao)->toBe(3);
    });
});

describe('nenhuma outra competência é tocada (CA-3) e comparativos derivam das vigentes (CA-5)', function () {
    it('só as duas linhas da competência mudam; as demais ficam byte a byte', function () {
        $agosto = lancadoEm($this->empresa, '2026-08');
        lancadoEm($this->empresa, '2026-09');
        $outubro = lancadoEm($this->empresa, '2026-10', ['lancamento_payload' => lancamentoCom(['D01' => '90.000,00'])->payload()]);
        $outra = lancadoEm(EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]), '2026-09');
        $fotos = fn () => DB::table('drm_lancamentos')->whereIn('id', [$agosto->id, $outubro->id, $outra->id])->orderBy('id')->get()->toArray();
        $antes = $fotos();

        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);

        expect($fotos())->toEqual($antes)
            ->and(DrmLancamento::query()->count())->toBe(5);
    });

    it('a variação de outubro sobre setembro, derivada na leitura das vigentes, acompanha a retificação', function () {
        lancadoEm($this->empresa, '2026-09');
        lancadoEm($this->empresa, '2026-10', ['lancamento_payload' => lancamentoCom(['D01' => '90.000,00'])->payload()]);

        $lucroDe = fn (string $competencia): int => (int) round(100 * (float) collect(
            DrmLancamento::vigenteDaCompetencia($this->empresa, $competencia.'-01')->demonstracao['linhas']['fora'],
        )->firstWhere('codigo', 'L11')['nominal']);
        $variacao = fn () => $lucroDe('2026-10') - $lucroDe('2026-09');

        $antes = $variacao();
        retificar($this->empresa, '2026-09', lancamentoCom(['D01' => '70.000,00']), 1);

        expect($variacao())->not->toBe($antes)
            ->and($variacao())->toBe($lucroDe('2026-10') - $lucroDe('2026-09'));
    });
});

describe('recusas', function () {
    it('retificação no-op é bloqueada e não grava nada nem audita', function () {
        $v1 = lancadoEm($this->empresa, '2026-09');

        expect(fn () => retificar($this->empresa, '2026-09', lancamentoCom(), 1))->toThrow(RetificacaoSemMudanca::class);

        expect(DrmLancamento::query()->count())->toBe(1)
            ->and($v1->fresh()->substituido_por_id)->toBeNull()
            ->and(AuditLog::query()->where('action', 'drm.lancamento.retificado')->count())->toBe(0);
    });

    it('escrever o mesmo valor com outra formatação continua sendo no-op (hash canônico)', function () {
        lancadoEm($this->empresa, '2026-09');

        expect(fn () => retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '5058,00']), 1))->toThrow(RetificacaoSemMudanca::class);
    });

    it('base_versao divergente da vigente é conflito e não grava nada', function () {
        lancadoEm($this->empresa, '2026-09');
        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1); // outra sessão chegou antes

        expect(fn () => retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '7.000,00']), 1))->toThrow(RetificacaoConflitante::class);

        expect(DrmLancamento::query()->where('competencia', '2026-09-01')->count())->toBe(2)
            ->and(AuditLog::query()->where('action', 'drm.lancamento.retificado')->count())->toBe(1);
    });

    it('o conflito vale também para base_versao à frente da vigente', function () {
        lancadoEm($this->empresa, '2026-09');

        expect(fn () => retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 2))->toThrow(RetificacaoConflitante::class);
    });

    it('competência sem lançamento não tem o que retificar', function () {
        expect(fn () => retificar($this->empresa, '2026-09', lancamentoCom(), 1))->toThrow(CompetenciaNaoLancada::class);
    });

    it('lançamento apagado (soft delete) não é retificável', function () {
        lancadoEm($this->empresa, '2026-09')->delete();

        expect(fn () => retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1))->toThrow(CompetenciaNaoLancada::class);
    });

    it('falha no meio desfaz tudo: sem auditar não há retificação, a vigente continua vigente', function () {
        $v1 = lancadoEm($this->empresa, '2026-09');
        AuditLog::creating(fn () => throw new RuntimeException('audit indisponível'));

        expect(fn () => retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1))->toThrow(RuntimeException::class);

        expect($v1->fresh()->substituido_por_id)->toBeNull()
            ->and(DrmLancamento::query()->count())->toBe(1);
    });
});

describe('auditoria (CA-7)', function () {
    it('registra autor, momento e versões, sem copiar o payload financeiro', function () {
        $v1 = lancadoEm($this->empresa, '2026-09');
        $v2 = retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);

        $log = AuditLog::query()->where('action', 'drm.lancamento.retificado')->sole();

        expect($log->actor_type)->toBe('user')
            ->and($log->actor_id)->toBe((string) $this->usuario->id)
            ->and($log->usuario_id)->toBe($this->usuario->id)
            ->and($log->subject_id)->toBe($v2->id)
            ->and($log->before->getArrayCopy())->toBe(['versao' => 1, 'payload_hash' => $v1->payload_hash])
            ->and($log->after->getArrayCopy())->toBe(['versao' => 2, 'payload_hash' => $v2->payload_hash])
            ->and($log->occurred_at)->not->toBeNull()
            ->and($log->context['competencia'])->toBe('2026-09')
            ->and(json_encode($log->toArray()))->not->toContain('6000.00')->not->toContain('D02');
    });

    it('uma retificação, um registro; a recusada não deixa rastro', function () {
        lancadoEm($this->empresa, '2026-09');
        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.000,00']), 1);
        retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.100,00']), 2);
        try {
            retificar($this->empresa, '2026-09', lancamentoCom(['D02' => '6.100,00']), 3);
        } catch (RetificacaoSemMudanca) {
        }

        expect(AuditLog::query()->where('action', 'drm.lancamento.retificado')->count())->toBe(2);
    });
});
