<?php

declare(strict_types=1);

namespace Tests\Browser\Concerns;

use Laravel\Dusk\Browser;

/** RNF-UI-02 — elementos da mesma linha precisam ter o mesmo topo e a mesma base, com tolerância de 1 px. */
trait VerificaAlinhamento
{
    /** @param  list<array{0: string, 1: string}>  $pares  valores de `dusk` lado a lado */
    protected function assertAlinhados(Browser $browser, array $pares): void
    {
        $caixa = function (string $dusk) use ($browser): array {
            /** @var array{0: array{0: float, 1: float}} $resultado */
            $resultado = $browser->script("const r = document.querySelector('[dusk=\"{$dusk}\"]').getBoundingClientRect(); return [r.top, r.bottom];");

            return $resultado[0];
        };

        foreach ($pares as [$esquerda, $direita]) {
            [$topoEsquerda, $baseEsquerda] = $caixa($esquerda);
            [$topoDireita, $baseDireita] = $caixa($direita);

            $this->assertEqualsWithDelta($topoEsquerda, $topoDireita, 1, "{$esquerda} e {$direita} deveriam ter o mesmo topo.");
            $this->assertEqualsWithDelta($baseEsquerda, $baseDireita, 1, "{$esquerda} e {$direita} deveriam ter a mesma base.");
        }
    }
}
