---
id: SCREEN-STORY-070-bloqueio-sem-saldo
story: STORY-070-design-telas-cobranca
implemented_by: STORY-073
epic: EPIC-005-cobranca-e-planos
status: ready
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-009, DDR-001, DDR-005]
ds_components_used: [page-header, card, button, aviso-inline, pill]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Bloqueio por falta de cota, créditos ou teste

> Referência: `STORY-070`; implementada por `STORY-073` (análise) e, para o Raio X do Negócio, por `STORY-084`. Só aparece com o interruptor de cobrança **ligado**; desligado, o fluxo é o de hoje. Microcopy provisória.

## 1. Objetivo
Quando não há saldo, dizer **por quê, o que fazer e que nada se perdeu**, sem culpar e sem esconder o que continua disponível.

## 2. Fluxo
- Quando aparece: ao iniciar a análise (`Diagnósticos → selecionar empresa → Iniciar`) ou ao concluir o quiz sem saldo (CA-3 da 073). Para o Raio X: ao tentar lançar um mês.
- Não é modal nem toast: é uma **página/bloco no lugar do fluxo**, para que haja o que ler e a que voltar.
- Ações: levam a outro lugar (`Ver planos`, `Comprar créditos`, `Atualizar cartão`, `Começar teste grátis`), nunca "continuar assim mesmo" (DDR-001, bloqueio).
- Retorno: depois de contratar/comprar, o usuário volta ao ponto onde parou (`Continuar o diagnóstico`).

## 3. Layout

### Mobile (≥360px) e desktop (coluna única de até 640px)
```
+--------------------------------------------------+
| Você não tem análises disponíveis                |   ← título conforme a causa
|                                                  |
| ┌ bloqueio ───────────────────────────────────┐  |
| │ {causa em uma frase}                        │  |
| │ {o que fazer, em uma frase}                 │  |
| └─────────────────────────────────────────────┘  |
|                                                  |
| ✓ Suas respostas estão salvas. Quando você       |
|   resolver, continua de onde parou.   (só se há  |
|                                         rascunho)|
|                                                  |
| [ ação primária ]  [ ação secundária ]           |
|                                                  |
| Seus diagnósticos anteriores continuam           |
| disponíveis.  [ Ver diagnósticos ]               |
+--------------------------------------------------+
```
- `aviso-inline` `bloqueio` (sem botão de seguir). As ações são botões **fora** do aviso, em barra de ações, para a regra "nenhum caminho para frente no mesmo fluxo" permanecer legível.
- A frase `Suas respostas estão salvas` só aparece se houver rascunho de quiz (CA-3 da 073). O link `Ver diagnósticos` mantém o acesso aos relatórios já emitidos (CA-9).

## 4. Causas — o que a tela exibe em cada uma

| Causa | Título | Frase | Ação primária | Secundária |
|---|---|---|---|---|
| Nunca teve nada, trial elegível | Você ainda não tem análises | Para fazer uma análise, comece o teste grátis ou escolha um plano. | Começar teste grátis | Ver planos |
| Nunca teve nada, trial indisponível/inelegível | Você ainda não tem análises | Escolha um plano ou compre créditos para fazer uma análise. | Ver planos | Comprar créditos |
| Teste: análise usada, prazo vigente | Você usou a análise do teste | O teste termina em {data}. Assine ou compre créditos para fazer outra análise. | Ver planos | Comprar créditos |
| Teste encerrado | Seu teste terminou | Seu teste terminou em {data}. Seus diagnósticos continuam disponíveis. | Ver planos | Comprar créditos |
| Plano ativo, cota esgotada, sem créditos | Você usou as análises deste ciclo | Suas {n} análises do ciclo acabaram. Novas análises em {data}. | Comprar créditos | Trocar de plano |
| Créditos vencidos, sem plano | Seus créditos venceram | Os créditos venceram em {data} e não podem mais ser usados. | Comprar créditos | Ver planos |
| Assinatura em atraso | Sua assinatura está em atraso | Não conseguimos cobrar a renovação. Atualize o cartão para voltar a fazer análises. | Atualizar cartão | Ver meu plano |
| Plano encerrado / ciclo terminou | Seu plano terminou | Seu plano terminou em {data}. Seus diagnósticos continuam disponíveis. | Ver planos | Comprar créditos |
| Desconto acabou, sem cartão | Falta cadastrar um cartão | O desconto terminou em {data}. Cadastre um cartão para continuar. | Cadastrar cartão | Ver meu plano |

### Variante Raio X do Negócio (STORY-084)
| Causa | Título | Frase | Primária |
|---|---|---|---|
| Sem plano nem teste (inclui "só créditos") | O Raio X do Negócio faz parte dos planos | Os créditos avulsos não incluem o Raio X do Negócio. Assine um plano ou comece o teste para lançar novos meses. Os que você já lançou continuam visíveis. | Ver planos (e `Começar teste grátis` se elegível) |
| Plano em atraso / encerrado / teste encerrado | O lançamento do Raio X está pausado | `{causa}`. Os meses já lançados continuam visíveis. | Atualizar cartão / Ver planos |

Lançar no Raio X **não consome cota**; a tela nunca usa o vocabulário de saldo para ele.

## 5. Estados
- **Feliz** (bloqueio ativo): acima.
- **Loading:** a decisão de bloquear vem do servidor antes de renderizar; sem estado intermediário que pisque o quiz (esqueleto de página curto).
- **Vazio:** n/a.
- **Erro:** se a carteira não puder ser consultada: `Não conseguimos conferir seu saldo agora. Tente de novo em instantes.` + `Tentar de novo`. **Não** bloqueia nem libera por padrão: tenta de novo.
- **Sem permissão ("Logar como"):** o bloqueio aparece igual, com ações `disabled` e o aviso de administrador.
- **Interruptor desligado:** esta tela não existe.
- **Concorrência (CA-6):** se a análise acabou de ser concluída por outra aba, `Esta análise já foi concluída.` + `Ver diagnóstico` em vez do bloqueio.

## 6. Acessibilidade
`aviso-inline` `bloqueio` com `role="alert"`; foco no `<h1>` ao chegar; as ações são botões/links com nome completo (`Ver planos e preços`). Sem informação só por cor ou ícone.

## 7. Identificadores E2E
`screen-bloqueio` · `-titulo` · `-causa` · `-rascunho-salvo` · `-acao-primaria` · `-acao-secundaria` · `-ver-diagnosticos` · variante `screen-bloqueio-raiox`.

## 8. Exceções ao DS
Nenhuma.

## 9. Dependências e perguntas
- A STORY-073 entrega o bloqueio **antes** de existir a página de planos (075). Até lá, as ações que levam à página ainda inexistente devem ficar ocultas, não quebradas (CA-6 da 075 inclui o link no bloqueio). **Pergunta ao PO:** aceitar que a 073 publique o bloqueio sem as ações até a 075 (padrão adotado, com o interruptor desligado isso nunca aparece ao usuário comum).
- Os motivos acima dependem de a carteira expor a causa (não só "sem saldo"); isso é um **requisito para a STORY-073**: retornar o motivo do bloqueio, não um booleano.

## 10. Histórico
| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
