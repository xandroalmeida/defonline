---
artifact: story-briefing
target_role: programador
story_id: STORY-027
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-25
status: ready-for-pickup
---

# Briefing de abertura — STORY-027 (Programador)

> Este briefing **não substitui** a estória. Leia `STORY-027-quiz-industria-anexo-a.md` por inteiro, depois volte aqui para o roteiro operacional.

## Estado em que a estória chega

Esta estória pode rodar **em paralelo** com a STORY-029 (relatório). Não há dependência funcional cruzada entre as duas — ambas dependem só do schema da STORY-026 e do motor da STORY-028 (já entregue).

Já está pronto pra você consumir:

- **IDR-010** (`decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md`, `accepted`) — define que rascunho do quiz vai em **tabela separada `quiz_rascunhos`**, e que `diagnosticos` é exclusivamente snapshot imutável de diagnóstico concluído. **Esta IDR manda.**
- **Schema de `quiz_rascunhos` decidido pela CA-6 desta estória** (você cria a migration). Esquema mínimo já especificado: `id` UUID, `usuario_id` UUID FK, `empresa_analisada_id` UUID FK, `quiz_payload` JSONB, `ultimo_bloco_preenchido` smallint, `expires_at` timestamptz, timestamps + `deleted_at`. UNIQUE parcial `(usuario_id, empresa_analisada_id) WHERE deleted_at IS NULL`.
- **Motor V1 já entregue** (STORY-028 done) — sua action `App\Actions\CalcularDiagnostico::execute(EmpresaAnalisada $empresa, array $quizPayload): Diagnostico` é o ponto de integração no submit final.
- **App shell + componentes Blade** (STORY-019 done) — você reusa `<x-button>`, `<x-input>`, `<x-input-error>`, `<x-label>`, `<x-link>` + tokens do design system.
- **Anexo A do quiz** em `defonline-docs/especificacao/V2/anexos/anexo-A-campos-quiz.md` — fonte autoritativa dos 23 campos, máscaras e validações. **Copy literal** dos labels e helps (não improvise).

## Divergências entre a redação da STORY-027 e o estado canônico — IDR-010 vence

A estória foi redigida antes do SPIKE. Foram corrigidas em 2026-05-25:

| Ponto | STORY-027 (rascunho antigo) | Estado vigente — siga este |
|---|---|---|
| Onde fica o rascunho | `diagnosticos` com `status='rascunho'` | **Tabela separada `quiz_rascunhos`** (você cria a migration). `diagnosticos` é snapshot imutável só. |
| Dedup do diagnóstico final | "Idempotência por hash do payload + empresa_id + motor_version" | **Sem dedup no banco.** Motor é determinístico (golden hashes). Anti-duplo-submit é UX (`wire:loading.attr="disabled"` no botão), não banco. |
| Coluna `status` em `diagnosticos` | Implícita na redação antiga | **Não existe.** Não tente popular. |

## Ordem sugerida de execução

Estimado L. Esta ordem minimiza retrabalho:

1. **Migration `quiz_rascunhos`** (~20 min)
   - `php artisan make:migration create_quiz_rascunhos_table` — schema do CA-6.
   - CHECK constraint: `ultimo_bloco_preenchido BETWEEN 1 AND 4`.
   - UNIQUE parcial `(usuario_id, empresa_analisada_id) WHERE deleted_at IS NULL`.
   - Índice `(usuario_id, expires_at)` para a query "rascunhos ativos do Roberto".
   - `php artisan migrate` em dev.

2. **Model `QuizRascunho`** (~15 min)
   - `HasUuids` + `SoftDeletes` + `BelongsToUsuarioScope` (multi-tenant).
   - Cast `quiz_payload => AsArrayObject`, `expires_at => 'datetime'`.
   - Scope `ativos()` filtrando `expires_at > now()`.
   - Helper estático `paraEmpresa(Usuario $u, EmpresaAnalisada $e): ?self` (busca o rascunho ativo ou retorna null).

3. **Componente Livewire `QuizIndustria`** (~3-4h — núcleo da sessão)
   - 1 componente com 4 steps (preferência da estória — não múltiplos componentes).
   - State: `payload` (array), `bloco_atual` (1..4), `validacoes_por_campo` (array de erros).
   - Métodos: `proximoBloco()`, `blocoAnterior()`, `salvarRascunho()`, `submeter()`.
   - **TDD por bloco:** 1 teste de renderização + 1 de validação + 1 de persistência de rascunho por bloco = 12 testes mínimos.
   - **Reusa componentes da STORY-019.** Não crie `<x-input>` novo. Se faltar variante (ex.: input monetário com R$), adicione como variante do componente existente.

