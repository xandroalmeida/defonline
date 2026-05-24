# Fluxo de Navegação — DEFOnline (EPIC-004 / STORY-019)

Documento normativo do fluxo de navegação ponta-a-ponta da área autenticada e das rotas de auth. Companion de `ux-specs.md`. Persona: Roberto, dono de marcenaria, ~50 anos, mobile-first.

---

## 1. Mapa de rotas (autenticadas vs públicas)

```
Públicas (sem auth)
├── /                            [STORY-024]   shell: AUTH    ← landing placeholder
│                                 (substitui página de debug `hello-world` da STORY-007;
│                                  hotsite completo entra no EPIC-008)
├── /cadastro                    [STORY-011]   shell: AUTH
├── /login                       [STORY-011]   shell: AUTH
├── /email/confirmar/{usuario}   [STORY-013]   shell: SISTEMA (página de status)
├── /email/confirmado            [STORY-013]   shell: SISTEMA
├── /email/confirmar-erro        [STORY-013]   shell: SISTEMA
├── /termos/termo-adesao         [STORY-012]   shell: LEGAL
└── /termos/politica-privacidade [STORY-012]   shell: LEGAL

Autenticadas (middleware auth)
├── /home                        [STORY-016]   shell: APP   ← raiz pós-login
├── /empresas/nova               [STORY-014]   shell: APP   ← filho de /home
├── /empresas/{id}               [STORY-014]   shell: APP   ← filho de /home
├── /logout                      [STORY-011]   POST only
└── (futuro EPIC-002)
    ├── /empresas/{id}/diagnostico/iniciar    shell: APP
    ├── /empresas/{id}/diagnostico/{n}/quiz   shell: APP
    └── /empresas/{id}/diagnostico/{n}        shell: APP   (relatório)
```

Tipos de shell:
- **APP** — header + sidebar/drawer + breadcrumb + main + footer institucional (Termo, Privacidade, ©, versão).
- **AUTH** — header simplificado (só logo + link para a outra ação: "Entrar"/"Criar conta") + footer simplificado (© + versão).
- **SISTEMA** — página standalone para confirmações/erros transacionais (logo + mensagem + 1 CTA único).
- **LEGAL** — layout específico para documentos jurídicos longos (já existe em `layouts/legal.blade.php`, não muda nesta story).

---

## 2. Fluxo principal de entrada (happy path do Roberto)

```
[Não logado]
     │
     ▼
 / (landing pública — STORY-024)
   ┌─────────────────────────────────────────────────────────────┐
   │ Header: [D] DEFOnline             [Entrar]  [Criar conta]   │
   │                                                              │
   │       Diagnóstico estratégico para sua empresa.              │
   │                                                              │
   │       Respostas claras sobre como sua indústria está em      │
   │       14 indicadores essenciais — em minutos, sem            │
   │       consultor caro, sem planilha.                          │
   │                                                              │
   │           [ Criar conta grátis ]   [ Já tenho conta ]        │
   │                                                              │
   │ Footer: © DEFOnline 2026                      v0.4.2·homol   │
   └─────────────────────────────────────────────────────────────┘
     │
     ├──▶ CTA "Criar conta grátis" (corpo) ───────────────▶ /cadastro
     ├──▶ CTA "Já tenho conta" (corpo)     ───────────────▶ /login
     ├──▶ Header "Criar conta"             ───────────────▶ /cadastro
     ├──▶ Header "Entrar"                  ───────────────▶ /login
     │
     ▼
 /cadastro          [shell AUTH]
   • Preenche nome, email, senha, aceites
   • Header: logo "D" + wordmark + link ghost "Entrar" (canto direito)
   • CTAs: [Criar conta] (primary, submit) — sem Cancelar (raiz do fluxo de auth)
   • Footer: © + versão
     │
     ├──▶ submit OK ─▶ tela SISTEMA "Verifique seu email" ─▶ (email com link assinado)
     │                                                              │
     │                                                              ▼
     │                                                       /email/confirmar/{usuario}
     │                                                              │
     │                                                              ├─ token válido ─▶ /email/confirmado ─▶ CTA [Entrar] ─▶ /login
     │                                                              └─ token inválido ─▶ /email/confirmar-erro ─▶ CTA [Reenviar]
     │
     └──▶ submit erro ─▶ permanece em /cadastro com mensagens inline
                                            │
                                            └─ link "Reenviar email de confirmação" → /email/confirmar-erro

 /login             [shell AUTH]
   • Email + senha
   • Header: logo + link ghost "Criar conta" (canto direito)
   • CTAs: [Entrar] (primary, submit) — sem Cancelar (raiz do fluxo de auth)
   • Link "Reenviar email de confirmação" abaixo do form
   • Link "Criar conta" abaixo do form (redundância com header — mantida por convenção)
   • Footer: © + versão
     │
     ▼
 /home              [shell APP]   ← primeira tela autenticada (raiz)
```

