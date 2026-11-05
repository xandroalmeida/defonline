<?php

declare(strict_types=1);

/**
 * STORY-076 CA-9 / ADR-010 §2 e §8 — o Asaas mora só no adaptador, e nada de cartão no nosso código.
 */
/** Comentários podem citar o provedor para explicar; o que vale é o código. */
function _semComentarios(string $codigo): string
{
    $codigo = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $codigo);
    $saida = '';

    foreach (token_get_all($codigo) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $saida .= is_array($token) ? $token[1] : $token;
    }

    return $saida;
}

function _arquivosPhpDe(array $pastas): array
{
    $raiz = dirname(__DIR__, 3);
    $arquivos = [];

    foreach ($pastas as $pasta) {
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$raiz}/{$pasta}", FilesystemIterator::SKIP_DOTS));

        foreach ($iterador as $arquivo) {
            if (preg_match('/\.php$/', $arquivo->getFilename()) === 1) {
                $arquivos[$arquivo->getPathname()] = _semComentarios((string) file_get_contents($arquivo->getPathname()));
            }
        }
    }

    return $arquivos;
}

it('nenhuma referência ao Asaas fora do adaptador, do bind do provedor e da configuração', function () {
    $permitidos = ['Infrastructure/Cobranca/Asaas/', 'Providers/AppServiceProvider.php', 'config/services.php', 'resources/views/legal/'];
    $achados = [];

    foreach (_arquivosPhpDe(['app', 'routes', 'resources/views', 'config', 'database']) as $caminho => $codigo) {
        foreach ($permitidos as $ok) {
            if (str_contains($caminho, $ok)) {
                continue 2;
            }
        }

        if (preg_match('/asaas/i', $codigo, $m) === 1) {
            $achados[] = basename($caminho).": {$m[0]}";
        }
    }

    expect($achados)->toBe([]);
});

it('o domínio e as telas de cobrança não conhecem classes do adaptador nem do provedor', function () {
    $achados = [];

    foreach (_arquivosPhpDe(['app/Domain', 'app/Services/Cobranca', 'app/Livewire/Cobranca', 'app/Jobs', 'app/Http']) as $caminho => $codigo) {
        if (preg_match('/Infrastructure\\\\Cobranca/', $codigo) === 1 && ! str_ends_with($caminho, 'PagamentoFakeController.php')) {
            $achados[] = basename($caminho);
        }
    }

    expect($achados)->toBe([]);
});

it('nenhum dado de cartão trafega pelo nosso código (PCI)', function () {
    $achados = [];

    foreach (_arquivosPhpDe(['app', 'routes', 'resources/views', 'database']) as $caminho => $codigo) {
        // O adaptador cita `creditCard*` apenas para DESCARTAR esses campos do payload dos webhooks.
        if (str_contains($caminho, 'Infrastructure/Cobranca/Asaas/')) {
            continue;
        }

        if (preg_match('/creditCard|\bccv\b|tokenizeCreditCard|numero_cartao|cvv/i', $codigo, $m) === 1) {
            $achados[] = basename($caminho).": {$m[0]}";
        }
    }

    expect($achados)->toBe([]);
});

it('o adaptador do Asaas nunca registra corpo de requisição ou resposta', function () {
    $codigo = (string) file_get_contents(dirname(__DIR__, 3).'/app/Infrastructure/Cobranca/Asaas/AsaasPaymentProvider.php');

    expect($codigo)->not->toMatch('/Log::|logger\(|->body\(\)|->json\(\)\s*\)\s*;?\s*\/\/ log/');
});

it('a rota do webhook não passa por sessão nem CSRF', function () {
    $rotas = (string) file_get_contents(dirname(__DIR__, 3).'/routes/web.php');
    preg_match("#Route::post\('/webhooks/pagamentos'.*?->name\('webhooks.pagamentos'\);#s", $rotas, $bloco);

    expect($bloco)->not->toBeEmpty()
        ->and($bloco[0])->toContain('StartSession::class')
        ->and($bloco[0])->toContain('PreventRequestForgery::class')
        ->and($bloco[0])->toContain('ValidateCsrfToken::class');
});
