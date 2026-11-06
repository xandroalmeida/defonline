<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\CicloCobranca;
use App\Domain\Cobranca\StatusAssinatura;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Models\Assinatura;
use App\Models\Invoice;
use App\Services\Cobranca\GerirRenovacao;
use App\Services\Cobranca\TrocarDePlano;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * STORY-091 (correção da rodada 1 da validação, D-05, D-07, D-19). Os cenários E25, E16b, E6 e E6b vêm do
 * exploratório da STORY-087, agora com o comportamento correto afirmado.
 * Catálogo: Básico R$ 49,90/mês (cota 1); Pro R$ 199,00/mês (cota 10).
 */
beforeEach(function () {
    _usarProvedor('fake');
    Mail::fake();
    $this->u = _compradorConfirmado();
});

describe('CA-1 — anual cancelado dentro do ano pago (E25)', function () {
    it('E25 a cota mensal continua voltando até o fim do ano pago', function () {
        [$a] = _assinaturaAtivaNoFake($this->u, CicloCobranca::Anual);
        DB::table('assinaturas')->where('id', $a->id)->update(['cota_restante_ciclo' => 0]);
        app(GerirRenovacao::class)->cancelar($this->u);
        expect($a->fresh()->status)->toBe(StatusAssinatura::Cancelada);

        $this->travelTo($a->fresh()->proxima_cota_em->copy()->addHour());
        Artisan::call('assinaturas:renovar-cota');

        $a = $a->fresh();
        expect($a->cota_restante_ciclo)->toBe($a->cota_snapshot)->and($a->status)->toBe(StatusAssinatura::Cancelada)
            ->and(app(Carteira::class)->verificar($this->u->id)->bloqueio)->toBeNull();
    });

    it('cota volta em todos os meses do ano pago, e acaba junto com ele', function () {
        [$a] = _assinaturaAtivaNoFake($this->u, CicloCobranca::Anual);
        app(GerirRenovacao::class)->cancelar($this->u);
        $voltas = 0;

        while (($proxima = $a->fresh()->proxima_cota_em) !== null) {
            DB::table('assinaturas')->where('id', $a->id)->update(['cota_restante_ciclo' => 0]);
            $this->travelTo($proxima->copy()->addMinute());
            Artisan::call('assinaturas:renovar-cota');
            expect($a->fresh()->cota_restante_ciclo)->toBe($a->cota_snapshot);
            $voltas++;
            expect($voltas)->toBeLessThan(13);
        }

        // 11 aniversários dentro do ano (o 12º é o próprio vencimento).
        expect($voltas)->toBe(11);

        // Passado o vencimento, nada mais volta.
        DB::table('assinaturas')->where('id', $a->id)->update(['cota_restante_ciclo' => 0]);
        $this->travelTo($a->fresh()->proximo_vencimento->copy()->addDay());
        expect(Artisan::call('assinaturas:renovar-cota'))->toBe(0)->and($a->fresh()->cota_restante_ciclo)->toBe(0);
    });

    it('assinatura em atraso ou encerrada continua sem receber a cota mensal', function () {
        [$a] = _assinaturaAtivaNoFake($this->u, CicloCobranca::Anual);
        DB::table('assinaturas')->where('id', $a->id)->update(['cota_restante_ciclo' => 0, 'status' => StatusAssinatura::EmAtraso->value]);

        $this->travelTo($a->fresh()->proxima_cota_em->copy()->addHour());
        Artisan::call('assinaturas:renovar-cota');

        expect($a->fresh()->cota_restante_ciclo)->toBe(0);
    });
});

describe('CA-2 — cota contratada é retrato (E16b)', function () {
    it('E16b cota reduzida no catálogo não muda quem renova', function () {
        $i = _pagarAssinatura(_assinaturaPendente($this->u, _plano('Pro')));
        $a = Assinatura::query()->where('usuario_id', $this->u->id)->sole();
        _plano('Pro')->forceFill(['cota_mensal' => 5, 'preco_mensal_centavos' => 29900])->save();
        expect($a->fresh()->cota_snapshot)->toBe(10);

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        _entregar(_fake()->simularRenovacao($i->checkout_id_externo));

        $a = $a->fresh();
        expect($a->periodos_pagos)->toBe(2)->and($a->cota_snapshot)->toBe(10)->and($a->cota_restante_ciclo)->toBe(10)
            ->and($a->preco_snapshot_centavos)->toBe(19900)
            ->and(Invoice::query()->where('renovacao', true)->sole()->cota_snapshot)->toBe(10);
    });

    it('cota aumentada no catálogo também não muda quem já assina (não há reajuste automático)', function () {
        $i = _pagarAssinatura(_assinaturaPendente($this->u, _plano('Básico')));
        $a = Assinatura::query()->where('usuario_id', $this->u->id)->sole();
        _plano('Básico')->forceFill(['cota_mensal' => 3])->save();

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        _entregar(_fake()->simularRenovacao($i->checkout_id_externo));

        expect($a->fresh()->cota_snapshot)->toBe(1);
    });

    it('ao trocar de plano (downgrade agendado), vale a cota do catálogo de hoje do plano escolhido', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(1));
        app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        $this->travelTo($a->data_inicio->copy()->addDays(10));
        app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);
        _plano('Básico')->forceFill(['cota_mensal' => 2])->save();

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        _entregar(_fake()->simularRenovacao($i->checkout_id_externo));

        $a = $a->fresh();
        expect($a->plano_id)->toBe(_plano('Básico')->id)->and($a->cota_snapshot)->toBe(2)->and($a->cota_restante_ciclo)->toBe(2);
    });
});

