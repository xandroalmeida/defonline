---
id: SCREEN-STORY-070-trial
story: STORY-070-design-telas-cobranca
implemented_by: STORY-074
epic: EPIC-005-cobranca-e-planos
status: draft
created_at: 2026-11-05
updated_at: 2026-11-05
owner_designer: designer (claude-sonnet-5-5)
related_ddrs: [DDR-009, DDR-005]
ds_components_used: [page-header, card, button, pill, aviso-inline]
exceptions_to_ds: []
viewports: [mobile, desktop]
---

# Spec de tela — Teste gratuito: oferta, ativação, andamento e fim

> Referência: `STORY-070`; implementada por `STORY-074`. Dias e análises vêm da configuração; os "7 dias / 1 análise" abaixo são ilustrativos. Microcopy provisória.

## 1. Objetivo
Roberto ativa o teste em **um toque**, sabe o que tem e até quando, e é convidado — sem pressão — a assinar quando acaba.

## 2. Fluxo
- Oferta aparece: (a) na Home, para usuário elegível sem plano; (b) em `Planos e preços`; (c) no bloqueio de análise.
- Ativação: `Começar teste grátis` → diálogo de confirmação não necessário (sem custo, sem cartão). A ativação leva direto a `Fazer uma análise`.
- Andamento: indicação em Meu plano e `aviso-inline` na Home. Fim: bloqueio das funções pagas, sem cobrança.

## 3. Layout

### Oferta (cartão) — mobile e desktop
```
| Teste grátis                                     |
| 7 dias, 1 análise e o Raio X do Negócio.         |
| Sem cartão e sem cobrança no fim.                |
| [ Começar teste grátis ]                         |
```
Sem timer, sem contagem, sem "oferta limitada".

### Confirmação da ativação
```
| Seu teste começou                                |
| Você tem até 12/11/2026 para fazer 1 análise e   |
| lançar no Raio X do Negócio.                     |
| [ Fazer uma análise ]  [ Cadastrar uma empresa ] |
```
Se o usuário ainda não tem Empresa Analisada, o primário é `Cadastrar uma empresa`.

### Avisos durante o teste (DDR-009)
| Quando | Tela | Severidade | Texto |
|---|---|---|---|
| teste ativo | Home | `info` | `Teste grátis: termina em 12/11/2026 · {n} análise(s) restante(s).` + `Ver planos` |
| D-2 | Home e Meu plano | `info` | `Seu teste termina em 10/11/2026 (em 2 dias). Assine para continuar usando o que já faz.` + `Assinar um plano` |
| D-0 | Home e Meu plano | `info` | `Seu teste termina hoje.` + `Assinar um plano` |
| análise usada | Home | `info` | `Você usou a análise do teste. O acesso ao Raio X continua até 12/11/2026.` + `Ver planos` |
| encerrado | Home, Meu plano, bloqueio | `bloqueio` | `Seu teste terminou em 12/11/2026. Seus diagnósticos continuam disponíveis.` + `Ver planos` |

(Os e-mails de D-2 e D-0, CA-6, têm o mesmo texto e o mesmo CTA.)

## 4. Estados
- **Feliz:** acima.
- **Loading:** `Começar teste grátis` → `Ativando…` (desabilitado).
- **Vazio:** n/a. Trial desativado (CA-4): oferta não existe em lugar nenhum; se alguém tenta ativar por URL, `bloqueio`: `O teste grátis não está disponível no momento.` + `Ver planos`.
- **Erro / inelegível (CA-2):** mensagem que explica o motivo e oferece planos, sem acusar:
  - CPF já usou: `Já existe um teste gratuito ativado com este CPF, então não conseguimos abrir outro.`
  - CNPJ já analisado em teste de outra conta: `Esta empresa já foi analisada em um teste gratuito, então não conseguimos incluir outro teste para ela.`
  - Ação para ambos: `Ver planos e preços`. A frase não revela de qual conta nem de que dia.
- **Erro inesperado:** `Não conseguimos ativar o teste agora. Tente de novo em instantes.` + `Tentar de novo`.
- **Já em teste:** oferta some; aparece a indicação de andamento.
- **Assinou durante o teste (CA-8):** `aviso-inline` `info` na confirmação da assinatura, ver `checkout-assinatura` §4.7.
- **Mudança na configuração (CA-3):** sem estado visual; as datas mostradas são as do teste do usuário.
- **Sem permissão ("Logar como"):** botão de ativar `disabled` com o aviso padrão do administrador.
- **Interruptor desligado (CA-9):** nada disso aparece.

## 5. Microcopy
Nas tabelas acima. Adicionais: `Seu teste começou` · `Você tem até {data} para fazer {n} análise(s) e lançar no Raio X do Negócio.` · `Fazer uma análise` · `Cadastrar uma empresa` · `Ativando…`.

## 6. Acessibilidade
Os avisos têm `role="status"` (info) / `role="alert"` (bloqueio). Datas escritas por extenso no texto acessível (`12 de novembro de 2026`). Foco na ativação vai ao `<h1>` da confirmação.

## 7. Identificadores E2E
`screen-trial-oferta` · `screen-trial-ativar` · `screen-trial-confirmacao` · `screen-trial-aviso` · `screen-trial-error-inelegivel` · `screen-trial-encerrado`.

## 8. Exceções ao DS
Nenhuma.

## 9. Dependências e perguntas
- Elegibilidade (CPF, CNPJ, IP) é do back-end; a tela só mostra o motivo. O alerta de IP (CA-7) é interno e **não** aparece ao usuário.
- **Pergunta:** o teste exige e-mail confirmado antes de ativar? Padrão adotado: não (nada é cobrado); a análise em si segue as regras do produto.

## 10. Histórico
| Data | Mudança | Quem | Motivo |
|---|---|---|---|
| 2026-11-05 | criação | designer (claude-sonnet-5-5) | STORY-070 |
