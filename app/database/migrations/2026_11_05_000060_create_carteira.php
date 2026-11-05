<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * STORY-073 / PDR-020 — a carteira do Usuário: interruptor de cobrança, assinatura (inclui o
 * trial), lotes de crédito e o registro de cada consumo.
 *
 * Os CHECKs são a última defesa: mesmo que alguma regra da aplicação falhe, o banco recusa saldo
 * negativo e consumo sem origem. `consumos_analise` tem `diagnostico_id` e `quiz_id` únicos: uma
 * análise é cobrada uma vez só, mesmo com duas conclusões simultâneas (CA-6).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Uma linha só, desligada: a cobrança só vale depois que o PO liga na tela de planos.
        Schema::create('cobranca_config', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('ativa')->default(false);
            $table->uuid('alterado_por_usuario_id')->nullable();
            $table->timestampsTz();
        });

        DB::table('cobranca_config')->insert([
            'id' => (string) Str::uuid7(), 'ativa' => false,
            'created_at' => DB::raw('now()'), 'updated_at' => DB::raw('now()'),
        ]);

        // `plano_id` nulo = trial (que não é um Plano). Preço e outros campos do provedor chegam
        // com as estórias de pagamento.
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('plano_id')->nullable()->constrained('planos')->restrictOnDelete();
            $table->boolean('trial')->default(false);
            $table->string('status', 20);
            $table->string('ciclo', 10)->nullable();
            $table->timestampTz('data_inicio');
            $table->timestampTz('proximo_vencimento');
            $table->timestampTz('data_cancelamento')->nullable();
            $table->unsignedInteger('cota_snapshot');
            $table->unsignedInteger('cota_restante_ciclo');
            $table->timestampsTz();

            $table->index(['usuario_id', 'data_inicio']);
        });

        DB::statement("ALTER TABLE assinaturas ADD CONSTRAINT assinaturas_status_ck CHECK (status IN ('ativa','em_atraso','cancelada','expirada'))");
        DB::statement('ALTER TABLE assinaturas ADD CONSTRAINT assinaturas_cota_ck CHECK (cota_restante_ciclo >= 0 AND cota_restante_ciclo <= cota_snapshot)');
        // Um Usuário tem no máximo uma assinatura vigente por vez (arquitetura §4.2).
        DB::statement("CREATE UNIQUE INDEX assinaturas_vigente_unica ON assinaturas (usuario_id) WHERE status IN ('ativa','em_atraso')");

        Schema::create('lotes_credito', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->uuid('invoice_id')->nullable();
            $table->unsignedInteger('quantidade_comprada');
            $table->unsignedInteger('quantidade_restante');
            $table->string('status', 10);
            $table->timestampTz('data_compra');
            $table->timestampTz('data_expiracao');
            $table->timestampsTz();

            $table->index(['usuario_id', 'status', 'data_expiracao']);
        });

        DB::statement("ALTER TABLE lotes_credito ADD CONSTRAINT lotes_credito_status_ck CHECK (status IN ('ativo','esgotado','expirado'))");
        DB::statement('ALTER TABLE lotes_credito ADD CONSTRAINT lotes_credito_quantidade_ck CHECK (quantidade_comprada > 0 AND quantidade_restante >= 0 AND quantidade_restante <= quantidade_comprada)');

        Schema::create('consumos_analise', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignUuid('diagnostico_id')->nullable()->unique()->constrained('diagnosticos')->nullOnDelete();
            $table->foreignUuid('empresa_analisada_id')->nullable()->constrained('empresas_analisadas')->nullOnDelete();
            $table->string('origem', 10);
            $table->foreignUuid('assinatura_id')->nullable()->constrained('assinaturas')->restrictOnDelete();
            $table->foreignUuid('lote_credito_id')->nullable()->constrained('lotes_credito')->restrictOnDelete();
            $table->uuid('quiz_id')->nullable()->unique();
            $table->timestampTz('consumido_em');
            $table->timestampsTz();

            $table->index(['usuario_id', 'consumido_em']);
        });

        DB::statement("ALTER TABLE consumos_analise ADD CONSTRAINT consumos_analise_origem_ck CHECK ((origem = 'cota' AND assinatura_id IS NOT NULL AND lote_credito_id IS NULL) OR (origem = 'credito' AND lote_credito_id IS NOT NULL AND assinatura_id IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos_analise');
        Schema::dropIfExists('lotes_credito');
        Schema::dropIfExists('assinaturas');
        Schema::dropIfExists('cobranca_config');
    }
};
