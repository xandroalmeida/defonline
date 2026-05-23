<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\FonteEnriquecimento;
use App\Domain\SituacaoCadastral;
use App\Domain\TipoDocumento;
use App\Domain\Uf;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmpresaAnalisada>
 */
final class EmpresaAnalisadaFactory extends Factory
{
    /** @var class-string<EmpresaAnalisada> */
    protected $model = EmpresaAnalisada::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ufs = Uf::cases();

        return [
            'id' => (string) Str::uuid7(),
            'usuario_id' => Usuario::factory(),
            'tipo_documento' => TipoDocumento::Cnpj,
            'documento' => self::gerarCnpjValido(),
            'razao_social' => fake('pt_BR')->company(),
            'nome_fantasia' => fake('pt_BR')->company(),
            'cnae' => str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'municipio' => fake('pt_BR')->city(),
            'uf' => $ufs[array_rand($ufs)]->value,
            'situacao_cadastral' => SituacaoCadastral::NaoInformada,
            'fonte_enriquecimento' => FonteEnriquecimento::Manual,
            'data_fundacao' => null,
            'enriquecido_at' => null,
        ];
    }

    public function comoCpf(): self
    {
        return $this->state(fn () => [
            'tipo_documento' => TipoDocumento::Cpf,
            'documento' => self::gerarCpfValido(),
        ]);
    }

    /**
     * Gera CNPJ com dígitos verificadores corretos para satisfazer App\Domain\Cnpj.
     */
    public static function gerarCnpjValido(): string
    {
        do {
            $base = '';
            for ($i = 0; $i < 12; $i++) {
                $base .= random_int(0, 9);
            }
            $cnpj = $base.self::calcularDvCnpj($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
            $cnpj .= self::calcularDvCnpj($cnpj, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        } while (preg_match('/^(\d)\1{13}$/', $cnpj) === 1);

        return $cnpj;
    }

    /**
     * Gera CPF com dígitos verificadores corretos para satisfazer App\Domain\Cpf.
     */
    public static function gerarCpfValido(): string
    {
        do {
            $base = '';
            for ($i = 0; $i < 9; $i++) {
                $base .= random_int(0, 9);
            }
            $cpf = $base.self::calcularDvCpf($base);
            $cpf .= self::calcularDvCpf($cpf);
        } while (preg_match('/^(\d)\1{10}$/', $cpf) === 1);

        return $cpf;
    }

    /**
     * @param  list<int>  $pesos
     */
    private static function calcularDvCnpj(string $base, array $pesos): string
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += ((int) $base[$i]) * $peso;
        }
        $resto = $soma % 11;

        return (string) ($resto < 2 ? 0 : 11 - $resto);
    }

    private static function calcularDvCpf(string $base): string
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
