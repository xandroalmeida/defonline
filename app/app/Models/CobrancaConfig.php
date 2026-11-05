<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * STORY-073 CA-1 — interruptor da cobrança (linha única), alterado na tela de planos e auditado.
 * Desligado, o diagnóstico funciona como antes e nada é consumido nem bloqueado.
 *
 * @property string $id
 * @property bool $ativa
 * @property string|null $alterado_por_usuario_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ativa', 'alterado_por_usuario_id'])]
final class CobrancaConfig extends Model
{
    use HasUuids;

    protected $table = 'cobranca_config';

    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }

    /**
     * A linha única criada pela migration. Se alguém a apagou (truncate em ambiente de teste, por
     * exemplo), nasce de novo DESLIGADA: faltar a linha nunca pode ligar a cobrança nem derrubar o produto.
     */
    public static function atual(): self
    {
        return self::query()->first() ?? self::query()->create(['ativa' => false]);
    }

    public static function ligada(): bool
    {
        return (bool) self::query()->value('ativa');
    }
}