---

## 3. Fluxo dentro da área autenticada (Minhas Empresas)

```
 /home  "Minhas Empresas"           [shell APP, raiz]
 ┌────────────────────────────────────────────────────────────────────┐
 │ Header: [☰] [D] DEFOnline   [Olá, Roberto ▾]      [homol]          │
 │ Sidebar (desktop):                                                  │
 │   ● Minhas Empresas      ◀── ATIVO (barra tertiary à esquerda)      │
 │   ○ + Adicionar Empresa                                             │
 │   ○ Diagnósticos    (disabled, tooltip "Em breve — Onda 2")         │
 │   ○ Histórico       (disabled)                                      │
 │   ○ Conta           (disabled)                                      │
 │ Breadcrumb: — (não aparece em raiz)                                 │
 │ H1: Minhas Empresas                                                 │
 │ Subtitle: "Você tem N empresas cadastradas."                        │
 │                                              [ + Adicionar empresa ]│  ← CTA primary (bug fix)
 │ ┌── Estado vazio ───────────────────────────────────────────────┐  │
 │ │ Empty illustration + "Cadastre sua primeira empresa..."       │  │
 │ │ CTA inline: [ Cadastrar primeira empresa ]                    │  │
 │ └───────────────────────────────────────────────────────────────┘  │
 │ ┌── Estado com empresas ──────────────────────────────────────────┐│
 │ │ Cards (1 col mobile, 2 col ≥768px):                              ││
 │ │ ┌── Marcenaria do Roberto      [RFB] ─┐                          ││
 │ │ │ 12.***.***/0001-90                  │                          ││
 │ │ │ Curitiba / PR                       │                          ││
 │ │ │ [Ver detalhes] [Iniciar diagnóstico*]   *disabled, tooltip     ││
 │ │ └─────────────────────────────────────┘                          ││
 │ └──────────────────────────────────────────────────────────────────┘│
 │ Footer: [Termo] [Privacidade] © DEFOnline 2026         v0.4.2·homol│
 └────────────────────────────────────────────────────────────────────┘
       │              │                       │              │
       │              │                       │              ▼
       │              │                       │     [ + Adicionar Empresa ] (sidebar)
       │              │                       │              │
       │              │                       │              ▼
       │              │                       │      /empresas/nova
       │              │                       │
       │              │                       ▼
       │              │              [ Ver detalhes ] (card)
       │              │                       │
       │              │                       ▼
       │              │              /empresas/{id}/show
       │              │
       │              ▼
       │     [ Adicionar empresa ] (header da seção, sempre visível)
       │              │
       │              ▼
       │      /empresas/nova
       │
       ▼
  [ Conta ▾ ] (dropdown do header)
       ├─ Editar perfil  (disabled, tooltip)
       └─ [Sair]  ──POST /logout──▶ /login (com flash "Você saiu da conta.")
```

---

## 4. Fluxo de cadastro de empresa (`/empresas/nova`)

```
 /empresas/nova                     [shell APP]
 ┌────────────────────────────────────────────────────────────────────┐
 │ Header: igual a /home                                               │
 │ Sidebar: "+ Adicionar Empresa" ATIVO                                │
 │ Breadcrumb: Minhas Empresas › Nova empresa                          │
 │ H1: Cadastrar empresa                                               │
 │ Caption: "Preencha os dados ou consulte na Receita Federal."        │
 │                                                                     │
 │ Form:                                                               │
 │  ◯ CNPJ  ◯ CPF                                                      │
 │  [documento] [ Consultar Receita ] (secondary contextual, só CNPJ)  │
 │  [razão social]                                                     │
 │  [nome fantasia]                                                    │
 │  [CNAE]                                                             │
 │  [município] [UF]                                                   │
 │  [situação cadastral]                                               │
 │  [data fundação]                                                    │
 │                                                                     │
 │  Ações (form-actions):                                              │
 │   Desktop:  [ Cancelar ]  [ Cadastrar empresa ]   ← lado a lado     │
 │   Mobile:   [ Cadastrar empresa ]                                   │
 │             [ Cancelar ]                          ← empilhado       │
 │                                                                     │
 │ Footer: padrão APP                                                  │
 └────────────────────────────────────────────────────────────────────┘

 Comportamento dos botões:
   [Cadastrar empresa]  ─submit──▶  POST → grava → /empresas/{id}/show
   [Cancelar]          ─click──▶   GET   → /home (descarta state, sem confirmação modal — modal fica para futuro)

 Edge cases:
   - Sem permissão de gravação / Erro do servidor → permanece em /empresas/nova com flash de erro no topo.
   - Erro de validação → permanece em /empresas/nova com mensagens inline; Cancelar continua disponível.
   - Roberto fechou o navegador no meio → ao reabrir e logar, vai direto para /home (state do form é volátil; não persistimos rascunho nesta story).
```

