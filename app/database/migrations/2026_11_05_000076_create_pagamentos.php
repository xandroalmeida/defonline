<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-076 / ADR-010 — `invoices` (toda cobrança, com status e id externo) e `provider_events`
 * (entrada idempotente dos webhooks).
 *
 * `invoices.id` é também o `externalReference` enviado ao provedor. Os índices únicos parciais
 * impedem dois registros para o mesmo recurso externo, e o de `lotes_credito.invoice_id` impede,
 * no banco, creditar a mesma compra duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->uuid('pacote_credito_id')->nullable();
            $table->string('descricao', 120);
            $table->unsignedInteger('quantidade_creditos')->nullable();
            $table->unsignedInteger('validade_meses')->nullable();
            $table->unsignedInteger('valor_centavos');
            $table->string('status', 20);
            $table->string('meio', 10);
            $table->string('provedor', 20);
            $table->string('checkout_id_externo', 120)->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestampTz('checkout_expira_em')->nullable();
            $table->string('id_externo', 120)->nullable();
            $table->timestampTz('pago_em')->nullable();
            $table->string('motivo_encerramento', 40)->nullable();
            $table->string('falha_motivo', 200)->nullable();
            $table->uuid('aceite_termo_id')->nullable();
            $table->timestampsTz();

            $table->index(['usuario_id', 'created_at']);
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_ck CHECK (status IN ('pendente','paga','vencida','falhou','estornada','estornada_parcial','contestada','cancelada'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_tipo_ck CHECK (tipo IN ('credito'))");
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_valor_ck CHECK (valor_centavos > 0)');
        DB::statement('CREATE UNIQUE INDEX invoices_id_externo_unico ON invoices (provedor, id_externo) WHERE id_externo IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX invoices_checkout_externo_unico ON invoices (provedor, checkout_id_externo) WHERE checkout_id_externo IS NOT NULL');
        // Uma compra credita um lote só, mesmo que o código da aplicação erre.
        DB::statement('CREATE UNIQUE INDEX lotes_credito_invoice_unico ON lotes_credito (invoice_id) WHERE invoice_id IS NOT NULL');

        Schema::create('provider_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provedor', 20);
            $table->string('evento_id', 200);
            $table->string('tipo', 40);
            $table->string('cobranca_id_externo', 120)->nullable();
            $table->string('checkout_id_externo', 120)->nullable();
            $table->string('referencia_externa', 120)->nullable();
            $table->uuid('invoice_id')->nullable();
            $table->jsonb('payload');
            $table->string('status', 12);
            $table->text('erro')->nullable();
            $table->timestampTz('recebido_em');
            $table->timestampTz('processado_em')->nullable();

            $table->unique(['provedor', 'evento_id']);
            $table->index(['status', 'recebido_em']);
        });

        DB::statement("ALTER TABLE provider_events ADD CONSTRAINT provider_events_status_ck CHECK (status IN ('recebido','processado','ignorado','erro'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_events');
        DB::statement('DROP INDEX IF EXISTS lotes_credito_invoice_unico');
        Schema::dropIfExists('invoices');
    }
};
