<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Lista feature flags com `@cleanup_due` no passado.
 *
 * Gate de PR (ADR-006 §3.1 — flags-check, warning sem bloqueio): expõe flags vencidas
 * e pede limpeza. Política: cada flag em `app/Features/` carrega PHPDoc `@owner` e
 * `@cleanup_due YYYY-MM-DD` (ADR-006 §Decisão 7).
 */
final class PennantListOverdue extends Command
{
    protected $signature = 'pennant:list-overdue {--fail-on-overdue : Sai com código != 0 se houver flag vencida}';

    protected $description = 'Lista feature flags com @cleanup_due no passado';

    public function handle(): int
    {
        $featuresDir = app_path('Features');

        if (! is_dir($featuresDir)) {
            $this->info('Sem diretório app/Features — nenhuma flag para auditar.');

            return self::SUCCESS;
        }

        $overdue = [];
        $missingMeta = [];
        $hoje = now()->startOfDay();

        foreach ((new Finder)->files()->in($featuresDir)->name('*.php') as $file) {
            $relative = $file->getRelativePathname();
            $fqcn = 'App\\Features\\'.str_replace(['/', '.php'], ['\\', ''], $relative);

            if (! class_exists($fqcn)) {
                continue;
            }

            $docblock = (new ReflectionClass($fqcn))->getDocComment() ?: '';

            $owner = $this->extractTag($docblock, 'owner');
            $cleanup = $this->extractTag($docblock, 'cleanup_due');

            if ($owner === null || $cleanup === null) {
                $missingMeta[] = $fqcn;

                continue;
            }

            try {
                $cleanupDate = Carbon::parse($cleanup);
            } catch (\Throwable) {
                $missingMeta[] = "{$fqcn} (cleanup_due malformado: {$cleanup})";

                continue;
            }

            if ($cleanupDate->lessThan($hoje)) {
                $overdue[] = "{$fqcn} @cleanup_due {$cleanup} (owner: {$owner})";
            }
        }

        if ($missingMeta !== []) {
            $this->warn('Flags sem @owner + @cleanup_due:');
            foreach ($missingMeta as $m) {
                $this->line("  - {$m}");
            }
        }

        if ($overdue !== []) {
            $this->error('Feature flags vencidas (cleanup pendente):');
            foreach ($overdue as $o) {
                $this->line("  - {$o}");
            }

            return $this->option('fail-on-overdue') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Nenhuma feature flag vencida. ✅');

        return self::SUCCESS;
    }

    private function extractTag(string $docblock, string $tag): ?string
    {
        if (preg_match('/@'.preg_quote($tag, '/').'\s+(.+?)(\s|\*|$)/', $docblock, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}
