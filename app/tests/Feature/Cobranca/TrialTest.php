<?php

declare(strict_types=1);

use App\Actions\CalcularDiagnostico;
use App\Domain\Cobranca\AssinaturaVigenteJaExiste;
use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\DocumentoHash;
use App\Domain\Cobranca\ElegibilidadeTrial;
use App\Domain\Cobranca\MotivoBloqueio;
use App\Domain\Cobranca\MotivoInelegivelTrial;
use App\Domain\Cobranca\Origem;
use App\Domain\Cobranca\SaldoInsuficiente;
use App\Domain\Cobranca\StatusAssinatura;
use App\Domain\Cobranca\Trial;
use App\Domain\Cobranca\TrialIndisponivel;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Models\ConsumoAnalise;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Plano;
use App\Models\TrialAtivacao;
use App\Models\TrialConfig;
use App\Models\TrialEmpresa;
use App\Models\Usuario;
use App\Observabilidade\AlertaDaEquipe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STORY-074 — trial gratuito: ativação, unicidade por CPF, uma análise de trial por CNPJ,
 * configuração que não afeta trial em andamento, fim do prazo, alerta de IP e assinatura durante o trial.
 */
function _payloadTrial(): array
{
    return [
        'Q01' => 1, 'Q02' => '50000', 'Q03' => '80000', 'Q04' => '60000', 'Q05' => '300000', 'Q06' => '40000',
        'Q07' => '30000', 'Q08' => '50000', 'Q09' => '100000', 'Q10' => 45, 'Q11' => 30, 'Q12' => 30,
        'Q13' => '2', 'Q14' => '20000', 'Q15' => '8000', 'Q16' => '2000',
    ];
}

function _trial(): Trial
{
    return app(Trial::class);
}

beforeEach(function () {
    $this->alerter = Mockery::mock(AlertaDaEquipe::class);
    $this->alerter->shouldReceive('enviar')->byDefault();
    app()->instance(AlertaDaEquipe::class, $this->alerter);
    CobrancaConfig::atual()->update(['ativa' => true]);
    $this->usuario = Usuario::factory()->create();
});

describe('CA-1 ativação', function () {
    it('dá os dias e as análises configurados, sem meio de pagamento', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC'));

        $a = _trial()->ativar($this->usuario->id, '203.0.113.9');

        expect($a->trial)->toBeTrue()
            ->and($a->plano_id)->toBeNull()
            ->and($a->status)->toBe(StatusAssinatura::Ativa)
            ->and($a->cota_snapshot)->toBe(1)
            ->and($a->cota_restante_ciclo)->toBe(1)
            ->and($a->data_inicio->toDateTimeString())->toBe('2026-11-05 10:00:00')
            ->and($a->proximo_vencimento->toDateTimeString())->toBe('2026-11-12 10:00:00');

        $registro = TrialAtivacao::query()->sole();
        expect($registro->usuario_id)->toBe($this->usuario->id)
            ->and($registro->ip)->toBe('203.0.113.9')
            ->and($registro->cpf_hash)->toBe(DocumentoHash::de($this->usuario->cpf))
            ->and($registro->cpf_hash)->not->toContain($this->usuario->cpf);
    });

    it('permite ao Usuário fazer a análise do trial e debita a cota', function () {
        _trial()->ativar($this->usuario->id, null);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

        $d = app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial());

        $consumo = ConsumoAnalise::query()->where('diagnostico_id', $d->id)->sole();
        expect($consumo->origem)->toBe(Origem::Cota)
            ->and(Assinatura::query()->sole()->cota_restante_ciclo)->toBe(0)
            ->and(app(Carteira::class)->verificar($this->usuario->id)->bloqueio->motivo)->toBe(MotivoBloqueio::TesteUsado);
    });

    it('vale com a configuração que o PO escolheu (dias e análises)', function () {
        TrialConfig::atual()->update(['dias' => 14, 'analises' => 3]);
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC'));

        $a = _trial()->ativar($this->usuario->id, null);

        expect($a->cota_snapshot)->toBe(3)->and($a->proximo_vencimento->toDateString())->toBe('2026-11-19');
    });
});

