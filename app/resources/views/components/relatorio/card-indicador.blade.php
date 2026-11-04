@props([
    'codigo',
    'indicador', // array{valor: ?float|int, farol: string, motivo: ?string, mensagem: string}
    'nominais' => [], // STORY-044 — list<array{rotulo, valor}> derivada do quiz_payload do snapshot
])

@php
    use App\Support\Relatorio\IndicadorFormatter;

    $nome = IndicadorFormatter::nome($codigo);
    $valor = $indicador['valor'] ?? null;
    $farol = $indicador['farol'] ?? 'nenhum';
    $mensagem = $indicador['mensagem'] ?? '';
    $indisponivel = $valor === null;
@endphp

{{-- Sem id (a <tr> desktop é a âncora canônica); o JS resolve o alvo visível por
     data-codigo. A classe .indicador-alvo dá scroll-margin + highlight (STORY-040). --}}
<article class="indicador-alvo rounded-[length:var(--radius-md)] border border-[color:var(--color-border)] bg-[color:var(--color-surface)] p-4 shadow-[var(--shadow-sm)]"
         data-codigo="{{ $codigo }}"
         @class(['opacity-70' => $indisponivel])>
    <header class="flex items-start justify-between gap-3 mb-2">
        <h3 class="text-[color:var(--color-primary)] font-medium text-base m-0">{{ $nome }}</h3>
        @if (! $indisponivel)
            <x-relatorio.farol :cor="$farol"/>
        @endif
    </header>

    <p class="text-[color:var(--color-primary)] text-2xl font-medium tabular-nums m-0 mb-2">
        @if ($indisponivel)
            <span class="text-[color:var(--color-secondary)] italic text-base">Indisponível</span>
        @else
            {{ IndicadorFormatter::valor($codigo, $valor) }}
            <x-relatorio.valores-nominais :codigo="$codigo" :nominais="$nominais"/>
        @endif
    </p>

    <p class="text-[color:var(--color-secondary)] text-sm m-0">{{ $mensagem }}</p>
</article>
