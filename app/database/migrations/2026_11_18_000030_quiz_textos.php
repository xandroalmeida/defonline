<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STORY-106 — textos do quiz editados no backoffice. Só guarda o que a equipe alterou: o campo sem
 * linha usa o texto original do código (`CatalogoPerguntas` e `config/quiz/help-industria.php`), e
 * restaurar o original é apagar a linha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_textos', function (Blueprint $table) {
            $table->string('campo', 16)->primary();
            $table->string('rotulo', 160);
            $table->text('auxiliar');
            $table->text('ajuda');
            $table->uuid('alterado_por_usuario_id')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_textos');
    }
};