describe('CA-2 elegibilidade por CPF', function () {
    it('recusa o segundo trial do mesmo Usuário', function () {
        _trial()->ativar($this->usuario->id, null);

        expect(fn () => _trial()->ativar($this->usuario->id, null))->toThrow(TrialIndisponivel::class, 'cpf_ja_usou');
        expect(TrialAtivacao::query()->count())->toBe(1)->and(Assinatura::query()->count())->toBe(1);
    });

    it('recusa quem teve o trial vencido (já usou)', function () {
        $a = _trial()->ativar($this->usuario->id, null);
        $a->forceFill(['status' => StatusAssinatura::Expirada])->save();

        try {
            _trial()->ativar($this->usuario->id, null);
            $this->fail('deveria recusar');
        } catch (TrialIndisponivel $e) {
            expect($e->motivo)->toBe(MotivoInelegivelTrial::CpfJaUsou);
        }
    });

    it('recusa quem já tem a ativação do CPF registrada (conta recriada com o mesmo CPF)', function () {
        TrialAtivacao::query()->create(['usuario_id' => null, 'cpf_hash' => DocumentoHash::de($this->usuario->cpf), 'ip' => null, 'ativado_em' => now()->subMonths(3)]);

        try {
            _trial()->ativar($this->usuario->id, null);
            $this->fail('deveria recusar');
        } catch (TrialIndisponivel $e) {
            expect($e->motivo)->toBe(MotivoInelegivelTrial::CpfJaUsou);
        }

        expect(Assinatura::query()->count())->toBe(0);
    });

    it('recusa quem já tem plano vigente', function () {
        $plano = Plano::query()->firstOrFail();
        app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addMonth(), $plano->id, 'mensal');

        try {
            _trial()->ativar($this->usuario->id, null);
            $this->fail('deveria recusar');
        } catch (TrialIndisponivel $e) {
            expect($e->motivo)->toBe(MotivoInelegivelTrial::JaTemAssinatura);
        }
    });

    it('trata a ativação simultânea do mesmo CPF como "já usou" e não duplica', function () {
        // A elegibilidade passa nas duas abas; o índice único barra a segunda.
        $hash = DocumentoHash::de($this->usuario->cpf);
        DB::table('trial_ativacoes')->insert(['id' => (string) Str::uuid7(), 'cpf_hash' => $hash, 'ativado_em' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $elegibilidade = Mockery::mock(ElegibilidadeTrial::class);
        $elegibilidade->shouldReceive('motivo')->andReturn(null);
        $trial = new Trial(app(Carteira::class), $elegibilidade, $this->alerter);

        try {
            $trial->ativar($this->usuario->id, null);
            $this->fail('deveria recusar');
        } catch (TrialIndisponivel $e) {
            expect($e->motivo)->toBe(MotivoInelegivelTrial::CpfJaUsou);
        }

        expect(Assinatura::query()->count())->toBe(0);
    });

    it('trata a corrida pela assinatura vigente como "já usou" e desfaz o registro do CPF', function () {
        $elegibilidade = Mockery::mock(ElegibilidadeTrial::class);
        $elegibilidade->shouldReceive('motivo')->andReturn(null);
        $trial = new Trial(app(Carteira::class), $elegibilidade, $this->alerter);
        $plano = Plano::query()->firstOrFail();
        app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addMonth(), $plano->id, 'mensal');

        expect(fn () => $trial->ativar($this->usuario->id, null))->toThrow(TrialIndisponivel::class);
        expect(TrialAtivacao::query()->count())->toBe(0);
    });

    it('registra o CNPJ ao gastar a análise do trial, só em HMAC', function () {
        _trial()->ativar($this->usuario->id, null);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

        app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial());

        $registro = TrialEmpresa::query()->sole();
        expect($registro->cnpj_hash)->toBe(DocumentoHash::de($empresa->documento))
            ->and($registro->usuario_id)->toBe($this->usuario->id);
    });

    it('barra a análise de trial do mesmo CNPJ em outra conta, sem cobrar nada', function () {
        $outro = Usuario::factory()->create();
        _trial()->ativar($outro->id, null);
        $primeira = EmpresaAnalisada::factory()->create(['usuario_id' => $outro->id, 'documento' => '11222333000181']);
        app(CalcularDiagnostico::class)->execute($primeira, _payloadTrial());

        _trial()->ativar($this->usuario->id, null);
        $mesmaEmpresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => '11222333000181']);

        $decisao = app(Carteira::class)->verificar($this->usuario->id, $mesmaEmpresa->id);
        expect($decisao->bloqueio->motivo)->toBe(MotivoBloqueio::TesteEmpresaJaAnalisada);

        try {
            app(CalcularDiagnostico::class)->execute($mesmaEmpresa, _payloadTrial());
            $this->fail('deveria barrar');
        } catch (SaldoInsuficiente $e) {
            expect($e->bloqueio->motivo)->toBe(MotivoBloqueio::TesteEmpresaJaAnalisada);
        }

        $minha = Assinatura::query()->where('usuario_id', $this->usuario->id)->sole();
        expect($minha->cota_restante_ciclo)->toBe(1)
            ->and(Diagnostico::query()->withoutGlobalScopes()->where('usuario_id', $this->usuario->id)->count())->toBe(0)
            ->and(TrialEmpresa::query()->sole()->usuario_id)->toBe($outro->id);

        // Outra empresa (outro CNPJ) segue valendo no trial.
        expect(app(Carteira::class)->verificar($this->usuario->id, EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id])->id)->permitido())->toBeTrue();
    });

    it('barra na hora de gastar mesmo que a conferência da abertura tenha passado (corrida)', function () {
        $outro = Usuario::factory()->create();
        _trial()->ativar($this->usuario->id, null);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => '11222333000181']);

        // O outro registra o CNPJ depois que o quiz desta conta abriu.
        TrialEmpresa::query()->create(['cnpj_hash' => DocumentoHash::de('11222333000181'), 'usuario_id' => $outro->id]);

        expect(fn () => app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial()))->toThrow(SaldoInsuficiente::class);
        expect(Assinatura::query()->where('usuario_id', $this->usuario->id)->sole()->cota_restante_ciclo)->toBe(1);
    });

    it('desfaz a cobrança se outra conta registra o CNPJ entre a conferência e o débito (corrida)', function () {
        $outro = Usuario::factory()->create();
        _trial()->ativar($this->usuario->id, null);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => '11222333000181']);
        $hash = DocumentoHash::de('11222333000181');
        $feito = false;

        // A outra conta entra logo antes do nosso INSERT em trial_empresas, já fora da conferência.
        DB::beforeExecuting(function (string $sql) use (&$feito, $outro, $hash): void {
            if (! $feito && str_contains($sql, 'insert into "trial_empresas"')) {
                $feito = true;
                DB::connection()->getPdo()->exec(sprintf("insert into trial_empresas (id, cnpj_hash, usuario_id, created_at, updated_at) values ('%s', '%s', '%s', now(), now())", (string) Str::uuid7(), $hash, $outro->id));
            }
        });

        try {
            app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial());
            $this->fail('deveria barrar');
        } catch (SaldoInsuficiente $e) {
            expect($e->bloqueio->motivo)->toBe(MotivoBloqueio::TesteEmpresaJaAnalisada);
        }

        expect($feito)->toBeTrue()
            ->and(Assinatura::query()->where('usuario_id', $this->usuario->id)->sole()->cota_restante_ciclo)->toBe(1)
            ->and(ConsumoAnalise::query()->count())->toBe(0);
    });

    it('deixa o mesmo Usuário reanalisar a própria empresa no trial', function () {
        TrialConfig::atual()->update(['analises' => 2]);
        _trial()->ativar($this->usuario->id, null);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

        app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial());
        app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial());

        expect(Assinatura::query()->sole()->cota_restante_ciclo)->toBe(0)->and(TrialEmpresa::query()->count())->toBe(1);
    });

    it('usa crédito comprado quando o trial é barrado pelo CNPJ', function () {
        $outro = Usuario::factory()->create();
        TrialEmpresa::query()->create(['cnpj_hash' => DocumentoHash::de('11222333000181'), 'usuario_id' => $outro->id]);
        _trial()->ativar($this->usuario->id, null);
        app(Carteira::class)->concederCreditos($this->usuario->id, 2, now()->addYear());
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => '11222333000181']);

        $d = app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial());

        expect(ConsumoAnalise::query()->where('diagnostico_id', $d->id)->sole()->origem)->toBe(Origem::Credito)
            ->and(Assinatura::query()->where('usuario_id', $this->usuario->id)->sole()->cota_restante_ciclo)->toBe(1);
    });

    it('não aplica a regra a empresa de CPF nem a plano pago', function () {
        $outro = Usuario::factory()->create();
        TrialEmpresa::query()->create(['cnpj_hash' => DocumentoHash::de('11222333000181'), 'usuario_id' => $outro->id]);
        $plano = Plano::query()->firstOrFail();
        app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addMonth(), $plano->id, 'mensal');
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => '11222333000181']);

        expect(app(Carteira::class)->verificar($this->usuario->id, $empresa->id)->permitido())->toBeTrue();
        app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial());
        expect(TrialEmpresa::query()->count())->toBe(1);
    });

    it('trata a conta excluída que gastou o CNPJ como "outra conta"', function () {
        TrialEmpresa::query()->create(['cnpj_hash' => DocumentoHash::de('11222333000181'), 'usuario_id' => null]);
        _trial()->ativar($this->usuario->id, null);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id, 'documento' => '11222333000181']);

        expect(app(Carteira::class)->verificar($this->usuario->id, $empresa->id)->bloqueio->motivo)->toBe(MotivoBloqueio::TesteEmpresaJaAnalisada);
    });
});

