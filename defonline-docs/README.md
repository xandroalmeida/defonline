# DEFOnline — Documentação do Produto

Repositório de **documentação do produto** da plataforma DEFOnline. Contém especificação funcional, arquitetura técnica de alto nível, requisitos não-funcionais e jurídicos, design system, roadmap pós-v1 e o material-fonte original (planilhas, pareceres, relatórios de teste).

> **Reset de stack — 19/05/2026.** A primeira tentativa de implementação foi descartada. As decisões de linguagem, framework e infra foram removidas dos documentos. Permanecem firmes: (a) **PostgreSQL** como banco e (b) **TDD obrigatório + testes E2E nos fluxos críticos**. A nova rodada de arquitetura técnica de construção vai redefinir o restante.

## Estrutura

```
defonline-docs/
├── ideacao/                Material-fonte do produto:
│                           planilhas Excel (QUIZ, ESTRUTURA, RECOMENDAÇÕES),
│                           relatórios de teste em PDF. Imutável — histórico.
│
├── revisao/                Pareceres CLAUDE e observações EBC (PDFs).
│                           Imutável — histórico de feedback formal.
│
└── especificacao/
    └── V2/                 Versão VIGENTE. Fonte de verdade do produto.
        ├── especificacao-funcional.md           (regras de negócio — intactas)
        ├── arquitetura-tecnica.md               (v3.0 — neutra de stack)
        ├── requisitos-nao-funcionais-e-juridicos.md   (v2.0 — neutra de stack)
        ├── design-system.md                     (tokens visuais — neutros)
        ├── roadmap-pos-v1.md
        ├── resumo-recomendacoes-e-respostas.md
        └── anexos/         (A campos quiz, F matriz dez/2025, G jul/2025, I glossário)
```

## Por onde começar

- **Para entender o produto** → comece por `especificacao/V2/especificacao-funcional.md`.
- **Para entender a arquitetura conceitual** → `especificacao/V2/arquitetura-tecnica.md`.
- **Para entender os RNF e exigências de qualidade (TDD / E2E)** → `especificacao/V2/requisitos-nao-funcionais-e-juridicos.md`.
- **Para o visual da plataforma** → `especificacao/V2/design-system.md`.
- **Para o que NÃO entra no MVP** → `especificacao/V2/roadmap-pos-v1.md`.

## Princípio editorial

- `especificacao/V2/` muda **deliberadamente** — versionado por release semântico no cabeçalho de cada documento.
- `revisao/` e `ideacao/` são **imutáveis** — histórico.
- Não há código aqui.

## Como contribuir

- Mudanças em `especificacao/V2/` exigem PR com revisão e bump de versão no cabeçalho do documento.
- Anexos com dados-fonte (planilhas) só são atualizados quando a EBC entregar nova versão; rastreabilidade via data no nome do arquivo (`ebc.2025dez10.xlsx`).