---

## 5. Fluxo de detalhe da empresa (`/empresas/{id}/show`)

```
 /empresas/{id}/show                [shell APP]
 ┌────────────────────────────────────────────────────────────────────┐
 │ Header: igual a /home                                               │
 │ Sidebar: "Minhas Empresas" ATIVO (navegação contextual dentro do    │
 │          mesmo ramo do menu)                                        │
 │ Breadcrumb: Minhas Empresas › Marcenaria do Roberto                 │
 │ H1: Marcenaria do Roberto (nome fantasia ou razão social)           │
 │ Pill: [Fonte: Receita Federal]                                      │
 │                                                                     │
 │ Dados (dl em 2 colunas no desktop, 1 no mobile):                    │
 │   Tipo do documento, CNPJ, razão social, nome fantasia, CNAE,       │
 │   município/UF, situação cadastral, data de fundação                │
 │                                                                     │
 │ Ações (no final do conteúdo):                                       │
 │   Desktop:  [ ← Voltar para Minhas Empresas ]  [ Iniciar diag.* ]   │
 │   Mobile:   [ Iniciar diagnóstico* ]                                │
 │             [ ← Voltar para Minhas Empresas ]                       │
 │                                                                     │
 │ *Iniciar diagnóstico: disabled, tooltip "Em breve — Onda 2"         │
 │  (decisão PO §13.3 do ux-specs: ocupa o slot do CTA primário para   │
 │   preparar o usuário para EPIC-002).                                │
 └────────────────────────────────────────────────────────────────────┘

 Comportamento:
   [Voltar para Minhas Empresas] ─click──▶ GET /home (mesmo destino do breadcrumb)
   [Iniciar diagnóstico] ─click──▶ (nada — disabled; cursor not-allowed; tooltip)
```

---

## 6. Política consolidada de Cancelar / Voltar

| Tela | Tem Cancelar? | Tem Voltar? | Por quê |
|---|---|---|---|
| `/cadastro` | Não | Não | Tela raiz do fluxo público; sair = fechar aba. Header oferece "Entrar" como alternativa lateral. |
| `/login` | Não | Não | Idem `/cadastro`. Header oferece "Criar conta". |
| `/email/confirmar*` | Não | Não | Páginas SISTEMA. CTA único conforme estado. |
| `/home` | Não | Não | Raiz da área logada. Para "sair de tudo" há `Sair` no dropdown Conta. |
| `/empresas/nova` | **Sim** | Não | É form de criação. `Cancelar` descarta e volta para `/home`. |
| `/empresas/{id}/show` | Não | **Sim** | Tela de leitura/detalhe. `Voltar para Minhas Empresas` complementa o breadcrumb. |
| (futuro) Quiz EPIC-002 | Sim (`Salvar e sair`?) | A definir | Decisão fica para EPIC-002 — pode haver rascunho do quiz. |
| (futuro) Editar empresa | **Sim** | Não | Mesmo padrão de `/empresas/nova`. |
| (futuro) Editar perfil | **Sim** | Não | Mesmo padrão. |

Regra de ouro: **nunca Cancelar + Voltar na mesma tela.** Se a tela é form de criação/edição, é Cancelar. Se é leitura/detalhe, é Voltar. Se é raiz, nenhum dos dois.

---

## 7. Identificação da tela ativa (4 sinais)

Roberto deve identificar onde está em **qualquer** rota autenticada pelos quatro sinais abaixo, todos sempre presentes (exceto breadcrumb em raiz):

| Sinal | `/home` | `/empresas/nova` | `/empresas/{id}/show` |
|---|---|---|---|
| Sidebar ativa | Minhas Empresas | + Adicionar Empresa | Minhas Empresas |
| Breadcrumb | — (raiz) | Minhas Empresas › Nova empresa | Minhas Empresas › {nome} |
| `<h1>` | Minhas Empresas | Cadastrar empresa | {nome fantasia ou razão} |
| `<title>` | "Minhas Empresas · DEFOnline" | "Cadastrar empresa · DEFOnline" | "{razão social} · DEFOnline" |

