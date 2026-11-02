<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Cnpj;
use App\Domain\Rfb\Atividade;
use App\Domain\Rfb\Endereco;
use App\Domain\Rfb\FichaRfb;
use App\Domain\Rfb\OpcaoRegime;
use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
use App\Domain\Rfb\Socio;
use App\Domain\SituacaoCadastral;
use App\Domain\Uf;
use Illuminate\Support\Carbon;

/**
 * Provedor mock determinístico de consulta de CNPJ (STORY-015 CA-1).
 *
 * Default em todos os ambientes até a STORY-018 introduzir os clientes reais
 * (`cnpja`, `receitaws`) — esta estória entrega só a abstração e o caminho
 * técnico, NRF §3.1 autoriza começar com mock.
 *
 * # Gatilhos para teste (NRF §3.1; STORY-015 CA-1)
 *
 * O primeiro dígito do CNPJ normalizado determina cenários de falha
 * deterministicamente. Os demais CNPJs caem no caminho feliz.
 *
 * - prefixo `00...` → {@see RfbCnpjStatus::CnpjInexistente}
 * - prefixo `99...` → {@see RfbCnpjStatus::Timeout}
 * - prefixo `88...` → {@see RfbCnpjStatus::Erro5xx}
 * - prefixo `77...` → {@see RfbCnpjStatus::ErroRede}
 * - qualquer outro → sucesso, dados sintéticos
 *
 * # Ficha completa (STORY-063 CA-10)
 *
 * Controlada pelos dígitos 3 a 5 do CNPJ, para o teste escolher o cenário:
 *
 * - 3º dígito `1` → MEI (SIMEI e Simples optantes); `2` → Simples optante;
 *   `3` → excluído do Simples; `9` → empresa BAIXADA; demais → não optante.
 * - 4º dígito módulo 4 → quantidade de CNAEs secundários (0 a 3).
 * - 5º dígito módulo 4 → quantidade de sócios (0 a 3).
 *
 * Os dados sintéticos do caminho feliz são derivados de `crc32($cnpj)`,
 * portanto o MESMO CNPJ sempre retorna os MESMOS dados — caracteriza tanto a
 * testabilidade quanto a propriedade de "mesmo input, mesmo output" usada
 * pelo cache da {@see RfbConsultarCnpj} (CA-6).
 */
final class MockRfbCnpjClient implements RfbCnpjClient
{
    private const PROVEDOR = 'mock';

    private const RAZOES = [
        'Marcenaria Roberto LTDA',
        'Padaria Boa Vista ME',
        'Auto Peças Central LTDA',
        'Confeitaria Doce Lar EIRELI',
        'Indústria Mecânica Norte SA',
        'Mercearia Bela Vista LTDA',
        'Construtora São Pedro LTDA',
        'Comércio de Roupas Estrela LTDA',
    ];

    private const MUNICIPIOS = [
        'AC' => 'Rio Branco', 'AL' => 'Maceió', 'AP' => 'Macapá', 'AM' => 'Manaus',
        'BA' => 'Salvador', 'CE' => 'Fortaleza', 'DF' => 'Brasília', 'ES' => 'Vitória',
        'GO' => 'Goiânia', 'MA' => 'São Luís', 'MT' => 'Cuiabá', 'MS' => 'Campo Grande',
        'MG' => 'Belo Horizonte', 'PA' => 'Belém', 'PB' => 'João Pessoa', 'PR' => 'Curitiba',
        'PE' => 'Recife', 'PI' => 'Teresina', 'RJ' => 'Rio de Janeiro', 'RN' => 'Natal',
        'RS' => 'Porto Alegre', 'RO' => 'Porto Velho', 'RR' => 'Boa Vista', 'SC' => 'Florianópolis',
        'SP' => 'São Paulo', 'SE' => 'Aracaju', 'TO' => 'Palmas',
    ];

    private const CNAES = ['1622699', '4721102', '4530703', '1091102', '2812400', '4724500', '4120400', '4781400'];

    private const DESCRICOES_CNAE = [
        '1622699' => 'Fabricação de outros artigos de carpintaria para construção',
        '4721102' => 'Padaria e confeitaria com predominância de revenda',
        '4530703' => 'Comércio a varejo de peças e acessórios novos para veículos automotores',
        '1091102' => 'Fabricação de produtos de padaria e confeitaria com predominância de produção própria',
        '2812400' => 'Fabricação de equipamentos hidráulicos e pneumáticos, peças e acessórios, exceto válvulas',
        '4724500' => 'Comércio varejista de hortifrutigranjeiros',
        '4120400' => 'Construção de edifícios',
        '4781400' => 'Comércio varejista de artigos do vestuário e acessórios',
    ];

    private const NOMES_SOCIOS = ['Roberto Almeida Souza', 'Joana Pereira Lima', 'Marcos Vinícius Rocha'];

    private const QUALIFICACOES = ['Sócio-Administrador', 'Sócio', 'Administrador'];

