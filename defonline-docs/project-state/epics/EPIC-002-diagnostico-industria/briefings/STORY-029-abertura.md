---
artifact: story-briefing
target_role: programador
story_id: STORY-029
epic_id: EPIC-002
sprint_id: SPRINT-2026-W25
written_by: po (alexandro)
written_at: 2026-05-25
status: ready-for-pickup
---

# Briefing de abertura — STORY-029 (Programador)

> Este briefing **não substitui** a estória. Leia `STORY-029-relatorio-minimalista.md` por inteiro.

## Estado em que a estória chega

A STORY-028 (motor V1) está `done`. O `Diagnostico` model + a action `CalcularDiagnostico` + os 5 fixtures canônicos estão prontos. Você renderiza o snapshot **já calculado** — não invoca motor (IDR-010 §sub-decisão 4: snapshot é a fonte da verdade).

**Esta estória pode rodar em paralelo com a STORY-027 (quiz).** Como? Usando os fixtures de teste da STORY-028 como seed para popular um `Diagnostico` em homol/dev e desenvolver o relatório sem depender da UI do quiz funcionar.

Já está pronto pra você consumir:

- **`Diagnostico` model** (`app/app/Models/Diagnostico.php`) — casts `AsArrayObject` para os 3 JSON, `belongsTo(Usuario)` + `belongsTo(EmpresaAnalisada)`, `BelongsToUsuarioScope` (multi-tenant Global Scope ADR-003).
- **5 fixtures canônicos de quiz** em `app/tests/Domain/Motor/Fixtures/*.json` — você consegue gerar um `Diagnostico` semeado rodando a action `CalcularDiagnostico::execute` com qualquer fixture; documente no `database/seeders/DiagnosticosDemoSeeder.php` (criar se não existir).
- **`DiagnosticoFactory`** (`database/factories/DiagnosticoFactory.php`) — método `paraEmpresa($empresa)` já preserva tenant. Útil para testes Pest.
- **`indicadores_calculados`** snapshot estável — sua única fonte de dados. Estrutura por entrada: `{valor, farol, motivo, mensagem}`. NCG absoluto tem `farol = 'nenhum'`.
- **Tokens de design** (STORY-019 + IDR-008) — `tokens.css`, componentes Blade `<x-button>`, `<x-card>`. **Não** crie variante de farol sem alinhar com tokens existentes.
- **Anexo I (glossário)** em `defonline-docs/especificacao/V2/anexos/anexo-I-glossario.md` — fonte do texto de glossário inline.
- **IDR-009** (404 silente cross-tenant) — replique o padrão exatamente. O Programador da 028 já entregou 5 testes feature comprovando o padrão em `/diagnosticos/{id}`; siga o exemplo.

## Escopo desta estória — relatório minimalista da V1

Lembre-se do escopo:

- **7 indicadores essenciais com farol** (Margem Bruta, Margem Líquida, Dívida Líq./EBITDA, NCG/Vendas, PMR, PMC, Ciclo Financeiro).
- **NCG absoluto** (R$, sem farol, com mensagem em 3 faixas — `farol = 'nenhum'`).
- **Glossário inline** (Anexo I — texto curto por termo).
- **Sem Resumo Executivo** (STORY-031). O `resumo_executivo` no snapshot vem com placeholder `{pendente_story: STORY-031}` — **renderiza nada** nesta versão (ou um banner discreto "Resumo executivo em breve" — decisão do programador, preferência por banner discreto para preservar layout).
- **Sem textos da matriz DEZ/2025** (STORY-032). A `mensagem` no snapshot vem como placeholder genérico (`"Faixa verde."` etc.) — exibir como está. STORY-032 troca depois sem mexer no relatório.
- **Sem tooltips** (STORY-033).

## Ordem sugerida de execução

Estimado M. Esta ordem minimiza retrabalho:

1. **Seeder de demo** (~20 min)
   - Cria `database/seeders/DiagnosticosDemoSeeder.php`.
   - Roda os 5 fixtures via `CalcularDiagnostico::execute` para um usuário+empresa demo.
   - `php artisan db:seed --class=DiagnosticosDemoSeeder` em dev para você ter 5 `Diagnostico` registros visualizáveis no browser.
   - Não vai para homol/prod automaticamente (só dev).

