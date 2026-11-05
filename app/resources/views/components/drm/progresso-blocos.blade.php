{{--
    <x-drm.progresso-blocos> — barra segmentada + linha textual (DDR-001 item 2).
    Os segmentos são decorativos; a informação real está em `Bloco N de M · Nome`.
--}}
@props(['atual', 'total', 'nome'])
<div class="mb-6">
    <div class="flex items-center gap-2" role="progressbar"
         aria-valuemin="1" aria-valuemax="{{ $total }}" aria-valuenow="{{ $atual }}"
         aria-label="Progresso do lançamento">
        @for ($i = 1; $i <= $total; $i++)
            <div class="flex-1 h-2 rounded-full {{ $i <= $atual ? 'bg-[color:var(--color-tertiary)]' : 'bg-[color:var(--color-neutral)]' }}"
                 aria-hidden="true" dusk="drm-lancamento-progresso-{{ $i }}"></div>
        @endfor
    </div>
    <p class="mt-2 mb-0 text-sm text-[color:var(--color-secondary)]">Bloco {{ $atual }} de {{ $total }} · {{ $nome }}</p>
</div>
