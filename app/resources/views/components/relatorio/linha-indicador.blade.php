@props([
    'codigo',
    'indicador', // array{valor: ?float|int, farol: string, motivo: ?string, mensagem: string}
    'nominais' => [], // STORY-044 — list<array{rotulo, valor}> derivada do quiz_payload do snapshot
    'motorVersion' => null, // versão do snapshot: o NCG/Vendas muda de escala no motor 2.0.0
])

@php
    use App\Support\Relatorio\IndicadorFormatter;

    $nome = IndicadorFormatter::nome($codigo);
    $valor = $indicador['valor'] ?? null;
    $farol = $indicador['farol'] ?? 'nenhum';
    $mensagem = $indicador['mensagem'] ?? '';
    $indisponivel = $valor === null;
@endphp

<tr class="border-b border-[color:var(--color-border)] last:border-b-0 align-top"
    data-codigo="{{ $codigo }}"
    @class(['opacity-70' => $indisponivel])>
    <th scope="row" class="py-3 pr-4 text-left text-[color:var(--color-primary)] font-medium">
        {{ $nome }}
    </th>
    {{-- `whitespace-nowrap` fica só no valor do indicador; o bloco nominal precisa
         quebrar linha, senão a tabela densa passa a rolar na horizontal (CA-7). --}}
    <td class="py-3 pr-4 text-[color:var(--color-primary)] tabular-nums">
        @if ($indisponivel)
            <span class="text-[color:var(--color-secondary)] italic">Indisponível</span>
        @else
            <span class="whitespace-nowrap">{{ IndicadorFormatter::valor($codigo, $valor, $motorVersion) }}</span>
            <x-relatorio.valores-nominais :codigo="$codigo" :nominais="$nominais"/>
        @endif
    </td>
    <td class="py-3 pr-4">
        @if (! $indisponivel)
            <x-relatorio.farol :cor="$farol"/>
        @else
            <span class="sr-only">Sem farol — indicador indisponível</span>
        @endif
    </td>
    <td class="py-3 text-[color:var(--color-secondary)] text-sm">
        {{ $mensagem }}
    </td>
</tr>