describe('CA-3 configuração não altera trial em andamento', function () {
    it('mantém dias e análises de quem já ativou', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC'));
        $a = _trial()->ativar($this->usuario->id, null);

        TrialConfig::atual()->update(['dias' => 30, 'analises' => 9]);

        $a->refresh();
        expect($a->cota_snapshot)->toBe(1)->and($a->cota_restante_ciclo)->toBe(1)->and($a->proximo_vencimento->toDateString())->toBe('2026-11-12');
    });
});

describe('CA-4 trial desativado', function () {
    it('recusa a ativação e não mostra elegibilidade', function () {
        TrialConfig::atual()->update(['ativo' => false]);

        try {
            _trial()->ativar($this->usuario->id, null);
            $this->fail('deveria recusar');
        } catch (TrialIndisponivel $e) {
            expect($e->motivo)->toBe(MotivoInelegivelTrial::Desativado);
        }

        expect(Assinatura::query()->count())->toBe(0)
            ->and(app(Carteira::class)->trialElegivel($this->usuario->id))->toBeFalse();
    });

    it('trata a falta da linha de configuração como desativado', function () {
        TrialConfig::query()->delete();

        expect(app(Carteira::class)->trialElegivel($this->usuario->id))->toBeFalse();
    });
});

describe('CA-5 fim do prazo', function () {
    it('bloqueia sem cobrar nada e os diagnósticos seguem visíveis', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC'));
        _trial()->ativar($this->usuario->id, null);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);
        $diagnostico = Diagnostico::factory()->create(['usuario_id' => $this->usuario->id, 'empresa_analisada_id' => $empresa->id]);

        // Um dia depois do prazo: a margem folgada evita depender do fuso da sessão do Postgres (dev: São Paulo).
        $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00', 'UTC'));

        $decisao = app(Carteira::class)->verificar($this->usuario->id);
        expect($decisao->bloqueio->motivo)->toBe(MotivoBloqueio::TesteEncerrado)
            ->and(Assinatura::query()->count())->toBe(1);

        $this->actingAs($this->usuario)->get('/diagnosticos/'.$diagnostico->id)->assertOk();
    });
});

