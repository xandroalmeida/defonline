<?php

declare(strict_types=1);

/**
 * Rótulos do DRM — **conteúdo validado**, não provisório.
 *
 * Nada aqui depende da EBC: os rótulos das linhas vêm de
 * `design/estrutura-drm.md` §1 (fixados pelo `PDR-004`), os do grupo de custo
 * vêm do Anexo K §K.4, e o nome de interface é decisão de PO (`PDR-009` §3).
 * Por isso este arquivo nasce fora de `provisorio/`.
 *
 * **O que NÃO está aqui:** fórmula de linha (é `estrutura-drm` §1 e vira motor
 * na STORY-050) e ordem de exibição (é do snapshot, `ADR-007`). Este arquivo é
 * só texto.
 *
 * Editar rótulo implica subir `conteudo_version` em `config/drm.php` — e não
 * altera lançamento já fechado, que carrega o rótulo do próprio snapshot.
 */
return [
    'version' => '1.0.0', // 2026-08-06 — STORY-052.

    /*
     * Nome de interface — PDR-009 §3, fecha o [DECIDIR-A].
     *
     * "DRM" é nome técnico e não aparece para o usuário em lugar nenhum:
     * menu, cabeçalho, e-mail, notificação, título de página. O par
     * (comercial, técnico) existe porque nome comercial muda com marketing e
     * nome técnico não deve mudar junto — senão cada revisão de marca vira
     * migração de banco.
     */
    'modulo' => [
        'nome' => 'Raio X do Negócio',
        'nome_curto' => 'Raio X',      // menu lateral em viewport estreito
        'chamada' => 'Veja se o mês fechou no azul',
    ],

    /*
     * Rótulo do grupo de custo por setor — Anexo K §K.4 (CA-3).
     *
     * Não é preferência de vocabulário: é o nome contábil correto do que o
     * grupo mede em cada setor. Indústria transforma (produtos), comércio
     * revende (mercadorias), serviços executa (serviços prestados).
     */
    'grupos_por_setor' => [
        'industria' => [
            'custo' => 'Custo dos Produtos Vendidos (CPV)',
            'custo_curto' => 'CPV',
        ],
        'comercio' => [
            'custo' => 'Custo das Mercadorias Vendidas (CMV)',
            'custo_curto' => 'CMV',
        ],
        'servicos' => [
            'custo' => 'Custo dos Serviços Prestados (CSP)',
            'custo_curto' => 'CSP',
        ],
    ],

    /*
     * Rótulos dos demais grupos — iguais nos três setores.
     */
    'grupos' => [
        'vendas' => 'Despesas com Vendas',
        'administrativas' => 'Despesas Administrativas e Gerais',
        'financeiras' => 'Despesas Financeiras',
    ],

    /*
     * Linhas da demonstração, por modo de apresentação (K.5-A, PDR-004 dec. B).
     *
     * O modo é escolha de EXIBIÇÃO: não altera lançamento, não recalcula e não
     * quebra a série. O invariante do `estrutura-drm` §1.3 vale — o Lucro
     * Líquido do Mês é idêntico nos dois modos.
     *
     * `L4` é a única linha cujo rótulo varia por setor: resolve-se por
     * `grupos_por_setor.<setor>.custo`.
     */
    'linhas' => [
        'fora' => [
            'L1' => 'Receita Bruta',
            'L2' => 'Deduções da Receita Bruta',
            'L3' => 'Receita Operacional Líquida',
            'L4' => null, // ← por setor
            'L5' => 'Lucro Bruto',
            'L6' => 'Despesas Operacionais',
            'L6a' => 'Despesas com Vendas',
            'L6b' => 'Despesas Administrativas e Gerais',
            // §3.1: neste modo a linha NÃO se chama EBITDA, porque o pró-labore
            // é subtraído depois — não é EBITDA na definição padrão.
            'L7' => 'Resultado Operacional',
            'L7b' => 'Outras receitas e despesas',
            'L8' => 'Despesas Financeiras',
            'L8a' => 'Juros pagos',
            'L8b' => 'Tarifas bancárias',
            // §3.2: a planilha dizia "antes do IR/CSLL", mas o optante do
            // Simples não recolhe os dois separadamente — estão na guia única.
            'L9' => 'Resultado antes do pró-labore',
            'L10' => 'Pró-labore',
            'L11' => 'Lucro Líquido do Mês',
        ],
        'dentro' => [
            'L1' => 'Receita Bruta',
            'L2' => 'Deduções da Receita Bruta',
            'L3' => 'Receita Operacional Líquida',
            'L4' => null, // ← por setor
            'L5' => 'Lucro Bruto',
            'L6' => 'Despesas Operacionais',
            'L6a' => 'Despesas com Vendas',
            'L6b' => 'Despesas Administrativas e Gerais',
            'L6c' => 'Pró-labore',
            // Aqui o nome é correto: o pró-labore já entrou em L6b.
            'L7' => 'Resultado Operacional (EBITDA)',
            'L7b' => 'Outras receitas e despesas',
            'L8' => 'Despesas Financeiras',
            'L8a' => 'Juros pagos',
            'L8b' => 'Tarifas bancárias',
            // L9 e L10 não existem neste modo — colapsam no Lucro Líquido.
            'L11' => 'Lucro Líquido do Mês',
        ],
    ],

    /*
     * Rótulos dos campos de lançamento — Anexo K §K.3.
     *
     * Texto do formulário, na linguagem do usuário. A explicação de cada um
     * mora no tooltip (`help.php`), não no rótulo: rótulo longo cansa numa
     * tela de 38 campos.
     */
    'campos' => [
        'D00' => 'Competência',

        'D01' => 'Quanto vendeu no mês',
        'D02' => 'Impostos sobre vendas pagos no mês',

        'D03' => 'Total do custo do que foi vendido',
        'D04' => 'Compras de mercadorias',
        'D05' => 'Compras de matéria-prima',
        'D06' => 'Compras de insumos',
        'D07' => 'Fretes na aquisição',

        'D08' => 'Total das despesas com vendas',
        'D09' => 'Fretes na entrega ao cliente',
        'D10' => 'Taxas de administração de cartões',
        'D11' => 'Taxas de aplicativos de entrega',
        'D12' => 'Franquia (royalties)',
        'D13' => 'Comissões',
        'D14' => 'Outras despesas que mudam conforme as vendas',

        'D15' => 'Total das despesas administrativas e gerais',
        'D16' => 'Salários',
        'D17' => 'Encargos trabalhistas (FGTS, INSS)',
        'D18' => 'Vale transporte',
        'D19' => 'Vale refeição / cesta alimentação',
        'D20' => 'Férias',
        'D21' => 'Décimo-terceiro salário',
        'D22' => 'Uniforme, fardamento',
        'D23' => 'Aluguel',
        'D24' => 'Energia',
        'D25' => 'Água e esgoto',
        'D26' => 'Telefone',
        'D27' => 'Internet',
        'D28' => 'Contador',
        'D29' => 'Sistemas e aplicativos (tecnologia)',
        'D30' => 'Marketing e publicidade',
        'D31' => 'Manutenção e reparos',
        'D32' => 'Material de limpeza',
        'D33' => 'Material de escritório',
        'D34' => 'Seguros',
        'D35' => 'Sindicatos e associações',
        'D36' => 'Outros custos que se repetem todo mês',

        'D37' => 'Total das despesas financeiras',
        'D38' => 'Juros pagos das dívidas',
        'D39' => 'Tarifas bancárias',
        'D40' => 'Pró-labore (retirada dos sócios/dono)',

        'D41' => 'Outras receitas do mês',
        'D42' => 'Outras despesas do mês',
    ],

    /*
     * Vocabulário de indisponibilidade — DDR-005.
     *
     * O motivo é dito UMA vez, no escopo em que vale, e nunca com número no
     * lugar. Ausência não é zero: essa distinção é a razão de o `ADR-007`
     * proibir `COALESCE(…, 0)` em query de série, e ela morre na última camada
     * se a tela disser as duas coisas do mesmo jeito.
     */
    'indisponibilidade' => [
        'indisponivel:sem_faturamento' => 'Sem faturamento neste mês — os percentuais sobre a receita não se aplicam.',
        'indisponivel:grupo_agregado' => 'Este grupo foi lançado em modo agregado — os itens não foram informados separadamente.',
        'indisponivel:sem_lancamento' => 'Este mês ainda não foi lançado.',
    ],
];
