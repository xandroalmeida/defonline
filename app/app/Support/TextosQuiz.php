<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Quiz\CatalogoPerguntas;
use App\Models\QuizTexto;
use Illuminate\Support\Facades\Cache;

/**
 * STORY-106 — textos das perguntas do quiz como o cliente vê: o original do código
 * ({@see CatalogoPerguntas} e a ajuda de `config/quiz/help-industria.php`), sobreposto pelo que a
 * equipe editou no backoffice (`quiz_textos`).
 *
 * Fica em cache até a próxima edição, e cada requisição lê uma vez só: o quiz pede o texto campo a
 * campo.
 */
final class TextosQuiz
{
    private const CACHE = 'quiz_textos:v1';

    private const MEMO = 'textos-quiz.memo';

    /** @return array<string, array{bloco: int, tipo: string, rotulo: string, auxiliar: string, ajuda: string, editado: bool}> */
    public static function todos(): array
    {
        $app = app();

        if (! $app->bound(self::MEMO)) {
            $app->instance(self::MEMO, Cache::rememberForever(self::CACHE, self::montar(...)));
        }

        /** @var array<string, array{bloco: int, tipo: string, rotulo: string, auxiliar: string, ajuda: string, editado: bool}> */
        return $app->make(self::MEMO);
    }

    public static function rotulo(string $campo): string
    {
        return self::todos()[$campo]['rotulo'] ?? $campo;
    }

    public static function auxiliar(string $campo): string
    {
        return self::todos()[$campo]['auxiliar'] ?? '';
    }

    public static function ajuda(string $campo): string
    {
        return self::todos()[$campo]['ajuda'] ?? '';
    }

    /** @return array{rotulo: string, auxiliar: string, ajuda: string} */
    public static function original(string $campo): array
    {
        $padrao = CatalogoPerguntas::PADRAO[$campo] ?? ['rotulo' => $campo, 'auxiliar' => ''];

        return [
            'rotulo' => $padrao['rotulo'],
            'auxiliar' => $padrao['auxiliar'],
            'ajuda' => (string) config('quiz.help-industria.campos.'.$campo, ''),
        ];
    }

    /** Depois de editar ou restaurar: a próxima leitura busca de novo no banco. */
    public static function esquecer(): void
    {
        Cache::forget(self::CACHE);
        app()->forgetInstance(self::MEMO);
    }

    /** @return array<string, array{bloco: int, tipo: string, rotulo: string, auxiliar: string, ajuda: string, editado: bool}> */
    private static function montar(): array
    {
        $editados = QuizTexto::query()->get()->keyBy('campo');
        $textos = [];

        foreach (CatalogoPerguntas::PADRAO as $campo => $padrao) {
            $editado = $editados->get($campo);
            $original = self::original($campo);
            $textos[$campo] = [
                'bloco' => $padrao['bloco'],
                'tipo' => $padrao['tipo'],
                'rotulo' => $editado->rotulo ?? $original['rotulo'],
                'auxiliar' => $editado->auxiliar ?? $original['auxiliar'],
                'ajuda' => $editado->ajuda ?? $original['ajuda'],
                'editado' => $editado !== null,
            ];
        }

        return $textos;
    }
}
