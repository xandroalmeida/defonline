<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Domain\Cobranca\PrecoCredito;
use App\Infrastructure\Cobranca\Nenhum\ProvedorNaoConfigurado;
use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\CobrancaConfig;
use App\Models\PacoteCredito;
use App\Models\Plano;
use App\Models\TrialConfig;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-071 / PDR-020 — catálogo de cobrança em tela: planos, pacotes de créditos e trial.
 *
 * Nada do catálogo fica fixo em código. Desativar substitui excluir, e o preço de quem já
 * assina é um snapshot (STORY-077), então editar aqui só vale para contratações futuras.
 * Preços digitados em reais ("49,90") e guardados em centavos inteiros.
 */
#[Layout('components.layouts.app')]
final class Planos extends Component
{
    use ExigePermissao;

    public const NOME_MAX = 60;

    public const INTEIRO_MAX = 100000;

    public const PRECO_MAX_CENTAVOS = 99999999;

    /** @var array{id: ?string, nome: string, cota_mensal: string, preco_mensal: string, preco_anual: string, ordem: string, ativo: bool} */
    public array $plano = ['id' => null, 'nome' => '', 'cota_mensal' => '', 'preco_mensal' => '', 'preco_anual' => '', 'ordem' => '1', 'ativo' => true];

    /** @var array{id: ?string, nome: string, quantidade_creditos: string, preco: string, validade_meses: string, ordem: string, ativo: bool} */
    public array $pacote = ['id' => null, 'nome' => '', 'quantidade_creditos' => '', 'preco' => '', 'validade_meses' => '12', 'ordem' => '1', 'ativo' => true];

    /** @var array{ativo: bool, dias: string, analises: string, limite_ip_30d: string} */
    public array $trial = ['ativo' => true, 'dias' => '', 'analises' => '', 'limite_ip_30d' => '3'];

    public ?string $mensagemTrial = null;

    /** STORY-073 CA-1 — interruptor da cobrança (desligado por padrão). */
    public bool $cobrancaAtiva = false;

    public ?string $mensagemCobranca = null;

    public function mount(): void
    {
        $this->auditar('backoffice.planos_listados', 'Plano');
        $this->limparPlano();
        $this->limparPacote();
        $this->carregarTrial();
        $this->cobrancaAtiva = CobrancaConfig::ligada();
    }

    public function salvarPlano(): void
    {
        $this->plano['nome'] = trim($this->plano['nome']);

        $dados = $this->validar([
            'nome' => ['required', 'string', 'max:'.self::NOME_MAX],
            'cota_mensal' => $this->regraInteiro(),
            'preco_mensal' => ['required', $this->regraPreco()],
            'preco_anual' => ['required', $this->regraPreco()],
            'ordem' => $this->regraInteiro(),
        ], [
            'nome.required' => 'Informe o nome do plano.',
            'nome.max' => 'O nome pode ter até '.self::NOME_MAX.' caracteres.',
            'cota_mensal.required' => 'Informe a cota mensal.',
            'cota_mensal.integer' => 'A cota mensal deve ser um número inteiro.',
            'cota_mensal.min' => 'A cota mensal deve ser de pelo menos 1 análise.',
            'cota_mensal.max' => 'A cota mensal é grande demais.',
            'preco_mensal.required' => 'Informe o preço mensal.',
            'preco_anual.required' => 'Informe o preço anual.',
            'ordem.required' => 'Informe a ordem.',
            'ordem.integer' => 'A ordem deve ser um número inteiro.',
            'ordem.min' => 'A ordem deve ser 1 ou mais.',
            'ordem.max' => 'A ordem é grande demais.',
        ], 'plano');

        $this->gravar(
            Plano::class,
            $this->plano['id'],
            [
                'nome' => $dados['nome'],
                'cota_mensal' => (int) $dados['cota_mensal'],
                'preco_mensal_centavos' => (int) self::centavos($dados['preco_mensal']),
                'preco_anual_centavos' => (int) self::centavos($dados['preco_anual']),
                'ordem' => (int) $dados['ordem'],
                'ativo' => $this->plano['ativo'],
            ],
            'Plano',
        );

        $this->limparPlano();
    }

