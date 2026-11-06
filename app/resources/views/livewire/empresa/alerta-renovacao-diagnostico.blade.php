<div @class(['hidden' => $diagnostico === null, 'mb-4' => $comMargem])>
    @if ($diagnostico !== null)
        <x-drm.aviso-inline variant="alerta" class="!mb-0" dusk="renovacao-alerta-{{ $empresaId }}">
            <p class="m-0 mb-2">O último diagnóstico é de {{ \App\Support\DataExibicao::data($diagnostico->gerado_em) }}. Faça um novo para ver como os números mudaram.</p>
            <span class="flex flex-wrap items-center gap-3">
                <x-link :href="route('diagnosticos.novo', $empresaId)" dusk="renovacao-alerta-novo-{{ $empresaId }}">Fazer novo diagnóstico</x-link>
                @unless ($personificando)
                    <button type="button" class="link bg-transparent border-0 p-0 cursor-pointer" wire:click="dispensar" dusk="renovacao-alerta-dispensar-{{ $empresaId }}">Dispensar</button>
                @endunless
            </span>
        </x-drm.aviso-inline>
    @endif
</div>