2. **Rota + Controller** (~20 min)
   - `Route::get('/diagnosticos/{diagnostico}', [DiagnosticoController::class, 'show'])->middleware('auth')->name('diagnosticos.show')`.
   - Route Model Binding pega `$diagnostico` automaticamente; Global Scope garante 404 cross-tenant (IDR-009).
   - Controller é magro: `return view('diagnosticos.show', compact('diagnostico'))`.

3. **View Blade `resources/views/diagnosticos/show.blade.php`** (~1.5h)
   - Estende o layout do app shell (`<x-app-layout>` ou equivalente da STORY-019).
   - Breadcrumb: `Minhas Empresas › {empresa.nome} › Diagnóstico de {data}`.
   - Cabeçalho: nome da empresa + data + badge "Versão MVP" pequena.
   - Loop pelos 7 indicadores com farol + NCG absoluto separado:
     ```blade
     @foreach ($diagnostico->indicadores_calculados as $codigo => $ind)
       @if ($codigo === 'ncg_absoluto')
         <x-relatorio.card-ncg :indicador="$ind" />
       @else
         <x-relatorio.linha-indicador :codigo="$codigo" :indicador="$ind" />
       @endif
     @endforeach
     ```
   - Glossário inline: `<details>` por termo (HTML nativo expansível, sem JS).
   - Rodapé: `motor_version`, `matrix_version`, data de geração, aviso legal.

4. **Componente `<x-relatorio.farol>`** (~30 min)
   - Visual: cor de fundo + ícone (acessibilidade — daltônicos). Tokens do design system para verde/amarelo/vermelho/sem-cor.
   - 4 variantes: `verde`, `amarelo`, `vermelho`, `nenhum`.
   - Ícones (lucide ou heroicons — preferência: heroicons já está na app): `check-circle` (verde), `exclamation-triangle` (amarelo), `x-circle` (vermelho), `information-circle` (nenhum).
   - `aria-label="Farol: {cor}"` para leitor de tela.

5. **Componentes filhos `<x-relatorio.linha-indicador>` e `<x-relatorio.card-ncg>`** (~45 min)
   - Linha (desktop tabela; mobile card): nome do indicador + valor formatado + farol + mensagem curta.
   - Card NCG: valor em R$ + mensagem em 3 faixas + bloco visual destacado de "indicador informativo".
   - Formatação de valor:
     - Margem* (%): `number_format($v, 1, ',', '.') . '%'` (1 casa decimal).
     - Dívida Líq./EBITDA (×): `number_format($v, 2, ',', '.') . '×'`.
     - NCG/Vendas (%): `number_format($v * 100, 1, ',', '.') . '%'` (multiplicar por 100 porque snapshot grava razão).
     - PMR/PMC/CicloFin (dias): `(int) $v . ' dias'`.
     - NCG abs (R$): `'R$ ' . number_format($v, 2, ',', '.')`.
     - **Quando `valor === null`:** exibe a `mensagem` do snapshot em texto cinza (linha "indisponível", visual sem destaque), **sem farol**.

6. **Layout mobile-first** (~30 min)
   - Em viewport ≥ 768px (md): tabela densa com 4 colunas (Indicador, Valor, Farol, Mensagem).
   - Em viewport < 768px: stack de cards.
   - Tailwind: `class="hidden md:table-row"` + `class="md:hidden"` em variantes. Decisão do programador se isso fica bonito ou se vai full card-grid responsivo.

7. **Glossário** (~30 min)
   - Lê o Anexo I e popula `<details>` por termo no fim da página.
   - Ou: cada nome de indicador na tabela é um `<button>` que expande/recolhe a definição (Alpine simples).
   - Decisão do programador. Preferência do PO: `<details>` nativo no fim — menos JS, mais simples.

8. **Loading state (skeleton)** (~20 min)
   - Quando Roberto chega via redirect do quiz (STORY-027 submeteu), motor pode ainda estar rodando (improvável dado p95 ≤ 500ms, mas robustez).
   - Decisão do programador: ou (a) controller espera o `Diagnostico` existir e renderiza direto (assume sync), ou (b) renderiza skeleton + Livewire polling até ver o registro. **Preferência do PO: (a) — sync direto. p95 ≤ 500ms torna skeleton overkill.** Documente nas Notas a decisão.

