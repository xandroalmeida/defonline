# DEFOnline — Resumo das Recomendações e Respostas

**Projeto:** Plataforma DEFOnline
**Versão do documento:** 2.0 (alinhado com reset de stack)
**Data:** 19/05/2026
**Responsáveis:** Product Owner + EB Parcerias Ltda
**Documento pai:** [`especificacao-funcional.md`](especificacao-funcional.md) (Seção 7)

**Versão do documento:** 2.0 (alinhado com reset de stack — decisões técnicas não funcionais foram redefinidas).

**Finalidade.** Mapear **toda** crítica, sugestão e ponderação presente nos três documentos de revisão recebidos em 20–22/04/2026 e registrar formalmente o que foi **aceito**, **adiado**, **recusado** (com justificativa) ou **transferido para decisão em aberto** (itens `[DECIDIR]` da Seção 6 da especificação funcional v2).

**Documentos-fonte analisados:**

- **F1.** `Parecer CLAUDE - Especificação Funcional DEF on line v.1.pdf` (20/04/2026, 9 páginas) — análise técnica e estratégica.
- **F2.** `DEF online - Especificação Funcional - observações ebc -220426.pdf` (22/04/2026, 6 páginas) — revisão página-a-página da EBC sobre a especificação funcional v1.
- **F3.** `Parecer CLAUDE - observações ebc.pdf` (22/04/2026, 1 página) — posicionamento EBC sobre o parecer CLAUDE (F1).

**Legenda de status:**

- ✅ **Aceito** — incorporado à v2 da especificação funcional ou aos documentos complementares.
- 🟡 **Em definição** — transferido para Seção 6 (`[DECIDIR]`) da especificação funcional v2.
- 🔵 **Adiado (roadmap)** — registrado em `roadmap-pos-v1.md`.
- ⚪ **Operacional** — tarefa de negócio/marketing/jurídico, fora do código, com responsável externo ao time de desenvolvimento.
- 🔴 **Recusado** — não incorporado, com justificativa explícita.

---

## Sumário

