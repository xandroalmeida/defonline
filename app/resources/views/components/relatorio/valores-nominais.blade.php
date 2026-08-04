@props([
    'codigo',
    'nominais' => [], // list<array{rotulo: string, valor: string}> — vindo de App\Support\Relatorio\ValoresNominais
])

{{--
    STORY-044 — valores nominais de apoio ao indicador.

    Nada é renderizado quando a lista é vazia: indicador sem origem monetária
    (prazos em dias, inadimplência) e snapshot antigo sem os campos no
    `quiz_payload` caem no mesmo caminho — sem placeholder, sem "N/A" (CA-3/CA-8).

    Acessibilidade (CA-9): o bloco fica DENTRO da linha (`<tr>`) ou do card do
    indicador, cuja `<th scope="row">`/`<h3>` já nomeia o indicador. Cada termo é
    lido como "Rótulo: valor", sem `aria-label` extra — rótulo duplicado em
    atributo faria o leitor de tela repetir a mesma informação duas vezes.

    Hierarquia (CA-5): token secundário + escala `xs`, contra o indicador em
    `--color-primary` e escala maior. `whitespace-normal` reverte o `nowrap`
    herdado da célula da tabela — sem isso a tabela ganharia scroll horizontal.

    É `<span>` (e não `<div>`) porque em um dos usos o bloco vive dentro de um
    `<p>`, onde conteúdo de fluxo em bloco é HTML inválido.
--}}
@if (! empty($nominais))
    <span class="mt-1 flex flex-col gap-y-0.5 whitespace-normal font-normal text-[color:var(--color-secondary)] text-xs"
          data-testid="nominais-{{ $codigo }}">
        @foreach ($nominais as $termo)
            {{-- O número nunca parte no meio: se faltar largura, a quebra cai
                 entre o rótulo e o valor, nunca dentro de "R$ 1.200.000,00". --}}
            <span>{{ $termo['rotulo'] }}: <span class="tabular-nums whitespace-nowrap">{{ $termo['valor'] }}</span></span>
        @endforeach
    </span>
@endif
