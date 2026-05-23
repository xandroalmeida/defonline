<?php

declare(strict_types=1);

use App\Domain\FonteEnriquecimento;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `empresas_analisadas` — entidade alvo do diagnóstico (espec V2 §1.5.2).
 *
 * STORY-014 — entrega o cadastro manual; STORY-015 vai usar a mesma tabela
 * para gravar enriquecimento via RFB (`fonte_enriquecimento='rfb'` + `enriquecido_at`).
 *
 * Multi-tenancy: FK obrigatória para `usuarios.id`. Global Scope no model
 * filtra por `auth()->id()` em toda query (ADR-003 §Decisão 1).
 *
 * Soft delete + anonimização diferida (ADR-003 §Decisão 5). Índice único
 * parcial `(usuario_id, documento)` ignora linhas soft-deleted — assim outro
 * Usuário pode cadastrar uma empresa com mesmo documento sem colidir, e o
 * próprio Usuário pode recadastrar uma empresa apagada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas_analisadas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->foreign('usuario_id')
                ->references('id')->on('usuarios')
                ->onDelete('restrict');
            $table->index('usuario_id');

            $table->string('tipo_documento', 8);
            $table->string('documento', 14);  // CNPJ tem 14, CPF tem 11 — coluna comporta os dois normalizados em dígitos.
            $table->text('razao_social');
            $table->text('nome_fantasia')->nullable();
            $table->string('cnae', 7)->nullable();
            $table->string('municipio');
            $table->char('uf', 2);
            $table->string('situacao_cadastral', 16)->default(SituacaoCadastral::NaoInformada->value);
            $table->date('data_fundacao')->nullable();
            $table->string('fonte_enriquecimento', 8)->default(FonteEnriquecimento::Manual->value);
            $table->timestampTz('enriquecido_at')->nullable();

            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        // Defesa em profundidade no banco: enums via CHECK (mais flexíveis de evoluir que CREATE TYPE).
        $tipos = implode(', ', array_map(static fn (TipoDocumento $t) => "'{$t->value}'", TipoDocumento::cases()));
        DB::statement("ALTER TABLE empresas_analisadas ADD CONSTRAINT empresas_analisadas_tipo_documento_check CHECK (tipo_documento IN ({$tipos}))");

        $situacoes = implode(', ', array_map(static fn (SituacaoCadastral $s) => "'{$s->value}'", SituacaoCadastral::cases()));
        DB::statement("ALTER TABLE empresas_analisadas ADD CONSTRAINT empresas_analisadas_situacao_check CHECK (situacao_cadastral IN ({$situacoes}))");

        $fontes = implode(', ', array_map(static fn (FonteEnriquecimento $f) => "'{$f->value}'", FonteEnriquecimento::cases()));
        DB::statement("ALTER TABLE empresas_analisadas ADD CONSTRAINT empresas_analisadas_fonte_check CHECK (fonte_enriquecimento IN ({$fontes}))");

        // CA-1: unique parcial (usuario_id, documento) — ignora soft-deletes. Outro
        // Usuário pode ter empresa com mesmo documento (multi-tenancy). O mesmo
        // Usuário pode recadastrar uma empresa apagada (deleted_at IS NOT NULL).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX empresas_analisadas_usuario_id_documento_unique
            ON empresas_analisadas (usuario_id, documento)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas_analisadas');
    }
};