4. **Máscaras (~45 min)**
   - **R$:** Alpine.js + IMask CDN ou `alpinejs-mask` (decisão do programador — preferência: alpinejs-mask, lib pequena, sem CDN extra).
   - **Dias:** input numérico inteiro, sufixo "dias" via `<x-input-suffix>`.
   - **% (Q13):** numérico decimal, sufixo "%", validação ≤ 100.
   - **CPF (Q20–Q23):** mask `000.000.000-00` + validação de DV via `App\Domain\Cpf` (já existe — usado na STORY-014).
   - **Booleano (Q17):** 2 radios `Sim` / `Não`. Quando Sim, habilita Q18–Q23 (campos do bloco 4 — show/hide via Alpine `x-show`).

5. **Validações por campo (~45 min)**
   - Server-side com Laravel Validator (regras em `app/Http/Livewire/QuizIndustria.php::rules()`):
     - `Q01` (setor — readonly, vem da empresa): `required|in:industria`. EPIC-002 só Indústria.
     - `Q02..Q07`: `nullable|numeric|min:0` por bloco; obrigatório só no submit final.
     - `Q08..Q16`: idem.
     - `Q10..Q12` (dias): `nullable|integer|min:0`. PMC/PME/PMR > 365 dispara warning "Tem certeza? Valor atípico" (espec §6.8) — não bloqueia, mas exige confirmação.
     - `Q13` (%): `nullable|numeric|min:0|max:100`.
     - `Q17`: `required|in:sim,nao`.
     - `Q18..Q19` (R$ se Q17=sim): `required_if:Q17,sim|numeric|min:0`.
     - `Q20`: `required_if:Q17,sim|numeric|min:0|lt:Q09` (regra do Anexo A — venda com cartão deve ser inferior à venda total).
     - `Q21..Q23` (CPF): `required_if:Q17,sim|cpf` (regra custom usando `App\Domain\Cpf`).
   - Mensagens em PT-BR em `lang/pt_BR/validation.php` (ou inline com `messages()`).

6. **Persistência de rascunho a cada `Próximo`** (~30 min)
   - Listener `nextBlock()`: valida bloco atual, persiste em `quiz_rascunhos` (UPSERT por `(usuario_id, empresa_analisada_id)`), incrementa `bloco_atual`.
   - **Não** persiste no bloco 4 → bloco final via `submeter()` (chama motor).
   - `expires_at` = `now()->addDays(90)` na criação; refresh a cada save.

7. **Submit final + integração com motor** (~30 min)
   - `submeter()`: valida bloco 4, **chama `App\Actions\CalcularDiagnostico::execute($empresa, $this->payload)`** (action da STORY-028 já pronta), recebe `Diagnostico`, deleta o rascunho (`forceDelete` ou `delete()` — soft é melhor para histórico de debug), `return redirect()->route('diagnosticos.show', $diagnostico)`.
   - Botão `Calcular diagnóstico` desabilitado via `wire:loading.attr="disabled"` enquanto o motor roda (≤ 500ms p95 segundo STORY-028, então é instantâneo, mas robustez).
   - **Caso o motor levante exception inesperada** (Programador da 028 garantiu que casos extremos conhecidos não levantam): `try`/`catch` no `submeter()`, redireciona pra tela de erro genérico com `request_id` no log estruturado (padrão da app — `ADR-004`).

8. **Evento `quiz_iniciado` (~15 min)**
   - Dispatch no primeiro `Próximo` (transição bloco 1 → 2). Listener detalhado na STORY-035; nesta estória apenas chama `event(new QuizIniciado($empresa, $usuario))` (classe vazia placeholder se necessário; a STORY-035 popula).

9. **Acessibilidade + Mobile (~30 min)**
   - Labels associados (`for`/`id`).
   - Navegação por teclado: `tabindex` natural; `Enter` no último campo do bloco avança.
   - `inputmode="decimal"` em campos monetários (teclado numérico no mobile).
   - Layout mobile 360x800: testar com DevTools; cada bloco rola sem horizontal scroll.

