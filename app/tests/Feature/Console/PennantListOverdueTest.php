<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * Cria um arquivo PHP em app/Features para simular uma flag e o `require`s para o
 * autoload pegar (o comando faz `class_exists` antes de extrair o docblock).
 */
function makeFeatureFixture(string $class, string $docblock): string
{
    $path = app_path("Features/{$class}.php");
    $body = <<<PHP
<?php

declare(strict_types=1);

namespace App\\Features;

{$docblock}
final class {$class}
{
    public function resolve(): bool
    {
        return true;
    }
}
PHP;

    file_put_contents($path, $body);
    require_once $path;

    return $path;
}

afterEach(function () {
    foreach (glob(app_path('Features/Fixture*.php')) ?: [] as $file) {
        @unlink($file);
    }
});

it('reports success when no flag is overdue', function () {
    Artisan::call('pennant:list-overdue');

    $output = Artisan::output();

    expect(Artisan::call('pennant:list-overdue'))->toBe(0);
    expect($output)->toContain('Nenhuma feature flag vencida');
});

it('lists overdue flags and returns failure with --fail-on-overdue', function () {
    makeFeatureFixture('FixtureOverdueFlag', <<<'DOC'
/**
 * @owner programador
 * @cleanup_due 2020-01-01
 */
DOC);

    $exit = Artisan::call('pennant:list-overdue', ['--fail-on-overdue' => true]);

    expect($exit)->toBe(1);
    expect(Artisan::output())
        ->toContain('FixtureOverdueFlag')
        ->toContain('2020-01-01');
});

it('does not fail without --fail-on-overdue, even if there is overdue', function () {
    makeFeatureFixture('FixtureOverdueOnlyWarn', <<<'DOC'
/**
 * @owner po
 * @cleanup_due 2019-06-15
 */
DOC);

    $exit = Artisan::call('pennant:list-overdue');

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('FixtureOverdueOnlyWarn');
});

it('warns about flags missing @owner or @cleanup_due', function () {
    makeFeatureFixture('FixtureNoMeta', '/** Não tem owner nem cleanup_due. */');

    $exit = Artisan::call('pennant:list-overdue');

    expect($exit)->toBe(0);
    expect(Artisan::output())
        ->toContain('Flags sem')
        ->toContain('FixtureNoMeta');
});

it('warns about flags with malformed @cleanup_due', function () {
    makeFeatureFixture('FixtureBadDate', <<<'DOC'
/**
 * @owner arquiteto
 * @cleanup_due NAO-EH-UMA-DATA
 */
DOC);

    $exit = Artisan::call('pennant:list-overdue');

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('FixtureBadDate');
});