    public function consultarCnpj(string $cnpj): RfbCnpjResult
    {
        $digitos = Cnpj::normalizar($cnpj);

        // Caller (RfbConsultarCnpj) garante DV válido antes de chegar aqui; defesa em camadas.
        if (! Cnpj::valido($digitos)) {
            throw new RfbCnpjFalhouException(
                RfbCnpjStatus::CnpjInexistente,
                self::PROVEDOR,
                'CNPJ inválido para o mock.',
            );
        }

        $status = self::gatilhoPorPrefixo($digitos);
        if ($status !== null) {
            throw new RfbCnpjFalhouException($status, self::PROVEDOR);
        }

        return self::dadosSinteticos($digitos);
    }

    private static function gatilhoPorPrefixo(string $cnpj): ?RfbCnpjStatus
    {
        return match (substr($cnpj, 0, 2)) {
            '00' => RfbCnpjStatus::CnpjInexistente,
            '99' => RfbCnpjStatus::Timeout,
            '88' => RfbCnpjStatus::Erro5xx,
            '77' => RfbCnpjStatus::ErroRede,
            default => null,
        };
    }

    private static function dadosSinteticos(string $cnpj): RfbCnpjResult
    {
        $seed = crc32($cnpj);
        $ufs = Uf::cases();
        $uf = $ufs[$seed % count($ufs)];
        $razao = self::RAZOES[($seed >> 4) % count(self::RAZOES)];
        $cnae = self::CNAES[($seed >> 8) % count(self::CNAES)];

        // 80% das vezes Ativa; 20% varia entre as outras situações — exercita o cast
        // de SituacaoCadastral no preenchimento sem precisar de CNPJ extra no teste.
        $situacao = (($seed % 10) < 8)
            ? SituacaoCadastral::Ativa
            : SituacaoCadastral::cases()[($seed >> 12) % count(SituacaoCadastral::cases())];
        if ($cnpj[2] === '9') {
            $situacao = SituacaoCadastral::Baixada;
        }

        $anoFundacao = 1980 + ($seed % 40);
        $mes = 1 + (($seed >> 8) % 12);
        $dia = 1 + (($seed >> 12) % 28);
        $dataFundacao = Carbon::create($anoFundacao, $mes, $dia)?->startOfDay();

        return new RfbCnpjResult(
            razaoSocial: $razao,
            nomeFantasia: substr($razao, 0, (int) min(strlen($razao), 30)),
            cnae: $cnae,
            municipio: self::MUNICIPIOS[$uf->value],
            uf: $uf->value,
            situacaoCadastral: $situacao,
            dataFundacao: $dataFundacao,
            fonteProvedor: self::PROVEDOR,
            consultadoAt: Carbon::now(),
            ficha: self::ficha($cnpj, $cnae, self::MUNICIPIOS[$uf->value], $uf->value, $dataFundacao?->format('Y-m-d'), $situacao),
        );
    }

    private static function ficha(string $cnpj, string $cnae, string $municipio, string $uf, ?string $fundacao, SituacaoCadastral $situacao): FichaRfb
    {
        $tipoRegime = $cnpj[2];
        $qtdSecundarios = ((int) $cnpj[3]) % 4;
        $qtdSocios = ((int) $cnpj[4]) % 4;

        $outros = array_values(array_diff(self::CNAES, [$cnae]));
        $secundarios = array_map(
            static fn (string $c): Atividade => new Atividade($c, self::DESCRICOES_CNAE[$c]),
            array_slice($outros, 0, $qtdSecundarios),
        );

        $socios = [];
        for ($i = 0; $i < $qtdSocios; $i++) {
            $socios[] = new Socio(
                nome: self::NOMES_SOCIOS[$i],
                qualificacao: self::QUALIFICACOES[$i],
                documento: '***'.substr($cnpj, 5 + $i, 6).'**',
                dataEntrada: $fundacao,
            );
        }

        [$simples, $simei, $porte] = match ($tipoRegime) {
            '1' => [new OpcaoRegime(true, $fundacao), new OpcaoRegime(true, $fundacao), 'Microempresa'],
            '2' => [new OpcaoRegime(true, $fundacao), new OpcaoRegime(false), 'Empresa de Pequeno Porte'],
            '3' => [new OpcaoRegime(false, $fundacao, '2024-01-01'), new OpcaoRegime(false), 'Empresa de Pequeno Porte'],
            default => [new OpcaoRegime(false), new OpcaoRegime(false), 'Demais'],
        };

        return new FichaRfb(
            naturezaJuridica: $tipoRegime === '1' ? 'Empresário (Individual)' : 'Sociedade Empresária Limitada',
            porte: $porte,
            capitalSocial: FichaRfb::normalizarCapital(10000 * (1 + (crc32($cnpj) % 50))),
            dataSituacao: $situacao === SituacaoCadastral::Ativa ? $fundacao : '2025-06-30',
            cnaePrincipal: new Atividade($cnae, self::DESCRICOES_CNAE[$cnae]),
            cnaesSecundarios: $secundarios,
            endereco: new Endereco(
                logradouro: 'Rua das Indústrias',
                numero: (string) (10 + (crc32($cnpj) % 900)),
                complemento: $qtdSocios > 1 ? 'Galpão 2' : null,
                bairro: 'Centro',
                cep: '0'.substr($cnpj, 0, 7),
                municipio: $municipio,
                uf: $uf,
            ),
            socios: $socios,
            simples: $simples,
            simei: $simei,
        );
    }
}