    public function editarPlano(string $id): void
    {
        $plano = Plano::query()->findOrFail($id);

        $this->resetValidation();
        $this->plano = [
            'id' => $plano->id,
            'nome' => $plano->nome,
            'cota_mensal' => (string) $plano->cota_mensal,
            'preco_mensal' => self::reais($plano->preco_mensal_centavos),
            'preco_anual' => self::reais($plano->preco_anual_centavos),
            'ordem' => (string) $plano->ordem,
            'ativo' => $plano->ativo,
        ];
    }

    public function alternarPlano(string $id): void
    {
        $plano = Plano::query()->findOrFail($id);
        $this->gravar(Plano::class, $id, ['ativo' => ! $plano->ativo], 'Plano');
    }

    public function limparPlano(): void
    {
        $this->resetValidation();
        $this->plano = [
            'id' => null, 'nome' => '', 'cota_mensal' => '', 'preco_mensal' => '', 'preco_anual' => '',
            'ordem' => (string) ((int) Plano::query()->max('ordem') + 1), 'ativo' => true,
        ];
    }

    public function salvarPacote(): void
    {
        $this->pacote['nome'] = trim($this->pacote['nome']);

        $dados = $this->validar([
            'nome' => ['required', 'string', 'max:'.self::NOME_MAX],
            'quantidade_creditos' => $this->regraInteiro(),
            'preco' => ['required', $this->regraPreco()],
            'validade_meses' => $this->regraInteiro(),
            'ordem' => $this->regraInteiro(),
        ], [
            'nome.required' => 'Informe o nome do pacote.',
            'nome.max' => 'O nome pode ter até '.self::NOME_MAX.' caracteres.',
            'quantidade_creditos.required' => 'Informe a quantidade de créditos.',
            'quantidade_creditos.integer' => 'A quantidade deve ser um número inteiro.',
            'quantidade_creditos.min' => 'A quantidade deve ser de pelo menos 1 crédito.',
            'quantidade_creditos.max' => 'A quantidade é grande demais.',
            'validade_meses.required' => 'Informe a validade em meses.',
            'validade_meses.integer' => 'A validade deve ser um número inteiro de meses.',
            'validade_meses.min' => 'A validade deve ser de pelo menos 1 mês.',
            'validade_meses.max' => 'A validade é grande demais.',
            'preco.required' => 'Informe o preço.',
            'ordem.required' => 'Informe a ordem.',
            'ordem.integer' => 'A ordem deve ser um número inteiro.',
            'ordem.min' => 'A ordem deve ser 1 ou mais.',
            'ordem.max' => 'A ordem é grande demais.',
        ], 'pacote');

        $this->gravar(
            PacoteCredito::class,
            $this->pacote['id'],
            [
                'nome' => $dados['nome'],
                'quantidade_creditos' => (int) $dados['quantidade_creditos'],
                'preco_centavos' => (int) self::centavos($dados['preco']),
                'validade_meses' => (int) $dados['validade_meses'],
                'ordem' => (int) $dados['ordem'],
                'ativo' => $this->pacote['ativo'],
            ],
            'PacoteCredito',
        );

        $this->limparPacote();
    }

    public function editarPacote(string $id): void
    {
        $pacote = PacoteCredito::query()->findOrFail($id);

        $this->resetValidation();
        $this->pacote = [
            'id' => $pacote->id,
            'nome' => $pacote->nome,
            'quantidade_creditos' => (string) $pacote->quantidade_creditos,
            'preco' => self::reais($pacote->preco_centavos),
            'validade_meses' => (string) $pacote->validade_meses,
            'ordem' => (string) $pacote->ordem,
            'ativo' => $pacote->ativo,
        ];
    }

    public function alternarPacote(string $id): void
    {
        $pacote = PacoteCredito::query()->findOrFail($id);
        $this->gravar(PacoteCredito::class, $id, ['ativo' => ! $pacote->ativo], 'PacoteCredito');
    }

