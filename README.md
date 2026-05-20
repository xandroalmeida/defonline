# DEFOnline

Pasta-pai do projeto **DEFOnline** — plataforma SaaS de Diagnóstico Econômico-Financeiro para Micro e Pequenas Empresas. **Esta pasta não é um repo git** — agrupa o repositório de documentação e (futuramente) o repositório de código lado a lado no file system.

> **Reset técnico — 19/05/2026.** A primeira tentativa de implementação foi descartada inteira. As decisões de linguagem, framework e infraestrutura foram removidas dos documentos. As regras de negócio permanecem intactas. Apenas **PostgreSQL** como banco e a exigência de **TDD + testes E2E** seguem como decisões fechadas. O resto será redefinido em nova rodada de arquitetura.

## Estrutura

```
~/Projetos/DEFOnline/
├── README.md                       (este arquivo)
│
└── defonline-docs/                 ← repo git: documentação do produto
    ├── ideacao/                    planilhas Excel originais e relatórios de teste
    ├── revisao/                    pareceres CLAUDE e observações EBC
    └── especificacao/              V1 (histórico) e V2 (vigente) + anexos
```

A pasta de código (`defonline-app/`) foi removida no reset. Quando a nova arquitetura técnica de construção fechar a stack, um novo repositório será criado.

## Como usar

### Claude Cowork

Selecione esta pasta (`~/Projetos/DEFOnline/`) como workspace. Cowork enxerga o repo de documentação automaticamente.

### Documentação como fonte de verdade

A documentação canônica vive em `defonline-docs/especificacao/V2/`. Começar por:

- `especificacao-funcional.md` — regras de negócio do produto (não mudaram com o reset).
- `arquitetura-tecnica.md` (v3.0) — arquitetura conceitual neutra de stack.
- `requisitos-nao-funcionais-e-juridicos.md` (v2.0) — SLA, segurança, LGPD, TDD, E2E.
- `design-system.md` — tokens visuais (neutros de framework).
- `roadmap-pos-v1.md` — o que **não** entra no MVP.
