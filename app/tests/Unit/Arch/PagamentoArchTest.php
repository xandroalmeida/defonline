<?php

declare(strict_types=1);
use App\Domain\Cobranca\CobrancaNoProvedor;

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

it('nenhum DTO, enum ou model do domínio de cobrança tem campo de número, validade ou CVV de cartão (ADR-010 §2a, STORY-077 CA-4)', function () {
    $proibido = '/(^|_)(numero|number|pan|cvv|cvc|ccv|validade|expiry|holder|titular|token)(_|$)|^(card|cartao)(Numero|Number|Validade|Cvv|Token)/i';
    $achados = [];

    foreach (glob(dirname(__DIR__, 3).'/app/Domain/Cobranca/*.php') ?: [] as $arquivo) {
        $classe = 'App\\Domain\\Cobranca\\'.basename($arquivo, '.php');

        if (! class_exists($classe)) {
            continue;
        }

        foreach ((new ReflectionClass($classe))->getProperties() as $p) {
            $nome = (string) preg_replace('/(?<!^)([A-Z])/', '_$1', $p->getName());

            if (preg_match($proibido, $nome) === 1 && ! in_array($p->getName(), ['validade', 'validadeMeses'], true)) {
                $achados[] = "{$classe}::\${$p->getName()}";
            }
        }
    }

    // `validade` no domínio é a validade do CRÉDITO (meses), nunca a do cartão: está liberada por nome acima.
    expect($achados)->toBe([]);
});

it('só a bandeira e os 4 últimos dígitos do cartão existem como dado nosso (ADR-010 §2)', function () {
    $dto = new ReflectionClass(CobrancaNoProvedor::class);
    $campos = array_map(fn (ReflectionProperty $p) => $p->getName(), $dto->getProperties());

    expect($campos)->toContain('cartaoBandeira')->toContain('cartaoUltimos4')
        ->and(array_values(array_filter($campos, fn (string $c) => str_starts_with($c, 'cartao'))))->toBe(['cartaoBandeira', 'cartaoUltimos4']);
});
