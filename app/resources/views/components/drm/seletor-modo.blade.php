{{--
    <x-drm.seletor-modo> — DDR-002. `radiogroup` de verdade (fieldset + legend + dois rádios);
    o aspecto de controle segmentado é estilo. O custo em campos é o argumento, e já vem
    contado depois da supressão por setor. O rádio fica na opção ATUAL enquanto a confirmação
    destrutiva está aberta (não antecipa uma troca que ainda não aconteceu): por isso o clique é
    `.prevent` e quem marca o rádio é o estado do servidor (`$wire.modos`), via `x-bind:checked`.
    Selecionada: borda tertiary (só borda e marcador, 3:1) + rótulo primary — tertiary sobre
    neutral reprova AA como texto.
--}}
@props(['grupo', 'modo', 'camposAgregado' => 1, 'camposDetalhado'])
@php
    $opcoes = [
        'agregado' => ['Informar o total', $camposAgregado],
        'detalhado' => ['Detalhar por item', $camposDetalhado],
    ];
@endphp
<fieldset class="m-0 mb-4 p-0 border-0 min-w-0" dusk="drm-lancamento-modo-{{ $grupo }}">
    <legend class="text-sm font-semibold text-[color:var(--color-primary)] mb-2 p-0">Como você quer informar?</legend>
    <div class="grid grid-cols-2 gap-1 rounded-md bg-[color:var(--color-neutral)] p-1">
        @foreach ($opcoes as $valor => [$rotulo, $campos])
            <label class="relative block cursor-pointer">
                <input type="radio" class="peer sr-only" name="modo-{{ $grupo }}" value="{{ $valor }}"
                       @checked($modo === $valor)
                       x-on:click.prevent
                       x-bind:checked="$wire.modos['{{ $grupo }}'] === '{{ $valor }}'"
                       wire:click="alternarModo('{{ $grupo }}', '{{ $valor }}')"
                       dusk="drm-lancamento-modo-{{ $grupo }}-{{ $valor }}">
                <span class="flex flex-col justify-center min-h-[44px] rounded-md border-2 border-transparent px-3 py-2
                             text-[color:var(--color-secondary)]
                             peer-checked:border-[color:var(--color-tertiary)] peer-checked:bg-[color:var(--color-surface)] peer-checked:text-[color:var(--color-primary)]
                             peer-focus-visible:ring-2 peer-focus-visible:ring-[color:var(--color-ring)]">
                    <span class="font-medium text-sm">{{ $rotulo }}</span>
                    <span class="text-xs">{{ $campos }} {{ $campos === 1 ? 'campo' : 'campos' }}</span>
                </span>
            </label>
        @endforeach
    </div>
    <p class="mt-2 mb-0 text-sm text-[color:var(--color-secondary)]">
        Com o total, a demonstração do mês fica correta do mesmo jeito. Detalhando, o DRM também consegue apontar qual item subiu.
    </p>
</fieldset>