describe('CA-7 alerta por IP', function () {
    it('alerta, sem bloquear, quando o IP passa do limite em 30 dias', function () {
        TrialConfig::atual()->update(['limite_ip_30d' => 2]);
        $this->alerter->shouldReceive('enviar')->once()->withArgs(fn (string $titulo, string $msg, array $ctx) => $ctx['ip_mascarado'] === '198.51.100.x' && $ctx['total_30d'] === 3 && $ctx['limite'] === 2 && str_contains($msg, '198.51.100.x') && ! str_contains($msg, '198.51.100.7') && ! array_key_exists('ip', $ctx));

        foreach (range(1, 3) as $_) {
            $a = _trial()->ativar(Usuario::factory()->create()->id, '198.51.100.7');
            expect($a->status)->toBe(StatusAssinatura::Ativa);
        }

        expect(Assinatura::query()->where('trial', true)->count())->toBe(3);
    });

    it('não alerta no limite exato, com IP de outro dia ou sem IP', function () {
        TrialConfig::atual()->update(['limite_ip_30d' => 2]);
        $this->alerter->shouldNotReceive('enviar');
        // Duas ativações antigas (fora da janela) do mesmo IP.
        foreach (range(1, 2) as $i) {
            TrialAtivacao::query()->create(['usuario_id' => null, 'cpf_hash' => hash('sha256', "antigo{$i}"), 'ip' => '198.51.100.7', 'ativado_em' => now()->subDays(45)]);
        }

        _trial()->ativar(Usuario::factory()->create()->id, '198.51.100.7');
        _trial()->ativar(Usuario::factory()->create()->id, '198.51.100.7');
        _trial()->ativar(Usuario::factory()->create()->id, null);
        _trial()->ativar(Usuario::factory()->create()->id, '');
    });
});

