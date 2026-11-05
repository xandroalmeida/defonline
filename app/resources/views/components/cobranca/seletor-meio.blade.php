{{--
    <x-cobranca.seletor-meio> — DDR-010 / spec STORY-070 (checkout de créditos e de plano anual).
    `radiogroup` de verdade (fieldset + legend + rádios nativos, setas navegam); o aspecto de controle
    segmentado é estilo. Liga ao `$meio` do componente Livewire pai. Sem "recomendado": nenhuma opção
    vem com destaque além da selecionada.
    $opcoes: lista de [valor, título, apoio].
--}}
@props(['opcoes', 'atual'])
<fieldset class="m-0 p-0 border-0 min-w-0" dusk="screen-checkout-meio">
    <legend class="text-sm font-semibold text-[color:var(--color-primary)] mb-2 p-0">Como você quer pagar?</legend>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($opcoes as [$valor, $titulo, $apoio])
            <label class="relative block cursor-pointer">
                <input type="radio" class="peer sr-only" name="meio" value="{{ $valor }}" wire:model.live="meio"
                       @checked($atual === $valor) dusk="screen-checkout-meio-{{ $valor }}">
                <span class="flex flex-col justify-center min-h-[44px] rounded-md border-2 border-[color:var(--color-border)] px-3 py-2 text-sm
                             text-[color:var(--color-secondary)]
                             peer-checked:border-[color:var(--color-tertiary)] peer-checked:bg-[color:var(--color-surface)] peer-checked:text-[color:var(--color-primary)]
                             peer-focus-visible:ring-2 peer-focus-visible:ring-[color:var(--color-ring)]">
                    <span class="font-medium">{{ $titulo }}</span>
                    <span>{{ $apoio }}</span>
                </span>
            </label>
        @endforeach
    </div>
    <p class="sr-only" aria-live="polite">{{ $atual === 'cartao' ? 'Campos do cartão exibidos' : 'Pagamento por Pix selecionado' }}</p>
</fieldset>
