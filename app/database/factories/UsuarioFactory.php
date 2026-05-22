<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
final class UsuarioFactory extends Factory
{
    /** @var class-string<Usuario> */
    protected $model = Usuario::class;

    protected static ?string $senhaCache = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cpf = self::gerarCpfValido();

        return [
            'id' => (string) Str::uuid7(),
            'cpf' => $cpf,
            'nome' => fake('pt_BR')->name(),
            'email' => fake()->unique()->safeEmail(),
            'senha_hash' => self::$senhaCache ??= Hash::make('senha-de-teste-1234'),
            'telefone' => '11'.fake()->numerify('9########'),
            // Factory cria conta JÁ confirmada por default — os testes de fluxo
            // herdam o caso "ok" da STORY-013. Use ->unconfirmed() para o caso
            // explícito de email pendente.
            'email_confirmed_at' => now(),
        ];
    }

    public function unconfirmed(): self
    {
        return $this->state(fn () => ['email_confirmed_at' => null]);
    }

    /**
     * Gera CPF com dígitos verificadores corretos para satisfazer App\Domain\Cpf.
     */
    private static function gerarCpfValido(): string
    {
        do {
            $base = '';
            for ($i = 0; $i < 9; $i++) {
                $base .= random_int(0, 9);
            }
            $cpf = $base.self::calcularDv($base);
            $cpf .= self::calcularDv($cpf);
        } while (preg_match('/^(\d)\1{10}$/', $cpf) === 1);

        return $cpf;
    }

    private static function calcularDv(string $base): string
    {
        $soma = 0;
        $peso = strlen($base) + 1;
        foreach (str_split($base) as $d) {
            $soma += ((int) $d) * $peso;
            $peso--;
        }
        $resto = $soma % 11;

        return (string) ($resto < 2 ? 0 : 11 - $resto);
    }
}
