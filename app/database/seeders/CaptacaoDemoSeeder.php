<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CalcularDiagnostico;
use App\Domain\Captacao\StatusDemanda;
use App\Jobs\AvisarDemandaCaptacao;
use App\Models\CaptacaoConfig;
use App\Models\DemandaCaptacao;
use App\Models\EmpresaAnalisada;
use App\Models\LoteCredito;
use App\Models\Usuario;
use App\Services\Captacao\GerirDemandaCaptacao;
use Illuminate\Database\Seeder;

/**
 * STORY-102 — massa local para validar a demanda de captação. **Somente dev.**
 *
 *   php artisan db:seed --class=CaptacaoDemoSeeder
 *
 * Cria o usuário `captacao.demo@defonline.local` (senha `senha-de-teste-1234`) com créditos, uma
 * empresa livre para fazer o quiz na mão e demandas em todas as etapas do quadro (no prazo, vencida,
 * sem autorização, em análise, respondida e encerrada), com responsável e notas da equipe. Cadastra `captacao@defonline.local` como e-mail da equipe e põe na fila
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

        // Quem cuida das demandas no quadro: a equipe por configuração (BACKOFFICE_EMAILS).
        $equipe = GerirDemandaCaptacao::responsaveisPossiveis();
        $gerir = app(GerirDemandaCaptacao::class);

        // [razão, fantasia, valor, autoriza, dias atrás, etapa, com responsável, nota]
        $cenarios = [
            ['Ferragens No Prazo Ltda', 'No Prazo', '150000.00', true, 0, StatusDemanda::Nova, false, null],
            ['Usinagem Vencida Ltda', 'Vencida', '80000.00', true, 10, StatusDemanda::Nova, false, null],
            ['Plásticos Sem Contato Ltda', 'Sem Contato', '40000.00', false, 2, StatusDemanda::Nova, false, null],
            ['Fundição Valor Alto S.A.', 'Valor Alto', '2500000.00', true, 1, StatusDemanda::EmAnalise, true, 'Liguei, pediu proposta de capital de giro até sexta.'],
            ['Têxtil Em Análise Atrasada Ltda', 'Análise Atrasada', '300000.00', true, 12, StatusDemanda::EmAnalise, true, 'Aguardando o balanço do último ano.'],
            ['Moveleira Respondida Ltda', 'Respondida', '120000.00', true, 6, StatusDemanda::Respondida, true, 'Enviei as opções de crédito por e-mail.'],
            ['Gráfica Encerrada Ltda', 'Encerrada', '60000.00', true, 20, StatusDemanda::Encerrada, true, 'Cliente desistiu da captação por ora.'],
        ];

        foreach ($cenarios as [$razao, $fantasia, $valor, $autoriza, $diasAtras, $etapa, $comResponsavel, $nota]) {
            $empresa = $this->empresa($usuario, $razao, $fantasia);
            $diagnostico = app(CalcularDiagnostico::class)->execute($empresa, $this->payload($valor));

            $demanda = DemandaCaptacao::query()->create([
                'diagnostico_id' => $diagnostico->id, 'usuario_id' => $usuario->id,
                'empresa_analisada_id' => $empresa->id, 'valor_captar' => $valor, 'autoriza_contato' => $autoriza,
            ]);
            $demanda->forceFill(['created_at' => now()->subDays($diasAtras)])->save();

            if ($autoriza && $etapa === StatusDemanda::Nova) {
                AvisarDemandaCaptacao::dispatch($demanda->id);
            }

            $autor = $equipe->first();
            if ($autor !== null) {
                if ($comResponsavel) {
                    $gerir->atribuir($demanda, $autor, $autor);
                }
                foreach (StatusDemanda::cases() as $passo) {
                    if ($passo === StatusDemanda::Nova) {
                        continue;
                    }
                    if (array_search($passo, StatusDemanda::cases(), true) > array_search($etapa, StatusDemanda::cases(), true)) {
                        break;
                    }
                    $gerir->mover($demanda, $passo, $autor);
                }
                if ($nota !== null) {
                    $gerir->anotar($demanda, $nota, $autor);
                }
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