---

## 8. Fluxo de logout

```
 [Qualquer rota autenticada]
       │
       ▼
  Header → [Olá, Roberto ▾] → dropdown abre
       │
       ▼
  Item "Sair" → POST /logout (CSRF token)
       │
       ▼
  Backend invalida sessão → redirect 302 → /login
       │
       ▼
  /login com flash "Você saiu da conta." (sucesso verde)
```

Sair **não pede confirmação** nesta story — é ação não destrutiva e barata de reverter (basta logar de novo). Confirmação modal "Tem certeza?" fica para futuro se entrevistas mostrarem dor.

---

## 9. Estados de erro e redirecionamentos

| Cenário | Destino | UX |
|---|---|---|
| Sessão expirou (middleware auth falha) | `/login` | Flash "Sua sessão expirou. Faça login novamente." |
| 404 em `/empresas/{id}` | view 404 | Página de erro com botão "Voltar para Minhas Empresas". |
| 403 (tentou acessar empresa de outro usuário) | view 403 | Página de erro com botão "Voltar para Minhas Empresas". |
| 500 / exception | view 500 | Página de erro genérica com `request_id` visível para suporte. |
| Logout sem CSRF token (419) | `/login` | Flash "Sua sessão expirou. Faça login novamente." |

Páginas 404/403/500 customizadas com o shell APP simplificado (logo + mensagem + CTA único) ficam idealmente nesta story; se a complexidade aumentar, podem virar story dedicada — decisão do PO no IDR ou em ajuste durante a STORY-019.

---

## 10. Fluxo no mobile (`< 1024px`)

A diferença principal é o hamburger:

```
 [☰] [D]      [Olá ▾]   ← header colado no topo
 
   ┌─ Hamburger clicado ──────┐
   │ Drawer slide-in 200ms     │
   │ Overlay escurece conteúdo │
   │                           │
   │ ● Minhas Empresas         │
   │ ○ + Adicionar Empresa     │
   │ ○ Diagnósticos*           │
   │ ○ Histórico*              │
   │ ○ Conta*                  │
   │                           │
   │ Fecha com:                │
   │  - clique no overlay      │
   │  - Escape                 │
   │  - clique no [☰] de novo  │
   └───────────────────────────┘
   
 H1 da página
 Conteúdo em 1 coluna
 CTAs full-width
 
 Footer: links e versão empilhados (versão em linha separada, ainda à direita)
```

Touch targets: **≥ 44×44px** em todos botões, inputs e itens de menu. Hamburger é botão ≥ 44×44.

---

## 11. Notas para validador (STORY-020)

Pontos de inspeção que cobrem o fluxo:

1. **Loop "adicionar segunda empresa":** logar → cadastrar 1ª → ver `/home` com card → clicar `[+ Adicionar empresa]` no header da seção → cadastrar 2ª → voltar para `/home` com 2 cards. **Sem digitar URL.**
2. **Sair pelo Cancelar:** abrir `/empresas/nova` → preencher 3 campos → clicar `Cancelar` → confirmar que volta para `/home` sem persistir.
3. **Sair pelo Voltar:** abrir `/empresas/{id}/show` → clicar `Voltar para Minhas Empresas` → confirmar destino.
4. **Sinais de tela ativa:** em cada rota autenticada, verificar 4 sinais (sidebar, breadcrumb, H1, title).
5. **Mobile:** em 360x800, abrir/fechar drawer (hamburger / overlay / Escape).
6. **Logout:** dropdown Conta → Sair → cair em `/login` com flash.
7. **Versão visível:** confirmar `v{X.Y.Z}` no canto direito do footer em qualquer rota autenticada; `v{X.Y.Z} · homol` em homologação.
8. **Logo:** confirmar logo "D" + wordmark em ≥480px; só ícone em <480px; no header `homol` aparece como pill discreta.

---

## 12. Decisões adiadas (não bloqueiam STORY-019)

- Confirmação modal "Descartar alterações?" no Cancelar de form com dirty state — fica para depois.
- Persistência de rascunho do form `/empresas/nova` (Roberto sai e volta com dados) — não é prioridade.
- Skip link "Pular para o conteúdo" para a11y — backlog.
- Search global no header (no estilo Stripe/Linear) — fora de escopo até EPIC-003.
- Páginas de erro customizadas 404/403/500 dentro do shell — proposta de incluir; se ficar pesado, vira story dedicada.
