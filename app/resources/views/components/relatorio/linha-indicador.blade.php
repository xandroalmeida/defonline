@props([
    'codigo',
    'indicador', // array{valor: ?float|int, farol: string, motivo: ?string, mensagem: string}
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
    <td class="py-3 pr-4 text-[color:var(--color-primary)] tabular-nums whitespace-nowrap">
        @if ($indisponivel)
            <span class="text-[color:var(--color-secondary)] italic">Indisponível</span>
        @else
            {{ IndicadorFormatter::valor($codigo, $valor) }}
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
