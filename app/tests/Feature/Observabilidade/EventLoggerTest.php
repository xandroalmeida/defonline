<?php

declare(strict_types=1);

use App\Models\EventoProduto;
use App\Observabilidade\EventLogger;
use App\Observabilidade\Excecoes\PiiEmEventoException;

it('persists an event with all required fields', function () {
    $event = EventLogger::emit('teste_emissao', ['origem' => 'pest']);

    expect($event)->toBeInstanceOf(EventoProduto::class);
    expect($event->nome_evento)->toBe('teste_emissao');
    expect((array) $event->propriedades)->toHaveKey('origem', 'pest');
    expect($event->request_id)->not->toBeEmpty();
    expect($event->evento_id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}/');
});

it('rejects forbidden PII keys', function (string $chave) {
    expect(fn () => EventLogger::emit('teste', [$chave => 'qualquer']))
        ->toThrow(PiiEmEventoException::class);
})->with(['cpf', 'cnpj', 'email', 'telefone', 'password', 'token', 'nome_completo']);

it('rejects financial keys matching the regex', function (string $chave) {
    expect(fn () => EventLogger::emit('teste', [$chave => 100]))
        ->toThrow(PiiEmEventoException::class);
})->with(['faturamento_bruto', 'balanco_2025', 'receita_q1', 'custo_total']);

it('rejects PII nested inside arrays in propriedades', function () {
    expect(fn () => EventLogger::emit('teste', ['perfil' => ['cpf' => '12345678901']]))
        ->toThrow(PiiEmEventoException::class);
});

it('forbids update on EventoProduto model', function () {
    $event = EventLogger::emit('teste', ['origem' => 'pest']);

    expect(fn () => $event->update(['nome_evento' => 'mudado']))
        ->toThrow(RuntimeException::class);
});

it('forbids delete on EventoProduto model', function () {
    $event = EventLogger::emit('teste', ['origem' => 'pest']);

    expect(fn () => $event->delete())->toThrow(RuntimeException::class);
});
