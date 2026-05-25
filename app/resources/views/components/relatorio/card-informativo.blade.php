@props([
    'codigo',
    'indicador', // array{valor: ?float|int, farol: 'nenhum', motivo: ?string, mensagem: string}
])

@php
    use App\Support\Relatorio\IndicadorFormatter;

    $valor = $indicador['valor'] ?? null;
    $mensagem = $indicador['mensagem'] ?? '';
    $indisponivel = $valor === null;
    $tituloId = $codigo.'-titulo';
@endphp

<section aria-labelledby="{{ $tituloId }}"
         class="rounded-[length:var(--radius-lg)] border border-[color:var(--color-border)] bg-[color:var(--color-neutral)] p-5 sm:p-6 shadow-[var(--shadow-sm)]"
         data-codigo="{{ $codigo }}">
    <div class="flex items-start justify-between gap-3 mb-3">
        <div>
            <p class="text-[color:var(--color-secondary)] uppercase tracking-wider text-xs font-semibold m-0">Indicador informativo</p>
            <h2 id="{{ $tituloId }}" class="text-[color:var(--color-primary)] text-xl font-medium m-0 mt-1">
                {{ IndicadorFormatter::nome($codigo) }}
            </h2>
        </div>
        <x-relatorio.farol cor="nenhum"/>
    </div>

    <p class="text-[color:var(--color-primary)] text-3xl sm:text-4xl font-light tabular-nums m-0 mb-2">
        @if ($indisponivel)
            <span class="text-[color:var(--color-secondary)] italic text-lg">Indisponível</span>
        @else
            {{ IndicadorFormatter::valor($codigo, $valor) }}
        @endif
    </p>

    <p class="text-[color:var(--color-secondary)] text-sm m-0">{{ $mensagem }}</p>
</section>