9. **Testes (~1h)**
   - **Cobertura ≥ 80%.**
   - Pest Feature: usuário autenticado dono vê o relatório (200, ve 7 indicadores).
   - Pest Feature multi-tenant: outro usuário tenta GET → 404 silente (IDR-009).
   - Pest Feature acessibilidade básica: response contém `<main>`, `<h1>`, ícones com `aria-label`.
   - Pest Feature visual smoke: snapshot com indicador `valor=null` renderiza linha cinza sem farol.
   - Dusk smoke (opcional, se sobrar tempo): abrir cada um dos 5 diagnósticos do seeder e ver sem erro JS.

10. **Latência de render** (~15 min)
    - Medir tempo total `request → response` para o relatório. Alvo: render + motor (que já correu) ≤ 1.5s p95 (o budget de 3s do épico inclui o quiz em si).
    - Sem teste automatizado obrigatório — só `dd(microtime(true) - LARAVEL_START)` no rodapé em dev, anotar nas Notas do agente.

## Pegadinhas

- **`AsArrayObject` no model:** ao iterar `$diagnostico->indicadores_calculados`, isso retorna `ArrayObject` — `@foreach` do Blade funciona, mas se você fizer `array_map` ou função de array, converta com `iterator_to_array()` antes.
- **NCG/Vendas é razão decimal, não percentual.** O snapshot grava `0.085` (= 8.5%). Multiplique por 100 ao exibir.
- **`valor === null` é distinto de `valor === 0`.** 0 é valor válido (Margem Bruta zero = caso vermelho real). Null é "indisponível". Use `is_null()` no Blade, não `empty()`.
- **NCG absoluto sem farol:** `farol === 'nenhum'`. Não pintar de cinza por engano — tem visual próprio (card destacado, mensagem em destaque).
- **Glossário não tem `motor_version`.** O Anexo I é texto editorial; muda só com PR de copy.
- **Rodapé do relatório precisa do `motor_version` + `matrix_version` do registro**, não do `config('motor.version')`. Snapshot é a verdade — se um diagnóstico antigo foi feito com 1.0.0 e agora produção está em 1.1.0, o rodapé desse antigo mostra 1.0.0.
- **Multi-tenant:** **não** use `withoutGlobalScope` no controller. Confiar no Route Model Binding + Global Scope (IDR-009 §Decisão 1). Já testado pelo Programador da 028 — replique o pattern.

## Quando escalar para o PO

- Se descobrir que algum tipo de valor não cabe em nenhum dos formatos do passo 5 — **PARE**, eu defino.
- Se o glossário ficar visualmente pesado e quebrar o layout — **PARE**, posso decidir colapsar pra final da página vs inline.
- Se quiser introduzir lib JS nova (Chart.js para gráfico de evolução, por exemplo) — **fora de escopo** desta estória; abra como follow-up para EPIC-003.

## Quando avisar o PO em meio à execução

- Ao terminar o passo 3 (Blade renderizando o snapshot) — *"relatório nasceu"*. Posso fazer smoke visual com você.
- Ao terminar o passo 9 (testes verdes + cobertura ≥ 80%) — *"pronto para revisão"*. Abrimos PR.

## Referências obrigatórias

- `defonline-docs/project-state/decisions/idr/IDR-010-versionamento-motor-persistencia-diagnostico.md` (snapshot é a verdade)
- `defonline-docs/project-state/decisions/idr/IDR-009-cross-tenant-403-vs-404.md` (404 silente)
- `defonline-docs/project-state/decisions/idr/IDR-008-framework-css-tailwind-v4-theme.md` (tokens visuais)
- `defonline-docs/especificacao/V2/anexos/anexo-I-glossario.md` (glossário)
- `defonline-docs/especificacao/V2/especificacao-funcional.md` §4.7 (relatório)
- `app/app/Models/Diagnostico.php` + `app/tests/Domain/Motor/Fixtures/*.json` (fonte de dados)
- `defonline-docs/skills/po/references/agent-task-format.md` (protocolo geral)

## Checklist de "puxei a estória, posso começar?"

- [ ] Li a STORY-029 inteira.
- [ ] Li este briefing.
- [ ] Vi pelo menos 1 dos 5 fixtures da STORY-028 e entendi a estrutura de `indicadores_calculados`.
- [ ] Conferi os tokens de design no `tokens.css` para os 4 estados de farol.
- [ ] Atualizei front-matter da STORY-029 (`status: in_progress`, `owner_agent`, `updated_at`).
- [ ] Atualizei `index.json` correspondente.
- [ ] Comecei pelo seeder demo (passo 1) — porque é o que destrava ver o relatório no browser sem precisar do quiz da 027.

— PO (Alexandro)
