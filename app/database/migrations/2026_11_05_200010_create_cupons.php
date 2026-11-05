<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-072 / PDR-020 / spec §2.5 — cupons de desconto, concessões diretas e usos.
 *
 * Os itens cobertos referenciam Plano e PacoteCredito pelo id, nunca por código (STORY-071 CA-9).
 * O uso (`cupom_usos`) é gravado na aplicação ao checkout (STORY-083): aqui só nasce a tabela,
 * para o backoffice mostrar o consumo e para a regra "uma vez por usuário" ter onde se apoiar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Sempre em maiúsculas (normalizado na aplicação): a unicidade ignora caixa.
            $table->string('codigo', 40)->unique();
            $table->string('tipo', 20);
            // Percentual (1 a 100) ou centavos, conforme o tipo.
            $table->unsignedInteger('valor');
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim');
            $table->unsignedInteger('limite_usos')->nullable();
            // Nulo = vale por toda a assinatura.
            $table->unsignedInteger('ciclos_duracao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->foreignUuid('alterado_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE cupons ADD CONSTRAINT cupons_tipo_check CHECK (tipo IN ('percentual', 'valor_fixo'))");
        DB::statement("ALTER TABLE cupons ADD CONSTRAINT cupons_valor_check CHECK (valor >= 1 AND (tipo <> 'percentual' OR valor <= 100))");
        DB::statement('ALTER TABLE cupons ADD CONSTRAINT cupons_vigencia_check CHECK (vigencia_fim >= vigencia_inicio)');

        Schema::create('cupom_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cupom_id')->constrained('cupons')->cascadeOnDelete();
            // Ou um plano (em um ciclo), ou um pacote de créditos.
            $table->foreignUuid('plano_id')->nullable()->constrained('planos')->restrictOnDelete();
            $table->string('ciclo', 10)->nullable();
            $table->foreignUuid('pacote_credito_id')->nullable()->constrained('pacotes_credito')->restrictOnDelete();
            $table->timestampTz('created_at')->nullable();

            $table->index('cupom_id');
        });

        DB::statement("ALTER TABLE cupom_itens ADD CONSTRAINT cupom_itens_forma_check CHECK ((plano_id IS NOT NULL AND ciclo IN ('mensal', 'anual') AND pacote_credito_id IS NULL) OR (plano_id IS NULL AND ciclo IS NULL AND pacote_credito_id IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX cupom_itens_plano_unico ON cupom_itens (cupom_id, plano_id, ciclo) WHERE plano_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cupom_itens_pacote_unico ON cupom_itens (cupom_id, pacote_credito_id) WHERE pacote_credito_id IS NOT NULL');

        Schema::create('cupom_concessoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cupom_id')->constrained('cupons')->cascadeOnDelete();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            // Cópia da duração do cupom na hora da concessão; nulo = toda a assinatura.
            $table->unsignedInteger('ciclos_restantes')->nullable();
            $table->foreignUuid('concedida_por_usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestampTz('concedida_em');

            // CA-5: conceder de novo à mesma conta não duplica.
            $table->unique(['cupom_id', 'usuario_id']);
            $table->index('usuario_id');
        });

        Schema::create('cupom_usos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cupom_id')->constrained('cupons')->cascadeOnDelete();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->unsignedInteger('desconto_centavos')->default(0);
            $table->unsignedInteger('ciclos_restantes')->nullable();
            $table->timestampTz('usado_em');

            // Spec §2.5: cada usuário usa o mesmo cupom uma única vez.
            $table->unique(['cupom_id', 'usuario_id']);
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupom_usos');
        Schema::dropIfExists('cupom_concessoes');
        Schema::dropIfExists('cupom_itens');
        Schema::dropIfExists('cupons');
    }
};
