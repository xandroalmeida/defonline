<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Cnpj;
use App\Domain\Rfb\RfbCnpjResult;
use App\Domain\Rfb\RfbCnpjStatus;
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
        );
    }
}