    public function limparPacote(): void
    {
        $this->resetValidation();
        $this->pacote = [
            'id' => null, 'nome' => '', 'quantidade_creditos' => '', 'preco' => '', 'validade_meses' => '12',
            'ordem' => (string) ((int) PacoteCredito::query()->max('ordem') + 1), 'ativo' => true,
        ];
    }

    public function salvarTrial(): void
    {
        $dados = $this->validar([
            'dias' => $this->regraInteiro(),
            'analises' => $this->regraInteiro(),
            'limite_ip_30d' => $this->regraInteiro(),
        ], [
            'dias.required' => 'Informe os dias do trial.',
            'dias.integer' => 'Os dias devem ser um número inteiro.',
            'dias.min' => 'O trial deve ter pelo menos 1 dia.',
            'dias.max' => 'Os dias são grandes demais.',
            'analises.required' => 'Informe as análises incluídas.',
            'analises.integer' => 'As análises devem ser um número inteiro.',
            'analises.min' => 'O trial deve incluir pelo menos 1 análise.',
            'analises.max' => 'As análises são grandes demais.',
            'limite_ip_30d.required' => 'Informe o limite de trials por IP.',
            'limite_ip_30d.integer' => 'O limite deve ser um número inteiro.',
            'limite_ip_30d.min' => 'O limite deve ser de pelo menos 1 trial.',
            'limite_ip_30d.max' => 'O limite é grande demais.',
        ], 'trial');

        $config = TrialConfig::atual();
        $this->aplicar($config, ['ativo' => $this->trial['ativo'], 'dias' => (int) $dados['dias'], 'analises' => (int) $dados['analises'], 'limite_ip_30d' => (int) $dados['limite_ip_30d']], 'TrialConfig');

        $this->carregarTrial();
        $this->mensagemTrial = 'Trial salvo.';
    }

    public function salvarCobranca(): void
    {
        // STORY-093 CA-1: ligar a cobrança sem provedor de pagamento válido deixaria o cliente sem como pagar (e o
        // webhook sem como confirmar). Recusa e diz o que falta; desligar sempre pode.
        if ($this->cobrancaAtiva && ! CobrancaConfig::ligada() && ($motivo = ProvedorNaoConfigurado::motivo()) !== null) {
            $this->cobrancaAtiva = false;
            $this->mensagemCobranca = 'Não dá para ligar a cobrança: o provedor de pagamento não está configurado. '.$motivo;

            return;
        }

        $this->aplicar(CobrancaConfig::atual(), ['ativa' => $this->cobrancaAtiva], 'CobrancaConfig');

        $this->cobrancaAtiva = CobrancaConfig::ligada();
        $this->mensagemCobranca = 'Interruptor salvo.';
    }

    public function render(): View
    {
        $planos = Plano::query()->orderBy('ordem')->orderBy('nome')->get();
        $pacotes = PacoteCredito::query()->orderBy('ordem')->orderBy('nome')->get();
        $ativos = $planos->where('ativo', true)->map(fn (Plano $p): array => [
            'nome' => $p->nome, 'cota_mensal' => $p->cota_mensal, 'preco_mensal_centavos' => $p->preco_mensal_centavos,
        ])->values()->all();

        $avisos = [];
        foreach ($pacotes as $p) {
            $avisos[$p->id] = PrecoCredito::planosMaisBaratos($p->preco_centavos, $p->quantidade_creditos, $ativos);
        }

        $precoForm = self::centavos($this->pacote['preco']);
        $quantidadeForm = ctype_digit($this->pacote['quantidade_creditos']) ? (int) $this->pacote['quantidade_creditos'] : 0;

        return view('livewire.backoffice.planos', [
            'planos' => $planos,
            'pacotes' => $pacotes,
            'avisos' => $avisos,
            'avisoFormulario' => $precoForm === null ? [] : PrecoCredito::planosMaisBaratos($precoForm, $quantidadeForm, $ativos),
        ])->layoutData(['title' => 'Planos e pacotes']);
    }

