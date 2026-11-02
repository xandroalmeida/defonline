---
pdr_id: PDR-010
slug: cadastro-empresa-cnpj-first-dados-rfb-imutaveis
title: Cadastro de Empresa Analisada começa pelo CNPJ; dados da Receita são imutáveis e completos
status: accepted
decided_at: 2026-11-02
decided_by: PO (Alexandro / Claude)
supersedes: null
superseded_by: null
related_epics: [EPIC-001]
related_adrs: [ADR-002, ADR-004]
related_idrs: [IDR-004, IDR-005]
---

# PDR-010 — Cadastro de Empresa Analisada começa pelo CNPJ; dados da Receita são imutáveis e completos

## Contexto

O cadastro de Empresa Analisada (STORY-014 + STORY-015) abre um formulário de 9 campos. O botão "Consultar Receita" é opcional e pré-preenche só 7 deles. O usuário pode sobrescrever tudo o que veio da Receita. Se a consulta falha, ele digita os dados à mão. O resultado é uma base de empresas em que razão social, CNAE e situação cadastral podem ter sido digitados livremente e divergir da RFB. Faltam os dados que o DRM (EPIC-010, MEI e Simples Nacional) e o diagnóstico precisam ler: as atividades secundárias, o endereço completo, o quadro de sócios e o regime tributário (MEI/SIMEI, Simples Nacional).

## Opções consideradas

### Opção 1 — CNPJ primeiro, dados RFB completos e só-leitura (escolhida)
- O usuário informa só o CNPJ. O sistema consulta a Receita e mostra a ficha completa para confirmação. Nada do que veio da RFB é editável. O usuário pode acrescentar observações próprias.
- Prós: dado confiável; menos digitação; o regime tributário fica disponível para o DRM.
- Contras: se a Receita estiver indisponível, o cadastro por CNPJ fica bloqueado até a consulta voltar.

### Opção 2 — Cadastro provisório quando a Receita falha
- O usuário digita só a razão social, a empresa fica "pendente de confirmação" e é reconsultada depois.
- Contras: estado intermediário em todas as telas; o diagnóstico poderia rodar sobre empresa não confirmada.

### Opção 3 — Status quo (pré-preenchimento editável com fallback manual)
- Consequência: a base continua não confiável e sem regime, CNAEs secundários nem sócios.

## Decisão

> **Optamos pela Opção 1.**

1. **CNPJ primeiro.** A tela `/empresas/nova` pede apenas o CNPJ. Os demais dados vêm da consulta à RFB.
2. **Ficha completa.** A ficha traz razão social, nome fantasia, situação cadastral e data, data de abertura, natureza jurídica, porte, capital social, CNAE principal e **todos** os CNAEs secundários (código e descrição), endereço completo (logradouro, número, complemento, bairro, CEP, município, UF), quadro de sócios e administradores (nome, qualificação, data de entrada; documento só como a RFB publica, mascarado), e regime: **optante MEI/SIMEI** e **optante Simples Nacional**, cada um com data de opção e data de exclusão quando houver.
3. **Dados RFB imutáveis.** O usuário não altera nenhum campo vindo da Receita, nem no cadastro nem depois. A única forma de mudá-los é reconsultar a Receita.
4. **Observações do usuário.** Campo de texto livre, opcional e editável a qualquer momento. Fica sempre visualmente separado dos dados oficiais.
5. **Falha na consulta bloqueia o cadastro por CNPJ.** Mensagem: "Não conseguimos consultar a Receita agora. Tente novamente em alguns minutos." Não há fallback manual para CNPJ. CNPJ inexistente tem mensagem própria.
6. **CPF continua manual.** O autônomo não formalizado entra por um link "Não tenho CNPJ (sou autônomo)" e cai no formulário manual atual, com os campos editáveis.
7. **Atualizar da Receita.** A tela da empresa ganha a ação "Atualizar dados da Receita", que reconsulta e substitui a ficha inteira. É assim que as empresas já cadastradas (manuais ou com o enriquecimento antigo de 7 campos) entram na regra nova. Não haverá reconsulta em lote.

## Justificativa

O DEFOnline vende um diagnóstico **confiável**. A identidade da empresa é a primeira coisa no cabeçalho do relatório (espec §3.4) e não pode ser digitada livremente. O regime MEI/Simples é o recorte do DRM (PDR-003). Ter esse dado vindo da RFB evita perguntá-lo ao usuário, que muitas vezes não sabe a resposta. Bloquear quando a consulta falha custa alguns minutos de espera em um evento raro (fallback para dois provedores, IDR-004), em troca de nunca ter um dado oficial errado.

## Consequências

### Positivas
- Cadastro por CNPJ vira um campo e um clique de confirmação.
- A base de empresas passa a ter regime, CNAEs secundários, endereço e sócios para uso futuro (DRM, captação, relatório).

### Negativas / trade-offs aceitos
- **Contraria a NRF §3.1 e a espec §3.3 Passo 2 / §1.5.2** ("falha na API não impede o cadastro"; "o Usuário pode editar a Empresa Analisada"). Para CNPJ, este PDR prevalece. Para CPF, a espec segue valendo.
- A disponibilidade do cadastro por CNPJ passa a depender dos provedores RFB. O endpoint aberto da cnpja tem 3 consultas/min (STORY-018). Se o volume crescer, será preciso o plano pago ou o fallback entre provedores.
- O quadro de sócios da RFB **não** pré-preenche os sócios do quiz (Q21–Q23 / STORY-038): a RFB publica o CPF mascarado.
- Sócios são dado pessoal. São públicos na RFB, mas seguem tratados como PII (nunca em log ou evento sem máscara).

### Para o time técnico
- Modelo de dados para CNAEs secundários, endereço, sócios e regime: decisão do Programador/Arquiteto dentro da ADR-003.
- Verificar se os dois provedores (cnpja, receitaws) entregam todos os campos da decisão 2 e registrar em IDR o que faltar.
- Impacto em épicos: EPIC-001 (evolução pós-fechamento, sem reabrir) e EPIC-010 (o regime fica disponível).

## Sinais de revisão

- A taxa de bloqueio por falha da Receita passa de 2% das tentativas de cadastro por CNPJ em uma semana (`business_metrics` rfb_consulta_*).
- Os usuários reportam dado da Receita desatualizado que precisavam corrigir para usar o diagnóstico.