describe('CA-3 — upgrade com cota extra proporcional (E6, E6b)', function () {
    it('E6 upgrade a 12 horas do fim: a cota do plano superior não sai de graça e o downgrade seguinte não a acumula', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subHours(12));

        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);

        $a = $a->fresh();
        expect($t->tipo)->toBe('aplicada')->and($t->previa->cotaExtraAgora)->toBe(0)
            ->and($a->cota_restante_ciclo)->toBe(1)->and($a->cota_snapshot)->toBe(10);

        $d = app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);

        expect($d->tipo)->toBe('agendada')->and($a->fresh()->cota_restante_ciclo)->toBe(1)
            ->and(FakePaymentProvider::valorDaAssinatura($a->id_externo)['valor'])->toBe(4990);
    });

    it('E6b upgrade 3 dias antes do fim: paga a parte do período e a cota extra é proporcional (0 de 9)', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->proximo_vencimento->copy()->subDays(3));

        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        _pagarAssinatura($t->invoice);

        expect($t->previa->aPagarCentavos)->toBe(1491)->and($t->previa->cotaExtraAgora)->toBe(0)
            ->and($a->fresh()->cota_restante_ciclo)->toBe(1)->and($a->fresh()->cota_snapshot)->toBe(10);
    });

    it('a cota extra mostrada na revisão é a que entra, mesmo se o pagamento chegar mais tarde', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->data_inicio->copy()->addDays(10));
        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal);
        expect($t->previa->cotaExtraAgora)->toBe(6)->and($t->invoice->cota_extra_troca)->toBe(6);

        $this->travelTo(now()->addDays(5));
        _pagarAssinatura($t->invoice);

        expect($a->fresh()->cota_restante_ciclo)->toBe(7);
    });

    it('a cota cheia do plano novo volta na renovação seguinte', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->data_inicio->copy()->addDays(10));
        _pagarAssinatura(app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal)->invoice);

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        _entregar(_fake()->simularRenovacao($i->checkout_id_externo));

        expect($a->fresh()->cota_restante_ciclo)->toBe(10);
    });

    it('anual: a proporção é a do mês da cota, não a do ano', function () {
        [$a] = _assinaturaAtivaNoFake($this->u, CicloCobranca::Anual);
        // 10 dias depois do início, ainda no 1º mês da cota (a janela vai até o 1º aniversário).
        $this->travelTo($a->data_inicio->copy()->addDays(10));

        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Anual);
        $esperado = intdiv(9 * ($a->proxima_cota_em->getTimestamp() - now()->getTimestamp()), $a->proxima_cota_em->getTimestamp() - $a->data_inicio->getTimestamp());
        _pagarAssinatura($t->invoice);

        expect($t->previa->cotaExtraAgora)->toBe($esperado)->toBeGreaterThan(4)
            ->and($a->fresh()->cota_restante_ciclo)->toBe(1 + $esperado)
            ->and($t->previa->cotaCheiaEm->toIso8601String())->toBe($a->proxima_cota_em->toIso8601String());
    });

    it('mensal para anual começa um período novo e dá a cota cheia já', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->data_inicio->copy()->addDays(10));

        $t = app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Anual);
        _pagarAssinatura($t->invoice);

        expect($t->invoice->fresh()->cota_extra_troca)->toBeNull()->and($a->fresh()->cota_restante_ciclo)->toBe(10);
    });
});

describe('CA-4 — repetir upgrade e downgrade não acumula', function () {
    it('agendar e desfazer o downgrade várias vezes não muda cota nem o valor no provedor', function () {
        [$a] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->data_inicio->copy()->addDays(10));
        _pagarAssinatura(app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal)->invoice);
        $restante = $a->fresh()->cota_restante_ciclo;
        $pagoAntes = Invoice::query()->where('assinatura_id', $a->id)->where('status', 'paga')->sum('valor_centavos');

        foreach (range(1, 4) as $_) {
            app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);
            expect(FakePaymentProvider::valorDaAssinatura($a->id_externo)['valor'])->toBe(4990);
            app(TrocarDePlano::class)->desfazer($this->u);
            expect(FakePaymentProvider::valorDaAssinatura($a->id_externo)['valor'])->toBe(19900);
        }

        $a = $a->fresh();
        expect($a->cota_restante_ciclo)->toBe($restante)->and($a->cota_snapshot)->toBe(10)
            ->and($a->preco_snapshot_centavos)->toBe(19900)
            ->and(Invoice::query()->where('assinatura_id', $a->id)->where('status', 'paga')->sum('valor_centavos'))->toBe($pagoAntes);
    });

    it('subir, descer e esperar a renovação deixa a cota do plano de destino, sem sobra do upgrade', function () {
        [$a, $i] = _assinaturaAtivaNoFake($this->u);
        $this->travelTo($a->data_inicio->copy()->addDays(10));
        _pagarAssinatura(app(TrocarDePlano::class)->confirmar($this->u, _plano('Pro'), CicloCobranca::Mensal)->invoice);
        app(TrocarDePlano::class)->confirmar($this->u, _plano('Básico'), CicloCobranca::Mensal);

        $this->travelTo($a->proximo_vencimento->copy()->addHour());
        _entregar(_fake()->simularRenovacao($i->checkout_id_externo));

        $a = $a->fresh();
        expect($a->plano_id)->toBe(_plano('Básico')->id)->and($a->cota_restante_ciclo)->toBe(1)->and($a->cota_snapshot)->toBe(1)
            ->and($a->preco_snapshot_centavos)->toBe(4990);
    });
});