    /** Aceita "49,90", "1.599,00", "R$ 49.90" ou "49". Devolve null se não for um valor monetário não negativo. */
    public static function centavos(string $texto): ?int
    {
        $limpo = trim(str_replace(['R$', ' '], '', $texto));

        if (str_contains($limpo, ',')) {
            $limpo = str_replace(',', '.', str_replace('.', '', $limpo));
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $limpo) !== 1) {
            return null;
        }

        return (int) round(((float) $limpo) * 100);
    }

    public static function reais(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '');
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::GerenciarPlanos;
    }

    /** @return list<string> */
    private function regraInteiro(): array
    {
        return ['required', 'integer', 'min:1', 'max:'.self::INTEIRO_MAX];
    }

    private function regraPreco(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            $texto = trim((string) $valor);

            if (preg_match('/^-\s*\d/', $texto) === 1) {
                $falhar('O preço não pode ser negativo.');
            } elseif (($centavos = self::centavos($texto)) === null) {
                $falhar('Informe o preço em reais, por exemplo 49,90.');
            } elseif ($centavos > self::PRECO_MAX_CENTAVOS) {
                $falhar('O preço é alto demais.');
            }
        };
    }

    /**
     * Valida o grupo de campos (`plano`, `pacote`, `trial`) com o validate() do Livewire,
     * que mantém as mensagens por campo no formulário.
     *
     * @param  array<string, mixed>  $regras
     * @param  array<string, string>  $mensagens
     * @return array<string, mixed>
     */
    private function validar(array $regras, array $mensagens, string $grupo): array
    {
        $comPrefixo = fn (array $porCampo): array => array_combine(
            array_map(fn (string $k): string => "{$grupo}.{$k}", array_keys($porCampo)),
            array_values($porCampo),
        );

        $this->validate($comPrefixo($regras), $comPrefixo($mensagens));

        return $this->{$grupo};
    }

    /**
     * @param  class-string<Plano|PacoteCredito>  $classe
     * @param  array<string, mixed>  $dados
     */
    private function gravar(string $classe, ?string $id, array $dados, string $tipo): void
    {
        if ($id === null) {
            $registro = new $classe;
            $registro->codigo = $this->codigoUnico($classe, $dados['nome']);
            $this->aplicar($registro, $dados, $tipo);

            return;
        }

        $this->aplicar($classe::query()->findOrFail($id), $dados, $tipo);
    }

    /**
     * Grava só se mudou algo e audita antes, depois e autor (CA-8). O criado nasce com
     * "antes" nulo.
     *
     * @param  array<string, mixed>  $dados
     */
    private function aplicar(Model $registro, array $dados, string $tipo): void
    {
        $existia = $registro->exists;
        $campos = array_keys($dados);
        $antes = $existia ? $registro->only($campos) : null;

        $registro->fill([...$dados, 'alterado_por_usuario_id' => Auth::id()]);

        if ($existia && ! $registro->isDirty($campos)) {
            return;
        }

        $registro->save();

        $this->auditar(
            $existia ? 'planos.'.Str::snake($tipo).'_alterado' : 'planos.'.Str::snake($tipo).'_criado',
            $tipo,
            (string) $registro->getKey(),
            $antes,
            $registro->only($campos),
        );
    }

    /** @param class-string<Plano|PacoteCredito> $classe */
    private function codigoUnico(string $classe, string $nome): string
    {
        $base = Str::slug($nome) ?: 'item';
        $codigo = $base;

        for ($i = 2; $classe::query()->where('codigo', $codigo)->exists(); $i++) {
            $codigo = "{$base}-{$i}";
        }

        return $codigo;
    }

    private function carregarTrial(): void
    {
        $config = TrialConfig::atual();
        $this->trial = ['ativo' => $config->ativo, 'dias' => (string) $config->dias, 'analises' => (string) $config->analises, 'limite_ip_30d' => (string) $config->limite_ip_30d];
        $this->mensagemTrial = null;
        $this->resetValidation();
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function auditar(string $action, string $tipo, ?string $subjectId = null, ?array $antes = null, ?array $depois = null): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: $action,
            subjectType: $tipo,
            subjectId: $subjectId,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            before: $antes,
            after: $depois,
        );
    }
}
