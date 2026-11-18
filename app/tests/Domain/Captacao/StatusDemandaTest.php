<?php

declare(strict_types=1);

use App\Domain\Captacao\StatusDemanda;

/** STORY-103 — etapas do quadro de captação, na ordem das colunas. */
it('segue a ordem Nova → Em análise → Respondida → Encerrada', function () {
    expect(array_map(fn (StatusDemanda $s) => $s->rotulo(), StatusDemanda::cases()))
        ->toBe(['Nova', 'Em análise', 'Respondida', 'Encerrada']);
});

it('só Nova e Em análise ainda devem retorno ao cliente', function () {
    expect(array_map(fn (StatusDemanda $s) => $s->emAberto(), StatusDemanda::cases()))->toBe([true, true, false, false]);
});
