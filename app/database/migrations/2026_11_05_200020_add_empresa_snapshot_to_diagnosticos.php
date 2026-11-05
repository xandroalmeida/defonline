<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-064 CA-8 — o diagnóstico congela, na emissão, os dados da empresa que a
 * ação "Atualizar dados da Receita" pode mudar (razão social, fantasia da Receita,
 * documento, município/UF). Decisão provisória do orquestrador, aguardando o PO.
 *
 * Backfill dos já emitidos com os dados atuais da empresa (a melhor informação
 * disponível), inclusive de empresas excluídas. Se o dono da empresa foi anonimizado,
 * ou a empresa não existe mais, entra um marcador neutro: não se recupera dado que a
 * anonimização removeu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosticos', function (Blueprint $table) {
            $table->jsonb('empresa_snapshot')->nullable();
        });

        $neutro = json_encode([
            'tipo_documento' => 'cnpj',
            'documento' => '',
            'razao_social' => 'Empresa removida',
            'nome_fantasia' => null,
            'municipio' => '',
            'uf' => '',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        DB::statement(<<<'SQL'
            UPDATE diagnosticos d
            SET empresa_snapshot = CASE
                WHEN e.id IS NULL OR u.anonimizado_at IS NOT NULL THEN ?::jsonb
                ELSE jsonb_build_object(
                    'tipo_documento', e.tipo_documento,
                    'documento', e.documento,
                    'razao_social', e.razao_social,
                    'nome_fantasia', CASE WHEN e.tipo_documento = 'cpf' THEN NULL ELSE e.nome_fantasia END,
                    'municipio', e.municipio,
                    'uf', e.uf
                )
            END
            FROM diagnosticos d2
            LEFT JOIN empresas_analisadas e ON e.id = d2.empresa_analisada_id
            LEFT JOIN usuarios u ON u.id = e.usuario_id
            WHERE d.id = d2.id
        SQL, [$neutro]);

        DB::statement('ALTER TABLE diagnosticos ALTER COLUMN empresa_snapshot SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('diagnosticos', function (Blueprint $table) {
            $table->dropColumn('empresa_snapshot');
        });
    }
};