10. **Testes (~1h)**
    - **Cobertura ≥ 80%** (gate de regra geral — não é regra de núcleo).
    - Pest Feature por bloco: render + validação + rascunho persistido.
    - Pest Feature integração: quiz inteiro submetido → `Diagnostico::count() == 1` → redirect 302 para `/diagnosticos/{id}`.
    - Pest Feature rascunho: usuario A não vê rascunho de B (multi-tenant 404).
    - Pest Feature expiração: rascunho com `expires_at` no passado não aparece em `QuizRascunho::ativos()`.
    - Dusk smoke (opcional, decisão do programador — pode ficar como débito se sobrar tempo): preencher quiz e ver redirect.

## Pegadinhas

- **Q11 (PME) é coletada mesmo na V1 com Indústria.** Motor V1 usa PME no Ciclo Financeiro (mas não exibe linha própria — STORY-030 exibe). O quiz tem que enviar Q11 no payload final.
- **Q01 vem da empresa**, não é digitado. Pré-preenchido como `industria` e readonly. EPIC-002 só faz Indústria — futuro Comércio/Serviços pega isso da empresa também.
- **`AsArrayObject` em `quiz_payload`:** ao serializar para enviar ao motor, converta para `array` puro com `iterator_to_array()`. A `App\Actions\CalcularDiagnostico::execute` espera `array`, não `ArrayObject`.
- **Decimais brasileiros vs sistema:** entrada com vírgula (`"1.234,56"`) → conversão para float canonicalizado (`"1234.56"` como string) antes de enviar ao motor. Fazer no listener `updated*` do Livewire, não no submit (mantém o `payload` sempre limpo).
- **CPF (Q21–Q23):** use `App\Domain\Cpf::validar()` que já existe. Não reinventar.
- **Rascunho expirado:** se Roberto abre o quiz com rascunho de 91 dias, `QuizRascunho::ativos()` retorna null e ele começa do zero. Avisar visualmente: "Seu rascunho expirou. Vamos começar do início." (banner suave, não erro).
- **Validações > 365 dias em PMC/PME/PMR:** não bloqueia. Mostra `<x-input-warning>` (criar se não existe; padrão do design system). O motor V1 calcula normalmente — quem decide cor é o `FarolIndustria`.

## Quando escalar para o PO

- Se descobrir que alguma máscara/validação do Anexo A é **inconsistente** com o que o motor espera no payload canonicalizado — **PARE**. A canonicalização (IDR-010 §1 do `idempotencia.md`) é o contrato; quiz precisa entregar nesse formato.
- Se quiser introduzir lib client-side nova (além de Alpine + alpinejs-mask) — **PARE**, abra IDR proposta com justificativa.
- Se a UX do rascunho ficar ambígua ("Roberto preencheu bloco 2 e mudou de empresa — o que acontece?") — **PARE**, pergunta. Eu (PO) defino.

## Quando avisar o PO em meio à execução

- Ao terminar o passo 3 (Livewire skeleton + bloco 1 funcionando) — *"quiz nasceu"*. Sinal pra coordenar com STORY-029.
- Ao terminar o passo 7 (submit final integrado com motor) — *"caminho feliz ponta a ponta vivo"*. Sinal pra abrir PR / smoke em homol.

## Referências obrigatórias

- `defonline-docs/especificacao/V2/anexos/anexo-A-campos-quiz.md` (autoritativo)
- `defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md`
- `defonline-docs/project-state/decisions/idr/IDR-008-framework-css-tailwind-v4-theme.md` (tokens visuais)
- `defonline-docs/especificacao/V2/especificacao-funcional.md` §6.4 (expiração rascunho), §6.8 (validações cruzadas — fora deste escopo, só warning > 365)
- `app/app/Models/Diagnostico.php` e `app/app/Actions/CalcularDiagnostico.php` (contrato a consumir)
- `app/app/Domain/Cpf.php` (validador de CPF reuso)
- `defonline-docs/skills/po/references/agent-task-format.md` (protocolo geral)

## Checklist de "puxei a estória, posso começar?"

- [ ] Li a STORY-027 inteira.
- [ ] Li este briefing.
- [ ] Li o Anexo A inteiro (23 campos, máscaras, condicionais).
- [ ] Skim do `CalcularDiagnostico` (sei o contrato de entrada do motor).
- [ ] Atualizei front-matter da STORY-027 (`status: in_progress`, `owner_agent`, `updated_at`).
- [ ] Atualizei `index.json` correspondente.
- [ ] Comecei pela migration de `quiz_rascunhos` (passo 1).

— PO (Alexandro)
