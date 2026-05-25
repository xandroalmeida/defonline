<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CalcularDiagnostico;
use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Popula o ambiente de desenvolvimento com 5 Diagnósticos demo — um por fixture
 * canônico da STORY-028 (saudavel / atencao / alerta / ncg-negativo / 70pct-indisponivel).
 *
 * Permite ao programador da STORY-029 abrir cada relatório no browser sem depender
 * de o quiz da STORY-027 estar funcional. **Somente dev** — não roda em homol/prod.
 *
 * Uso:
 *   php artisan db:seed --class=DiagnosticosDemoSeeder
 *
 * Idempotente: se o usuário demo já existe, reutiliza-o (acumulando empresas/diagnósticos
 * a cada execução). Para limpar, recrie o banco via `php artisan migrate:fresh`.
 */
final class DiagnosticosDemoSeeder extends Seeder
{
    private const EMAIL_DEMO = 'roberto.demo@defonline.local';

    private const FIXTURES = [
        'saudavel' => 'quiz_industria_saudavel.json',
        'atencao' => 'quiz_industria_atencao.json',
        'alerta' => 'quiz_industria_alerta.json',
        'ncg_negativo' => 'quiz_industria_ncg_negativo.json',
        '70pct_indisponivel' => 'quiz_industria_70pct_indisponivel.json',
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'development', 'testing')) {
            $this->command?->warn('DiagnosticosDemoSeeder é somente dev — abortando.');

            return;
        }

        $usuario = Usuario::firstWhere('email', self::EMAIL_DEMO)
            ?? Usuario::factory()->create([
                'email' => self::EMAIL_DEMO,
                'nome' => 'Roberto Demo',
            ]);

        $action = app(CalcularDiagnostico::class);

        foreach (self::FIXTURES as $cenario => $arquivo) {
            $empresa = EmpresaAnalisada::factory()
                ->create([
                    'usuario_id' => $usuario->id,
                    'razao_social' => "Demo Indústria — {$cenario}",
                    'nome_fantasia' => "Demo · {$cenario}",
                ]);

            $payload = $this->carregarFixture($arquivo);
            $diag = $action->execute($empresa, $payload);

            $this->command?->info("✓ {$cenario}: /diagnosticos/{$diag->id}");
        }

        $this->command?->info("Usuário demo: {$usuario->email} (senha: senha-de-teste-1234)");
    }

    /**
     * @return array<string, mixed>
     */
    private function carregarFixture(string $arquivo): array
    {
        $caminho = base_path("tests/Domain/Motor/Fixtures/{$arquivo}");
        $json = (string) file_get_contents($caminho);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
