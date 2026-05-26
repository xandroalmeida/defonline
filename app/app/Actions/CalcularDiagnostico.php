<?php

declare(strict_types=1);

namespace App\Actions;

use App\Domain\Motor\Motor;
use App\Domain\Motor\QuizPayloadCanonicalizer;
use App\Models\Diagnostico;
use App\Models\EmpresaAnalisada;
use Illuminate\Support\Facades\DB;

/**
 * Calcula e persiste um Diagnóstico (espec §4.5; IDR-010 §sub-decisão 2).
 *
 * **Fluxo:**
 *   1. Canonicaliza o `quiz_payload` (forma única para hash e persistência).
 *   2. Calcula `payload_hash` (SHA-256 do canonical JSON).
 *   3. Roda o {@see Motor} → recebe `indicadores_calculados` + metadados.
 *   4. Persiste em `diagnosticos` (snapshot imutável) dentro de uma transaction.
 *   5. Estampa `gerado_em = now()` aqui (NÃO no motor — motor é puro).
 *
 * **Sem deduplicação por hash.** Roberto pode emitir o mesmo diagnóstico duas
 * vezes conscientemente (são 2 registros — IDR-010 §sub-decisão 2). A garantia
 * de idempotência vive nos golden hashes do motor, não em UNIQUE no banco.
 *
 * **Setor.** V1 só processa Indústria. Para outros setores (Comércio/Serviços),
 * abrir IDR específica.
 */
final class CalcularDiagnostico
{
    public function __construct(private readonly Motor $motor) {}

    /**
     * @param  array<string, mixed>  $quizPayload  Respostas brutas do quiz (será canonicalizado).
     * @param  list<array{regra: string, ocorrido_em: string, valor_envolvido: float|int}>  $alertasAceitos
     *                                                                                                       Alertas de validação cruzada que Roberto optou por ignorar (STORY-034 CA-3). Gravados
     *                                                                                                       em `quiz_payload.alertas_aceitos` para auditoria, mas **fora** do `payload_hash`.
     *
     * @throws \InvalidArgumentException se `$empresa->setor` não é suportado nesta versão.
     */
    public function execute(EmpresaAnalisada $empresa, array $quizPayload, string $setor = 'industria', array $alertasAceitos = []): Diagnostico
    {
        $canonical = QuizPayloadCanonicalizer::canonicalize($quizPayload);
        $payloadHash = hash('sha256', QuizPayloadCanonicalizer::toJson($canonical));

        $saida = $this->motor->calcular($canonical, $setor);

        // Auditoria mesclada DEPOIS do hash e do motor: não muda payload_hash nem chega ao motor.
        $quizPayloadPersistido = $canonical;
        if ($alertasAceitos !== []) {
            $quizPayloadPersistido['alertas_aceitos'] = $alertasAceitos;
        }

        return DB::transaction(fn () => Diagnostico::create([
            'usuario_id' => $empresa->usuario_id,
            'empresa_analisada_id' => $empresa->id,
            'motor_version' => $saida['motor_version'],
            'matrix_version' => $saida['matrix_version'],
            'setor' => $saida['setor'],
            'quiz_payload' => $quizPayloadPersistido,
            'payload_hash' => $payloadHash,
            'indicadores_calculados' => $saida['indicadores_calculados'],
            'resumo_executivo' => $saida['resumo_executivo'],
            'gerado_em' => now(),
        ]));
    }
}