describe('CA-8 assinar durante o trial', function () {
    it('encerra o trial e a nova assinatura tem só a cota do plano', function () {
        $trial = _trial()->ativar($this->usuario->id, null);
        $plano = Plano::query()->where('cota_mensal', 10)->firstOrFail();

        $pago = app(Carteira::class)->concederCota($this->usuario->id, $plano->cota_mensal, now(), now()->addMonth(), $plano->id, 'mensal');

        expect($trial->refresh()->status)->toBe(StatusAssinatura::Expirada)
            ->and($pago->cota_restante_ciclo)->toBe(10)
            ->and(Assinatura::query()->where('usuario_id', $this->usuario->id)->whereIn('status', ['ativa', 'em_atraso'])->sole()->id)->toBe($pago->id)
            ->and(app(Carteira::class)->verificar($this->usuario->id)->origem)->toBe(Origem::Cota);
    });

    it('não soma a análise do trial que sobrou', function () {
        _trial()->ativar($this->usuario->id, null);
        $plano = Plano::query()->where('cota_mensal', 1)->firstOrFail();

        $pago = app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addMonth(), $plano->id, 'mensal');

        expect($pago->cota_snapshot)->toBe(1)->and($pago->cota_restante_ciclo)->toBe(1);
    });

    it('continua recusando uma segunda assinatura paga, ou um trial sobre um trial', function () {
        $plano = Plano::query()->firstOrFail();
        app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addMonth(), $plano->id, 'mensal');
        expect(fn () => app(Carteira::class)->concederCota($this->usuario->id, 1, now(), now()->addMonth(), $plano->id, 'mensal'))->toThrow(AssinaturaVigenteJaExiste::class);

        $outro = Usuario::factory()->create();
        app(Carteira::class)->concederCota($outro->id, 1, now(), now()->addDays(7), trial: true);
        expect(fn () => app(Carteira::class)->concederCota($outro->id, 1, now(), now()->addDays(7), trial: true))->toThrow(AssinaturaVigenteJaExiste::class);
    });
});

describe('CA-9 interruptor', function () {
    it('com a cobrança desligada o trial não é consumido nem altera o diagnóstico', function () {
        CobrancaConfig::atual()->update(['ativa' => false]);
        $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $this->usuario->id]);

        app(CalcularDiagnostico::class)->execute($empresa, _payloadTrial());

        expect(ConsumoAnalise::query()->count())->toBe(0)->and(TrialEmpresa::query()->count())->toBe(0);
    });
});