1. [Parte I — Parecer CLAUDE sobre a especificação funcional (F1)](#parte-i--parecer-claude-sobre-a-especificação-funcional-f1)
2. [Parte II — Observações EBC sobre a especificação funcional (F2)](#parte-ii--observações-ebc-sobre-a-especificação-funcional-f2)
3. [Parte III — Observações EBC sobre o parecer CLAUDE (F3)](#parte-iii--observações-ebc-sobre-o-parecer-claude-f3)
4. [Parte IV — Contradições identificadas e como foram resolvidas](#parte-iv--contradições-identificadas-e-como-foram-resolvidas)
5. [Parte V — Checklist de cobertura (auditoria)](#parte-v--checklist-de-cobertura-auditoria)

---

## Parte I — Parecer CLAUDE sobre a especificação funcional (F1)

### I.1 Acertos reconhecidos (pg. 2 do F1)

Não demandam ação — aqui apenas por completude do registro.

| # | Item | Status | Observação |
|---|---|---|---|
| I.1.1 | Separação escopo v1/roadmap | ✅ | Mantida e reforçada na v2. |
| I.1.2 | Versionamento do motor de recomendações | ✅ | Mantido; decisão 6.9 (Seção 6) complementa com "critério de revisão". |
| I.1.3 | Tratamento de divisão por zero ("Indisponível") | ✅ | Marcado como `[VALIDADO]` na Seção 4.5. |
| I.1.4 | FIFO de consumo de créditos | ✅ | Mantido. |
| I.1.5 | Estrutura LGPD adequada | ✅ | Mantida; expandida em §7 do `requisitos-nao-funcionais-e-juridicos.md`. |
| I.1.6 | Personas bem definidas | ✅ | Mantidas; ampliadas com 1.3.4 (Personas 4 e 5 futuras) e 1.3.3 (CRC, parceiros EB/Xandrix). |

### I.2 Omissões Críticas (pg. 2–3 do F1)

| # | Item | Status | Onde foi tratado |
|---|---|---|---|
| I.2.1 | Gestão multi-cliente no plano Pro | ✅ Aceito (parte do MVP) | **Resolvido em 17/05/2026** (v2.1). Modelo de domínio refatorado: **Usuário** → N **Empresas Analisadas** vira a espinha dorsal da plataforma em todos os planos. Sem limite de quantidade por plano (controle é pela cota mensal de análises). Spec funcional §6.1 marcado `[RESOLVIDO]`. Roadmap §1.1 reorientado para "Compartilhamento N:M entre Usuários" (caso colaborativo dono+contador). |
| I.2.2 | SLA e responsabilidade técnica (Termo de Adesão) | ✅ | Documento dedicado `requisitos-nao-funcionais-e-juridicos.md` §8 — premissas para revisão jurídica. EBC: "revisar quando concluirmos a definição das regras do negócio" (F3) — alinhado. |
| I.2.3 | Simplificação do Patrimônio Líquido | ✅ `[VALIDADO]` parcialmente + 🟡 mitigação | EBC decidiu **manter** em 22/04/2026 (F3). A v2 registra como `[VALIDADO]` na Seção 4.5, mas introduz **mitigação** via aviso condicional no rodapé do relatório quando Q06 > 30% do Ativo Total (item 6.7 da Seção 6 — limiar `[DECIDIR]`). |

### I.3 Omissões Importantes (pg. 3 do F1)

| # | Item | Status | Onde foi tratado |
|---|---|---|---|
| I.3.1 | SLA de disponibilidade e performance | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §1 (uptime 99,5%, tempos de resposta p95, capacidade concorrente). |
| I.3.2 | Mecanismo de geração do Resumo Executivo | ✅ Aceito | **Resolvido em 17/05/2026** (v2.2). §4.7.1 da especificação funcional contém algoritmo determinístico em 6 passos: classificação por contagem proporcional, até 3 destaques, severidade pela distância à fronteira amarela, desempate pela ordem do Anexo D, "Indisponível" fora do denominador (≥70% → mensagem fixa), reaproveita mensagem curta da matriz DEZ/2025, sem pesos por indicador no MVP. §6.2 marcado `[RESOLVIDO]`. |
| I.3.3 | Limiar NCG positivo alto crescente | ✅ Aceito | **Resolvido em 17/05/2026** (v2.3). Limiar fixado em **NCG > 10% das Vendas anualizadas** para "positivo alto crescente"; 0–10% é "moderado"; ≤ 0 é "negativo". Adicionalmente, NCG absoluto **vira indicador informativo sem farol visual** no MVP (evita duplicidade com NCG/Vendas #10). 3 textos da matriz DEZ/2025 pg. 33 ficam como extração operacional EBC. §6.3 marcado `[RESOLVIDO]`; detalhes em §4.5, §4.7.1, Anexo D linha #9 e Anexo E. |
| I.3.4 | Infraestrutura e segurança de dados | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §2, §3, §4, §5, §6. |
| I.3.5 | LGPD para Pro operando CNPJ de terceiros | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §7.2 (contador = controlador, EB = operadora, DPA simplificado). Também referenciado na Seção 4.2 da especificação funcional. |
| I.3.6 | Expiração de rascunhos | 🟡 Em definição | Seção 6 item 6.4 — proposta de 90 dias. Também sinalizado no Anexo A §A.4. |

### I.4 Pontos Relevantes (pg. 4 do F1)

| # | Item | Status | Onde foi tratado |
|---|---|---|---|
| I.4.1 | Validações cruzadas DRE × Balanço | 🟡 Em definição | Seção 6 item 6.6 — proposta com 3 regras. EBC: "Ok, sem problema" (F3) — concorda com inclusão. |
| I.4.2 | PME desnecessário para Serviços | ✅ | Aplicado: Q11 agora obrigatório apenas se Q01≠3 (não coletado para Serviços). Anexo A §A.2 atualizado; Anexo D indicador #12 marcado "não se aplica a Serviços"; Anexo E atualizado. EBC prefere "não obrigatório"; Product Owner adotou a solução mais forte (suprimir da UI) para reduzir atrito, conforme recomendação do parecer. |
| I.4.3 | SLA interno de análise de captação | ✅ + 🔵 | SLA aumentado para **5 dias úteis** (Seção 4.9 — EBC sugeriu no F2). Cálculo automático adiado: `roadmap-pos-v1.md` §2.2 (v1.1). |

### I.5 Mercado Potencial e Estratégia (pg. 5–6 do F1)

| # | Item | Status | Observação |
|---|---|---|---|
| I.5.1 | Dimensionamento TAM/SAM/SOM | ⚪ Operacional | Referência para go-to-market e investor relations; não afeta código. |
| I.5.2 | Canal contador como alavanca | ✅ 🔵 Operacional | Estratégia B2B com CRCs e associações registrada em `roadmap-pos-v1.md` §3.4 (v1.1). |
| I.5.3 | Ambiente competitivo (SEBRAE, ERPs) | ⚪ Operacional | Input para posicionamento e marketing. |

### I.6 Pré-lançamento técnico (pg. 5 do F1, §2.2)

| # | Item | Status | Onde foi tratado |
|---|---|---|---|
| I.6.1 | API RFB com fallback manual | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §3.1 e Seção 4.1 da especificação (já presente). |
| I.6.2 | Gateway de pagamento tokenizado + Pix | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §3.2. |
| I.6.3 | Motor determinístico com 14 indicadores validados | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §9.2 — mínimo 10 casos de teste por fórmula. |
| I.6.4 | Gerador de PDF A4 com semáforo colorido | ✅ | Mantido na Seção 4.7 da especificação funcional. |
| I.6.5 | Validação das faixas por especialista externo | ⚪ Operacional | `requisitos-nao-funcionais-e-juridicos.md` §9.3 — pré-go-live. |
| I.6.6 | Revisão jurídica do Termo de Adesão | ⚪ Operacional | `requisitos-nao-funcionais-e-juridicos.md` §8. |
| I.6.7 | Estratégia de conteúdo e SEO (15–20 artigos) | 🔵 | `roadmap-pos-v1.md` §6.3 — pré-go-live e contínuo. |
| I.6.8 | Beta estruturado com 20–30 usuários | ⚪ Operacional | `requisitos-nao-funcionais-e-juridicos.md` §9.5. |

### I.7 Desafios Centrais (pg. 6 do F1)

| # | Item | Status | Observação |
|---|---|---|---|
| I.7.1 | Qualidade dos dados de entrada | ✅ | Mitigado por: tooltip/box de explicação por indicador (Seção 6 item 6.8 e Anexo A §A.6), validações cruzadas (6.6), declaração de responsabilidade do usuário (Seção 3.5 da especificação funcional e §8.1.2 do documento jurídico). Integração com ERP adiada (`roadmap-pos-v1.md` §4.2). |
| I.7.2 | Conversão trial → assinatura | 🔵 | Onboarding ativo em `roadmap-pos-v1.md` §1.3 (v1.1). Decisão de manter/ativar trial em Seção 6 item 6.10. |
| I.7.3 | Retenção após primeiro ciclo (churn) | 🔵 | Metas, alertas, comparativos e benchmarks em `roadmap-pos-v1.md` §1.2 (v1.1/v1.2). |
| I.7.4 | Adoção pelo canal contador | 🔵 Operacional | `roadmap-pos-v1.md` §3.4. |

### I.8 Tabela de riscos R1–R10 (pg. 7 do F1)

Cada risco do parecer CLAUDE recebeu tratamento:

| Risco | Status | Tratamento na v2 |
|---|---|---|
| R1 Diagnóstico equivocado induz decisão errada | ✅ | Termo de Adesão com exoneração redigida por advogado (`requisitos-nao-funcionais-e-juridicos.md` §8); aviso no quiz e no relatório; cláusula de não substituição à análise contábil (§8.1.1). |
| R2 Conversão trial→pago baixa | 🔵 | Onboarding ativo (`roadmap-pos-v1.md` §1.3). |
| R3 Pro subutilizado por ausência de multi-cliente | ✅ | **Resolvido em v2.1** — refatoração do modelo de domínio: Usuário → N Empresas Analisadas é nativo no MVP. Risco neutralizado. |
| R4 Matriz com faixas incorretas | ✅ | Validação externa pré-go-live (`requisitos-nao-funcionais-e-juridicos.md` §9.3); feedback 👍/👎 (`roadmap-pos-v1.md` §1.4); critério de revisão (Seção 6 item 6.9). |
| R5 API RFB instável | ✅ | Fallback manual robusto (Seção 4.1 e §3.1 do doc jurídico); monitoramento proativo (`requisitos-nao-funcionais-e-juridicos.md` §6.4). |
| R6 Concorrente lança produto similar gratuito | ⚪ Operacional | Aprofundamento da parametrização setorial (já é diferencial) e canal contador (`roadmap-pos-v1.md` §3.4). |
| R7 Churn elevado | 🔵 | Funcionalidades de retenção (`roadmap-pos-v1.md` §1.2). |
| R8 Fraude no trial | ✅ | Verificação por CPF/CNPJ (já previsto) + IP (Seção 2.3 atualizada) + CAPTCHA (`requisitos-nao-funcionais-e-juridicos.md` §4.4). |
| R9 Análise manual de captação estourando capacidade | 🔵 | Cálculo automático (`roadmap-pos-v1.md` §2.2). |
| R10 PL simplificado distorcendo empresas com dívida LP | ✅ | Mitigação por aviso condicional no rodapé (Seção 6 item 6.7). |

### I.9 Parte III — Síntese e Recomendação Formal (pg. 8–9 do F1)

As **três omissões críticas** listadas pelo parecer:

1. Multi-cliente Pro — I.2.1 🟡
2. Simplificação PL — I.2.3 ✅ (mantida) + mitigação 🟡
3. Algoritmo do Resumo Executivo — I.3.2 🟡

E os **três requisitos adicionais** (pg. 9):

- Resolução das omissões críticas antes do desenvolvimento — **refletido em §10 Critérios de Aceite para Kickoff** do documento jurídico.
- Sessão de validação das faixas por especialista externo — ✅ `requisitos-nao-funcionais-e-juridicos.md` §9.3.
- Revisão jurídica completa do Termo de Adesão — ✅ `requisitos-nao-funcionais-e-juridicos.md` §8.

---

## Parte II — Observações EBC sobre a especificação funcional (F2)

### II.1 Correções factuais/nominais (aplicadas direto na V2)

| Página | Observação EBC | Status | Aplicação |
|---|---|---|---|
| pg. 3 Anexo D | "14 indicadores" (não 11) | ✅ | Aplicado globalmente: sumário, §1.4.1, §3.6, §4.5, §4.7, Anexo D (agora com 14 linhas), Anexo E, §A.5. |
| pg. 5 §1.1 2º§ | "alta necessidade" (não "demanda") | ✅ | Aplicado em §1.1. |
| pg. 5 §1.2 3ª linha | "poucos segundos ou minutos" | ✅ | Aplicado em §1.2 (lista de diferenciais). |
| pg. 6 §1.3.2 Persona 3 | "relatório periódico" (não "trimestral") | ✅ + 🟡 | Texto ajustado em §1.3.2. Cadência específica em Seção 6 item 6.5 (afeta precificação do Pro). |
| pg. 6 §1.3.3 | Incluir cliente, parceiros EB/Xandrix e CRC | ✅ | Adicionado em §1.3.3. |
| pg. 7 §1.4.1 2ª linha | Termo de Adesão revisto após regras do negócio | ✅ | Explicitado em §1.4.1 e §4.1. |
| pg. 7 §1.4.1 5ª linha | "14 indicadores" | ✅ | Aplicado em §1.4.1. |
| pg. 11 §3.6 cabeçalho | "DEF on line" no lugar de "DefWeb" | ✅ | Aplicado em §3.6 e §4.7. |
| pg. 13 §4.1 campos | Telefone WhatsApp | ✅ | Adicionado em §4.1. |
| pg. 13 §4.1 regras 3ª linha | "contato com suporte por e-mail" | ✅ | Aplicado em §4.1. |
| pg. 13 §4.1 Login | 30 min OK | ✅ (sem mudança necessária) | Confirmado o valor. |
| pg. 13 §4.2 | Termo de Adesão revisto após regras do negócio | ✅ | Aplicado em §4.2. |
| pg. 15 Q11 | Não obrigatório se Q1=3 | ✅ | Aplicado em §4.4 (tabela), Anexo A §A.2 e §A.3. |
| pg. 15 Q14 Obs | Texto completo da aba QUIZ | ✅ | Aplicado em §4.4 e Anexo A §A.2. |
| pg. 15 Q15 Obs | Texto completo da aba QUIZ | ✅ | Aplicado em §4.4 e Anexo A §A.2. |
| pg. 15 Q16 Obs | Acrescentar "de cartões e desconto de boletos" | ✅ | Aplicado em §4.4 e Anexo A §A.2. |
| pg. 15 Q20 formato | "e < Q09" | ✅ | Aplicado em §4.4, Anexo A §A.2 e §A.3 (validação rígida). |
| pg. 15 Q21 a Q23 | Obrigatórios se Q17=1 | ✅ | Aplicado em §4.4, Anexo A §A.2 e §A.3. |
| pg. 16 §4.5 | "O motor transforma 16 das 23 respostas..." | ✅ | Aplicado em §4.5. |
| pg. 16 §4.5 PL | Manter simplificação (VALIDADO) | ✅ | Marcado `[VALIDADO]` em §4.5 + mitigação (6.7). |
| pg. 16 §4.5 NCG | Usar recomendações pg. 33 para NCG positiva | ✅ + 🟡 | Referência adicionada em §4.5; limiar "positivo alto crescente" em 6.3. |
| pg. 17 §4.7 cabeçalho | "DEF on line" | ✅ | Aplicado em §4.7. |
| pg. 18 §4.8 Retenção | Medianas setoriais futuras | ✅ | Menção adicionada em §4.8; detalhe em `roadmap-pos-v1.md` §2.1. |
| pg. 18 §4.9 | Aumentar para 5 dias úteis | ✅ | Aplicado em §4.9. |
| pg. 20 §5-D | 14 indicadores | ✅ | Aplicado no mapa de anexos. |
| pg. 22 Q11 | Não obrigatório se Q1=3 | ✅ | (Duplicado; já aplicado via §4.4 e Anexo A.) |
| pg. 22 Q14-Q16 Obs | Textos completos | ✅ | (Duplicado; já aplicado.) |
| pg. 22 Q20 | "e < Q09" | ✅ | (Duplicado.) |
| pg. 22 Q21-Q23 | Se Q17=1 | ✅ | (Duplicado.) |
| pg. 23 A.3 Regras, Captação | "Q18 a Q23" | ✅ | Aplicado em Anexo A §A.3. |
| pg. 23 A.3 Regras, CPF | Obrigatórios se Q17=1 | ✅ | Aplicado em Anexo A §A.3. |
| pg. 23 A.5 | "16 dos 23 campos ... 14 indicadores" | ✅ | Aplicado em §4.5 e Anexo A §A.5. |
| pg. 24 Anexo C | Excluir ROL, alterar para "Vendas (ROB)" | ✅ | Aplicado no Anexo C. |
| pg. 25 Anexo D | 14 indicadores, +3 linhas individualizadas | ✅ | Anexo D reescrito com 14 linhas (split do antigo item 11 em PMC, PME, PMR, Inadimplência). |
| pg. 26 Anexo E, PME Serviços | Não se aplica | ✅ | Anexo E atualizado: PME separado por setor; Serviços marcado como "não se aplica". |
| pg. 27 Anexo E, NCG | Pg. 33 para positiva, sem farol para negativa | ✅ | Anexo E atualizado: NCG ≤ 0 → sem farol; positivo → matriz DEZ/2025; limiar `positivo alto crescente` em 6.3. |
| pg. 35 G.0 | Usar percentuais DEZ/2025; textos reduzidos por limite 300 caracteres | ✅ | Registrado no cabeçalho do Anexo F. |
| pg. 35–42 G.1 a G.14 | Usar faixas DEZ/2025 | ✅ | Registrado no cabeçalho do Anexo F. |
| pg. 40 G.9 Serviços | Não se aplica | ✅ | Consistente com a supressão do Q11 e com a linha correspondente do Anexo E (indicador #12 PME não se aplica a Serviços). |
| pg. 45 Anexo I, I.3 | 14 indicadores | ✅ | Consistente em toda a v2. |
| pg. 45 Anexo I, I.99 | Percentuais DEZ/2025 | ✅ | Registrado no Anexo F. |
| pg. 46 Anexo J | Medianas setoriais futuras + i18n | ✅ 🔵 | Removido de inline; agora referenciado em `roadmap-pos-v1.md` (§2.1 medianas, §5.2 i18n). |
| pg. 8 §2.2 | Créditos avulsos > planos | ✅ | Regra explicitada em §2.2 (preço unitário do crédito avulso superior ao da cota de assinatura). |
| pg. 9 §2.3 | Dúvida: acesso gratuito desvaloriza? | 🟡 | Seção 6 item 6.10 — trial como flag de produto; decisão pré-go-live. |
| pg. 10 §3.3 | E-mail de confirmação PJ e PF | ✅ | Explicitado em §3.3 e §4.1. |
| pg. 11 §3.4 | 30 min Pix OK | ✅ (sem mudança) | Confirmado. |
| pg. 11 §3.6 | 14 indicadores | ✅ | (Duplicado.) |

### II.2 Ponderações gerais (pg. 6 do F2)

| # | Ponderação | Status | Onde foi tratado |
|---|---|---|---|
| P1 | Responsabilidade pela qualidade dos dados é do assinante | ✅ | Já presente em §3.5 da especificação funcional (tela inicial do quiz); reforçado em §8.1.2 do documento jurídico como cláusula contratual. |
| P2 | Nome do produto que ajude nas vendas | ⚪ Operacional | Tarefa de branding/marketing fora do escopo técnico. Registrada. |
| P3 | Identificação de pontos cegos do produto | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §5.4 — revisão trimestral estruturada como item recorrente. |
| P4 | Performance com muitos usuários simultâneos | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §1.3 — 300 concorrentes no MVP, escalável a 1.500. |
| P5 | Plano de contingência | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §5 (backup, RPO/RTO, cenários). |
| P6 | Testes de validação da versão beta | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §9.5. |
| P7 | Integração dos meios de pagamento | ✅ | `requisitos-nao-funcionais-e-juridicos.md` §3.2 — critérios de escolha e contratação. |
| P8 | Box de explicação do indicador no quiz | ✅ 🟡 | Seção 6 item 6.8 + Anexo A §A.6 — tooltip inline por campo. |
| P9 | Banco de dados com indicadores para medianas setoriais futuras | 🔵 | `roadmap-pos-v1.md` §2.1. |
| P10 | Estimativa de custos fixos da plataforma | 🔵 Operacional | `roadmap-pos-v1.md` §7.4 — pré-go-live e revisão trimestral. |

---

## Parte III — Observações EBC sobre o parecer CLAUDE (F3)

### III.1 Pg. 2 do F1, §1.2 Omissões importantes (F3 página 1)

| Item EBC | Status | Justificativa |
|---|---|---|
| "Gestão de múltiplos clientes no plano Pro: também percebi" | 🟡 | Seção 6 item 6.1 — consensual. |
| "SLA: Termo de Adesão: revisar quando concluirmos a definição das regras do negócio" | ✅ | Alinhado ao §8.3 do documento jurídico — revisão jurídica após regras de negócio e especificação concluídas. |
| "PL: crítica correta, tecnicamente, mas, para o produto (simplificado), vamos manter: OK" | ✅ + 🟡 | Marcado `[VALIDADO]` em §4.5 + mitigação 6.7. Complementado pelo Product Owner com aviso condicional no relatório. |

### III.2 Pg. 4 do F1 (F3 página 1)

| Item EBC | Status | Justificativa |
|---|---|---|
| "Ausência validações: Ok, sem problema" | 🟡 (incluir) | EBC aceita; Product Owner transformou em item 6.6 da Seção 6 com propostas concretas. |
| "PME> se Q11 = 3, não obrigatório" | ✅ | **Nota:** EBC escreveu "Q11 = 3"; contextualmente refere-se a **Q01 = 3 (Serviços) → Q11 não obrigatório**. Assim aplicado na v2. |
| "Análise captação: vide Fluxo Operacional para Captação de Recursos EB" | ✅ | Referência externa adicionada em §4.9. |

### III.3 Pg. 5–6 do F1, validação da matriz (F3 página 1)

| Item EBC | Status | Justificativa |
|---|---|---|
| "Faixas: validamos as de DEZ/2025. Vide planilha RECOMENDAÇÕES" | ✅ | Registrado no cabeçalho do Anexo F. |
| "Textos recomendações reduzidos para caber em 300 caracteres" | ✅ | Registrado no cabeçalho do Anexo F. |
| Captura de WhatsApp sobre regra de duplicidade de recomendação | ⚪ | Regra operacional de entrada de dados da planilha RECOMENDAÇÕES. Não afeta especificação funcional externa; cabe ao CMS/back-office (roadmap) replicar a validação "não duplicar recomendação com mesmo range e indicador". |

---

## Parte IV — Contradições identificadas e como foram resolvidas

### IV.1 PL simplificado: crítica técnica vs. decisão de produto

**Situação.** F1 (CLAUDE) aponta como omissão crítica; F3 (EBC) aceita tecnicamente mas decide manter. A v1 ficava na crítica técnica sem endereçamento; a manutenção pura e simples deixava o risco R10 aberto.

**Resolução na v2.** Registrar a simplificação como `[VALIDADO]` (decisão de produto) + introduzir **aviso condicional** no rodapé do relatório quando Q06 > 30% do Ativo Total (item 6.7). Assim, respeita-se a decisão comercial da EBC e mitiga-se o risco técnico levantado pelo parecer.

### IV.2 Faixas JUL/2025 vs. DEZ/2025

**Situação.** F1 detectou divergências entre as duas versões (ex.: Margem Bruta Indústria ≤ 10% → ≤ 20% na faixa vermelha) sem critério documentado. F3 responde apenas "validamos as de DEZ/2025", sem explicar o racional da mudança.

**Resolução na v2.** Registrar em item 6.9 a necessidade de **documentar o critério de revisão** de cada faixa, com coluna específica na planilha RECOMENDAÇÕES. Enquanto isso não acontece, a DEZ/2025 é a vigente (decisão tomada).

### IV.3 Periodicidade da Persona 3

**Situação.** F1 assumiu trimestral (240 análises/ano → sustenta economics do Pro). F2 corrigiu para "periódico" sem fixar cadência. Sem cadência, o preço do Pro fica sem base.

**Resolução na v2.4 (17/05/2026).** Cadência **semestral** fixada como padrão de produto e comunicação ("check-up semestral consultivo"). Cota do Pro ajustada de 5 para **10 análises/mês** (= 120/ano = atende 30 a 60 empresas ativas conforme a frequência real escolhida pelo contador para cada cliente). Preço Pro fechado em **R$ 199/mês e R$ 1.599/ano**. Persona 3 (§1.3.2) e tabela de planos (§2.1) atualizadas; modelo de dados em `arquitetura-tecnica.md` §4.1 ajustado (Subscription.cota_mensal = 10 para Pro). §6.5 marcado `[RESOLVIDO]`. Variação "Pro+" com carteira maior fica como evolução pós-MVP se a EBC observar demanda.

### IV.4 Q11 "não obrigatório" vs. "suprimir" para Serviços

**Situação.** EBC defende "não obrigatório"; CLAUDE defende "suprimir do formulário". Mesmo problema, soluções diferentes — a não-obrigatoriedade deixa o campo exibido na UI (ruído cognitivo), enquanto a supressão o esconde.

**Resolução na v2.** Product Owner adotou a **supressão dinâmica** (solução mais forte): Q11 não coletado quando Q01=3, indicador correspondente omitido no relatório. Justificativa: menor atrito para Persona 1 (Joana) e zero perda funcional. Registrado em §4.4, Anexo A §A.2 e §A.3.

### IV.5 Acesso gratuito (trial) "desvaloriza o produto?"

**Situação.** EBC levantou dúvida; CLAUDE defende manutenção do trial como padrão SaaS. Decisão não consensual.

**Resolução na v2.** Item 6.10 — trial continua especificado e implementável, mas sua exposição ao usuário final fica como **flag de configuração** controlada pelo Product Owner. Permite testar as 3 alternativas (ativar, desativar, reduzir) sem retrabalho de código.

### IV.6 Vocabulário Usuário ↔ Cliente ↔ Empresa Analisada

**Situação.** A v1 e a v2.0 não tinham definições formais para "Usuário" nem "Cliente". O termo "cliente" era usado em pelo menos três sentidos: (i) cliente do contador no plano Pro; (ii) cliente final/comprador da empresa analisada (contexto contábil de Q03/Q12/Q13); (iii) "cliente" como sinônimo vago de "conta na plataforma" (na frase "multi-tenant lógica, sem isolamento por banco por cliente"). A entidade `User` no modelo de dados fundia atributos de pessoa (login) com atributos de empresa (CNPJ, razão social, CNAE) — o que funcionava para Joana (dono = usuário) e quebrava para Marcos (contador com 60 empresas distintas).

**Resolução na v2.1 (17/05/2026).**
- Definições formais introduzidas em §1.5.2 da especificação funcional: **Usuário** (pessoa física que loga, CPF + e-mail), **Empresa Analisada** (entidade PJ ou autônomo cujos dados são analisados), **Cliente** (uso restrito ao contexto contábil — comprador da Empresa Analisada). O termo "cliente final" foi descartado.
- Refatoração do modelo de dados em `arquitetura-tecnica.md` §4.1: `User` desmembrado em `Usuario` + `EmpresaAnalisada` com FK 1:N.
- Conceito de "multi-tenant lógico" removido — a plataforma é uma aplicação relacional convencional com isolamento por FK + autorização.
- Toda a especificação funcional foi varrida para padronizar o uso dos três termos.
- Para a UX, o vocabulário exposto ao Usuário Pro é "Empresas" (Opção C — sem reintroduzir "cliente" na interface).

---

## Parte V — Checklist de cobertura (auditoria)

Esta seção demonstra, item a item, que **nenhuma crítica/sugestão ficou sem resposta**.

### V.1 F1 — Parecer CLAUDE (9 páginas)

- [x] 1.1 Acertos relevantes (6 itens) → I.1.1 a I.1.6
- [x] 1.2 Omissões críticas (3 itens) → I.2.1 a I.2.3
- [x] 1.2 Omissões importantes (6 itens) → I.3.1 a I.3.6
- [x] Pontos relevantes (3 itens) → I.4.1 a I.4.3
- [x] 2.1 Mercado potencial (3 subitens) → I.5.1 a I.5.3
- [x] 2.2 Pré-lançamento técnico (8 itens) → I.6.1 a I.6.8
- [x] 2.3 Desafios centrais (4 itens) → I.7.1 a I.7.4
- [x] 2.4 Riscos R1–R10 (10 riscos) → §I.8
- [x] 3.0 Síntese e recomendação formal (3 omissões críticas + 3 requisitos) → §I.9

**Cobertura F1: 100%.**

### V.2 F2 — Observações EBC sobre especificação funcional (6 páginas)

- [x] Correções factuais (45+ itens página-a-página) → §II.1
- [x] Ponderações finais P1 a P10 → §II.2

**Cobertura F2: 100%.**

### V.3 F3 — Observações EBC sobre parecer CLAUDE (1 página)

- [x] 3 itens sobre omissões importantes (§III.1)
- [x] 3 itens sobre pontos relevantes (§III.2)
- [x] 3 itens sobre validação da matriz de recomendações (§III.3)

**Cobertura F3: 100%.**

### V.4 Contradições resolvidas (§IV)

- [x] IV.1 PL simplificado
- [x] IV.2 Faixas JUL/2025 vs. DEZ/2025
- [x] IV.3 Periodicidade Persona 3
- [x] IV.4 Q11 Serviços — não-obrigatório vs. suprimir
- [x] IV.5 Trial gratuito
- [x] IV.6 Vocabulário Usuário ↔ Cliente ↔ Empresa Analisada (resolvido na v2.1)

### V.5 Resumo quantitativo (v2.4)

| Status | Quantidade |
|---|---|
| ✅ Aceito (aplicado direto) | ~59 |
| 🟡 Em definição (Seção 6) | 6 (era 7 na v2.3; 6.5 migrou para `[RESOLVIDO]`) |
| 🔵 Adiado (roadmap pós-v1) | ~20 |
| ⚪ Operacional (fora do código) | ~8 |
| 🔴 Recusado | 0 |

**Nenhuma recomendação foi recusada sem tratamento.** Os itens marcados "Operacional" também recebem responsável e horizonte — estão apenas fora do código da aplicação. Itens `[DECIDIR]` resolvidos até a v2.4: **6.1** (multi-empresa Pro — modelo base), **6.2** (algoritmo do Resumo Executivo — §4.7.1), **6.3** (NCG absoluto — indicador informativo, §4.5) e **6.5** (cadência Persona 3 + dimensionamento Pro — §1.3.2 e §2.1).

---

**Fim do documento — Resumo das Recomendações e Respostas v1.0**

*Documento vivo. Atualizar à medida que os itens `[DECIDIR]` forem resolvidos (Seção 6 da especificação funcional) e os itens 🔵 forem iniciados (acompanhar em `roadmap-pos-v1.md`).*
