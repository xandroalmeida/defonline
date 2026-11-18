<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CalcularDiagnostico;
use App\Jobs\AvisarDemandaCaptacao;
use App\Models\CaptacaoConfig;
use App\Models\DemandaCaptacao;
use App\Models\EmpresaAnalisada;
use App\Models\LoteCredito;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * STORY-102 — massa local para validar a demanda de captação. **Somente dev.**
 *
 *   php artisan db:seed --class=CaptacaoDemoSeeder
 *
 * Cria o usuário `captacao.demo@defonline.local` (senha `senha-de-teste-1234`) com créditos, uma
 * empresa livre para fazer o quiz na mão e quatro demandas prontas (no prazo, vencida, sem
 * autorização e valor alto). Cadastra `captacao@defonline.local` como e-mail da equipe e põe na fila
 * os avisos das demandas autorizadas: os e-mails aparecem no Mailpit.
 */
final class CaptacaoDemoSeeder extends Seeder
{
    private const EMAIL = 'captacao.demo@defonline.local';

    public function run(): void
    {
        if (! app()->environment('local', 'development', 'testing')) {
            $this->command?->warn('CaptacaoDemoSeeder é somente dev — abortando.');

            return;
        }

        $usuario = Usuario::firstWhere('email', self::EMAIL)
            ?? Usuario::factory()->create(['email' => self::EMAIL, 'nome' => 'Carla Captação', 'telefone' => '11987654321']);

        LoteCredito::query()->create([
            'usuario_id' => $usuario->id, 'quantidade_comprada' => 20, 'quantidade_restante' => 20,
            'status' => 'ativo', 'data_compra' => now(), 'data_expiracao' => now()->addYear(),
        ]);

        CaptacaoConfig::atual()->update(['email' => 'captacao@defonline.local']);

        $livre = $this->empresa($usuario, 'Metalúrgica Quiz Livre Ltda', 'Quiz Livre');
        $this->command?->info("Quiz livre: /empresas/{$livre->id}/diagnosticos/novo");

        $cenarios = [
            ['Ferragens No Prazo Ltda', 'No Prazo', '150000.00', true, 0],
            ['Usinagem Vencida Ltda', 'Vencida', '80000.00', true, 10],
            ['Plásticos Sem Contato Ltda', 'Sem Contato', '40000.00', false, 2],
            ['Fundição Valor Alto S.A.', 'Valor Alto', '2500000.00', true, 1],
        ];

        foreach ($cenarios as [$razao, $fantasia, $valor, $autoriza, $diasAtras]) {
            $empresa = $this->empresa($usuario, $razao, $fantasia);
            $diagnostico = app(CalcularDiagnostico::class)->execute($empresa, $this->payload($valor));

            $demanda = DemandaCaptacao::query()->create([
                'diagnostico_id' => $diagnostico->id, 'usuario_id' => $usuario->id,
                'empresa_analisada_id' => $empresa->id, 'valor_captar' => $valor, 'autoriza_contato' => $autoriza,
            ]);
            $demanda->forceFill(['created_at' => now()->subDays($diasAtras)])->save();

            if ($autoriza) {
                AvisarDemandaCaptacao::dispatch($demanda->id);
            }

            $this->command?->info("✓ {$fantasia}: /diagnosticos/{$diagnostico->id}");
        }

        $this->command?->info('Usuário: '.self::EMAIL.' (senha: senha-de-teste-1234) · Backoffice: /backoffice/captacao · Mailpit: http://localhost:8025');
    }

    private function empresa(Usuario $usuario, string $razao, string $fantasia): EmpresaAnalisada
    {
        return EmpresaAnalisada::factory()->create([
            'usuario_id' => $usuario->id, 'razao_social' => $razao, 'nome_fantasia' => $fantasia,
            'email' => 'contato@'.strtolower(str_replace(' ', '', $fantasia)).'.com.br', 'telefone' => '1133334444',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(string $valor): array
    {
        $json = (string) file_get_contents(base_path('tests/Domain/Motor/Fixtures/quiz_industria_saudavel.json'));
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return [...$payload, 'Q17' => true, 'Q18' => $valor, 'Q19' => '60000.00', 'Q20' => '10000.00',
            'socios' => [['cpf' => '52998224725', 'nome' => 'Ana Lima'], ['cpf' => '11144477735', 'nome' => 'Bruno Costa']]];
    }
}
