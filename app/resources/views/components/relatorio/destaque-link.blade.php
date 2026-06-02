@props([
    'codigo', // string — código do indicador (ex.: 'ncg_vendas')
    'texto',  // string — texto completo do destaque (§4.7.1)
])

{{--
 STORY-040 — destaque clicável do Resumo Executivo.

 É um <a href="#indicador-{codigo}"> nativo: link semântico (role=link), focável
 por teclado, acionável com Enter sem JS. `data-indicador-link` é o gancho do
 progressive enhancement em app.js (rola até o alvo VISÍVEL no breakpoint atual e
 aplica o highlight de ~1s). Sem JS, o href ainda faz o jump (CA-6).

 Afordância de clicabilidade (CA-4): sublinhado pontilhado em tertiary + cursor
 pointer nativo; no hover/foco vira sólido. O sufixo sr-only explicita o destino
 para leitores de tela.
--}}
<a href="#indicador-{{ $codigo }}"
   data-indicador-link="{{ $codigo }}"
   data-testid="resumo-destaque-link"
   class="text-[color:var(--color-primary)] underline decoration-dotted decoration-[color:var(--color-tertiary)] underline-offset-2 hover:decoration-solid focus-visible:decoration-solid">{{ $texto }}<span class="sr-only"> (clique para ver os detalhes na tabela)</span></a>
