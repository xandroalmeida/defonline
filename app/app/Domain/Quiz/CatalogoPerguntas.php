<?php

declare(strict_types=1);

namespace App\Domain\Quiz;

/**
 * STORY-105 — catálogo das perguntas do quiz (Indústria), na ordem em que aparecem na tela.
 *
 * É a fonte única do enunciado (`rotulo`) e do texto auxiliar que fica embaixo do campo
 * (`auxiliar`), usada pelo quiz, pela consulta de respostas e pelo relatório de travamentos.
 * O `tipo` diz como a resposta é exibida. A caixa de ajuda (?) continua em
 * `config/quiz/help-industria.php`.
 */
final class CatalogoPerguntas
{
    /** @var array<string, array{bloco: int, tipo: string, rotulo: string, auxiliar: string}> */
    public const PADRAO = [
        'Q01' => ['bloco' => 1, 'tipo' => 'setor', 'rotulo' => 'Setor de atividade', 'auxiliar' => ''],

        'Q08' => ['bloco' => 2, 'tipo' => 'moeda', 'rotulo' => 'Compras (média mensal)', 'auxiliar' => 'Mercadorias, matéria-prima, insumos.'],
        'Q09' => ['bloco' => 2, 'tipo' => 'moeda', 'rotulo' => 'Vendas (média mensal)', 'auxiliar' => 'Receita operacional bruta.'],
        'Q14' => ['bloco' => 2, 'tipo' => 'moeda', 'rotulo' => 'Custos e despesas fixas', 'auxiliar' => 'Folha de Pagamento, Encargos sociais, contador, aluguel, condomínio, energia, água, telefone, internet, franquia, retirada de sócios, etc.'],
        'Q15' => ['bloco' => 2, 'tipo' => 'moeda', 'rotulo' => 'Custos e despesas variáveis', 'auxiliar' => 'Fretes, comissões, tributos, despesas de vendas, outras despesas variáveis, etc.'],
        'Q16' => ['bloco' => 2, 'tipo' => 'moeda', 'rotulo' => 'Despesas financeiras', 'auxiliar' => 'Juros pagos a bancos, antecipações de cartões e desconto de boletos.'],
        'Q10' => ['bloco' => 2, 'tipo' => 'dias', 'rotulo' => 'PMC — prazo médio de fornecedores', 'auxiliar' => 'Em quantos dias você paga seus fornecedores em média.'],
        'Q11' => ['bloco' => 2, 'tipo' => 'dias', 'rotulo' => 'PME — giro de estoque', 'auxiliar' => 'Quantos dias seus produtos/insumos ficam parados em estoque.'],
        'Q12' => ['bloco' => 2, 'tipo' => 'dias', 'rotulo' => 'PMR — prazo médio a clientes', 'auxiliar' => 'Em quantos dias seus clientes pagam você em média.'],
        'Q13' => ['bloco' => 2, 'tipo' => 'percentual', 'rotulo' => 'Inadimplência', 'auxiliar' => 'Percentual médio de atraso dos clientes.'],

        'Q02' => ['bloco' => 3, 'tipo' => 'moeda', 'rotulo' => 'Recursos disponíveis', 'auxiliar' => 'Caixa + bancos + aplicações de liquidez imediata.'],
        'Q03' => ['bloco' => 3, 'tipo' => 'moeda', 'rotulo' => 'Contas a receber', 'auxiliar' => 'Vendas a prazo a clientes — cheques, boletos, cartão, fiado.'],
        'Q04' => ['bloco' => 3, 'tipo' => 'moeda', 'rotulo' => 'Estoque', 'auxiliar' => 'Produtos, mercadorias, matéria-prima, insumos.'],
        'Q05' => ['bloco' => 3, 'tipo' => 'moeda', 'rotulo' => 'Patrimônio (imobilizado)', 'auxiliar' => 'Imóveis, equipamentos e veículos livres de ônus – valor estimado para venda rápida'],
        'Q06' => ['bloco' => 3, 'tipo' => 'moeda', 'rotulo' => 'Dívidas financeiras', 'auxiliar' => 'Empréstimos, financiamentos, dívidas não operacionais.'],
        'Q07' => ['bloco' => 3, 'tipo' => 'moeda', 'rotulo' => 'Fornecedores a pagar', 'auxiliar' => 'Obrigações comerciais em aberto.'],

        'Q17' => ['bloco' => 4, 'tipo' => 'simnao', 'rotulo' => 'Necessita captar recursos?', 'auxiliar' => ''],
        'Q18' => ['bloco' => 4, 'tipo' => 'moeda', 'rotulo' => 'Valor que precisa captar', 'auxiliar' => 'Montante desejado.'],
        'Q19' => ['bloco' => 4, 'tipo' => 'moeda', 'rotulo' => 'Endividamento total no mercado', 'auxiliar' => 'Bancos + particulares.'],
        'Q20' => ['bloco' => 4, 'tipo' => 'moeda', 'rotulo' => 'Venda mensal com cartão / duplicatas', 'auxiliar' => ''],
        'socios' => ['bloco' => 4, 'tipo' => 'socios', 'rotulo' => 'Sócios', 'auxiliar' => ''],
    ];

    /** Títulos curtos das validações cruzadas (config/quiz/validacoes-cruzadas.php), para relatórios. */
    public const REGRAS = [
        'R1' => 'Despesas financeiras altas para a dívida declarada',
        'R2' => 'Custos acima das vendas',
        'R3' => 'Passivo maior que o ativo',
        'R4' => 'Vendas com cartão/duplicatas iguais ou acima das vendas',
    ];

    public const BLOCOS = [
        1 => 'Identificação',
        2 => 'DRE e Operação',
        3 => 'Saldos atuais',
        4 => 'Contexto e Captação',
    ];

    /** @return array<string, array{bloco: int, tipo: string, rotulo: string, auxiliar: string}> */
    public static function todas(): array
    {
        return self::PADRAO;
    }

    public static function rotulo(string $campo): string
    {
        return self::todas()[$campo]['rotulo'] ?? $campo;
    }

    public static function auxiliar(string $campo): string
    {
        return self::todas()[$campo]['auxiliar'] ?? '';
    }

    public static function regra(string $regra): string
    {
        return self::REGRAS[$regra] ?? $regra;
    }
}
