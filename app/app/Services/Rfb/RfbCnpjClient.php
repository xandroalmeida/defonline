<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Rfb\RfbCnpjResult;
use App\Providers\AppServiceProvider;

/**
 * Contrato de provedor de consulta de CNPJ na RFB (STORY-015 CA-1; IDR-004).
 *
 * Implementações:
 * - {@see MockRfbCnpjClient} — default; entregue por esta estória.
 * - `CnpjaRfbCnpjClient` / `ReceitawsRfbCnpjClient` — entregues pela STORY-018.
 *
 * Selecionado em runtime via `config('services.rfb.provider')` e bind no
 * {@see AppServiceProvider}.
 *
 * Contrato de erro: lança {@see RfbCnpjFalhouException} em QUALQUER cenário
 * que não seja sucesso (CNPJ inexistente, timeout, 5xx, erro de rede). Quem
 * chamar não precisa interpretar HTTP nem mensagem — só pegar o `status` do
 * enum em `RfbCnpjFalhouException::$status`.
 *
 * Cache/métrica/audit NÃO são responsabilidade do client — quem orquestra
 * isso é {@see RfbConsultarCnpj} (CA-3..CA-6).
 */
interface RfbCnpjClient
{
    /**
     * @param  string  $cnpj  14 dígitos puros (sem máscara). Caller já validou DV.
     *
     * @throws RfbCnpjFalhouException quando a consulta falha (qualquer status ≠ sucesso).
     */
    public function consultarCnpj(string $cnpj): RfbCnpjResult;
}
