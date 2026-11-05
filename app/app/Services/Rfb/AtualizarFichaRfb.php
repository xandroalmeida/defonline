<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\FonteEnriquecimento;
use App\Domain\Rfb\FichaRfb;
use App\Domain\Rfb\RfbCnpjResult;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * "Atualizar dados da Receita" (STORY-064; PDR-010 item 7): reconsulta a RFB e
 * substitui a ficha inteira, mantendo observações, nome informado e contato.
 *
 * A consulta passa pelo {@see RfbConsultarCnpj}, que já aplica o cache de 5 min
 * (IDR-004). Um resultado cuja consulta é a mesma já gravada na empresa não gera
 * escrita nem audit: devolve `false` ("já atualizada", CA-5).
 *
 * @throws RfbCnpjFalhouException a ficha anterior fica intacta (CA-4).
 */
final class AtualizarFichaRfb
{
    /**
     * Compara pelo relógio (UTC) gravado, não pelo instante: com a sessão do Postgres
     * em America/Sao_Paulo (compose de dev), o instante relido de `enriquecido_at`
     * desloca 3h, mas o relógio gravado volta igual.
     */
    private const RELOGIO = 'Y-m-d H:i:s';

    public function __construct(private readonly RfbConsultarCnpj $consultar) {}

    /** @return bool true se a ficha foi substituída; false se já estava atualizada. */
    public function executar(EmpresaAnalisada $empresa): bool
    {
        $resultado = $this->consultar->executar($empresa->documento);

        if ($this->jaAtualizada($empresa, $resultado)) {
            return false;
        }

        $novos = [
            'razao_social' => $resultado->razaoSocial,
            'nome_fantasia' => $resultado->nomeFantasia,
            'cnae' => $resultado->cnae,
            'municipio' => $resultado->municipio,
            'uf' => $resultado->uf,
            'situacao_cadastral' => $resultado->situacaoCadastral,
            'data_fundacao' => $resultado->dataFundacao?->format('Y-m-d'),
            'fonte_enriquecimento' => FonteEnriquecimento::Rfb,
            'enriquecido_at' => $resultado->consultadoAt,
            'ficha_rfb' => $resultado->ficha->toArray(),
        ];

        $alterados = $this->camposAlterados($empresa, $resultado);

        DB::transaction(function () use ($empresa, $novos, $alterados): void {
            $empresa->update($novos);

            $usuarioId = (string) $empresa->usuario_id;
            // Só os nomes dos campos: sócios e endereço são dados pessoais.
            AuditLogger::log(
                action: 'empresa.rfb_atualizada',
                subjectType: 'EmpresaAnalisada',
                subjectId: $empresa->id,
                actorType: 'user',
                actorId: $usuarioId,
                usuarioId: $usuarioId,
                context: ['campos_alterados' => $alterados],
            );
        });

        return true;
    }

    private function jaAtualizada(EmpresaAnalisada $empresa, RfbCnpjResult $resultado): bool
    {
        return $empresa->fonte_enriquecimento === FonteEnriquecimento::Rfb
            && $empresa->ficha_rfb !== null
            && $empresa->enriquecido_at !== null
            && $resultado->consultadoAt->format(self::RELOGIO) <= $empresa->enriquecido_at->format(self::RELOGIO);
    }

    /**
     * Nomes dos campos cujo valor muda, no mesmo vocabulário das colunas e das
     * chaves da ficha. Inclui `fonte_enriquecimento` quando a empresa era manual.
     *
     * @return list<string>
     */
    private function camposAlterados(EmpresaAnalisada $empresa, RfbCnpjResult $resultado): array
    {
        $antes = [
            'razao_social' => $empresa->razao_social,
            'nome_fantasia' => $empresa->nome_fantasia,
            'cnae' => $empresa->cnae,
            'municipio' => $empresa->municipio,
            'uf' => $empresa->uf,
            'situacao_cadastral' => $empresa->situacao_cadastral->value,
            'data_fundacao' => $empresa->data_fundacao?->format('Y-m-d'),
            'fonte_enriquecimento' => $empresa->fonte_enriquecimento->value,
            ...($empresa->fichaRfb() ?? new FichaRfb)->toArray(),
        ];
        $depois = [
            'razao_social' => $resultado->razaoSocial,
            'nome_fantasia' => $resultado->nomeFantasia,
            'cnae' => $resultado->cnae,
            'municipio' => $resultado->municipio,
            'uf' => $resultado->uf,
            'situacao_cadastral' => $resultado->situacaoCadastral->value,
            'data_fundacao' => $resultado->dataFundacao?->format('Y-m-d'),
            'fonte_enriquecimento' => FonteEnriquecimento::Rfb->value,
            ...$resultado->ficha->toArray(),
        ];

        return array_keys(array_filter($depois, static fn (mixed $valor, string $campo): bool => $valor != $antes[$campo], ARRAY_FILTER_USE_BOTH));
    }
}
