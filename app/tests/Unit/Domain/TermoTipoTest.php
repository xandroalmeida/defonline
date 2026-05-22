<?php

declare(strict_types=1);

use App\Domain\TermoTipo;

it('marca termo_adesao e lgpd como obrigatórios e marketing como opcional', function () {
    expect(TermoTipo::TermoAdesao->obrigatorio())->toBeTrue();
    expect(TermoTipo::Lgpd->obrigatorio())->toBeTrue();
    expect(TermoTipo::Marketing->obrigatorio())->toBeFalse();
});

it('expõe valores em snake_case alinhados ao check constraint do banco', function () {
    expect(TermoTipo::TermoAdesao->value)->toBe('termo_adesao');
    expect(TermoTipo::Lgpd->value)->toBe('lgpd');
    expect(TermoTipo::Marketing->value)->toBe('marketing');
});
