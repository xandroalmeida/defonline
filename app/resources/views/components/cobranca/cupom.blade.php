@props(['aplicado' => null, 'concedido' => false, 'aberto' => false, 'erro' => null, 'esgotou' => false])

{{-- STORY-083 / spec STORY-070-cupom-no-checkout §2-§4: campo colapsado; o concedido vem aplicado e sem campo. --}}
<div class="flex flex-col gap-2" dusk="screen-checkout-cupom">
    @if ($esgotou)
        <x-drm.aviso-inline variant="alerta" dusk="screen-checkout-cupom-esgotou">
            Este cupom acabou de atingir o limite de usos. O valor voltou ao preço cheio — confira antes de pagar.
        </x-drm.aviso-inline>
    @endif

    @if ($concedido && $aplicado)
        <x-drm.aviso-inline variant="info" dusk="screen-checkout-cupom-concedido">
            Cupom {{ $aplicado->cupom->codigo }} aplicado à sua conta: {{ $aplicado->descricao() }}.
        </x-drm.aviso-inline>
    @elseif ($aplicado)
        <div class="flex flex-wrap items-center gap-3 text-sm text-[color:var(--color-primary)]" aria-live="polite" dusk="screen-checkout-cupom-aplicado">
            <span>&#10003; {{ $aplicado->cupom->codigo }} aplicado — {{ $aplicado->descricao() }}</span>
            <x-button type="button" variant="secondary" size="sm" wire:click="removerCupom" dusk="screen-checkout-cupom-remover">Remover cupom</x-button>
        </div>
    @elseif (! $aberto)
        <div>
            <button type="button" class="link bg-transparent border-0 p-0 cursor-pointer text-sm min-h-[44px]" wire:click="abrirCupom" dusk="screen-checkout-cupom-toggle">Tem cupom? Adicionar cupom</button>
        </div>
    @else
        <div class="flex flex-col gap-1">
            <label for="campo-cupom" class="text-sm text-[color:var(--color-primary)]">Código do cupom</label>
            <div class="flex flex-wrap gap-2 items-start">
                <input id="campo-cupom" type="text" wire:model="campoCupom" wire:keydown.enter.prevent="aplicarCupom" placeholder="Ex.: BETA50" autocomplete="off"
                       style="text-transform: uppercase" dusk="screen-checkout-cupom-campo"
                       aria-describedby="@if ($erro)cupom-erro @endif"
                       class="min-h-[44px] rounded-md border border-[color:var(--color-border)] px-3 text-sm">
                <x-button type="button" variant="secondary" wire:click="aplicarCupom" wire:loading.attr="disabled" wire:target="aplicarCupom" dusk="screen-checkout-cupom-aplicar">
                    <span wire:loading.remove wire:target="aplicarCupom">Aplicar</span>
                    <span wire:loading wire:target="aplicarCupom">Atualizando…</span>
                </x-button>
            </div>
            @if ($erro)
                <p id="cupom-erro" class="m-0 text-sm text-[color:var(--color-destructive)]" role="alert" dusk="screen-checkout-error-cupom">{{ $erro }}</p>
            @endif
        </div>
    @endif
</div>
