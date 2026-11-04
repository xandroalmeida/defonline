<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Domain\Motor\FonteRecomendacoes;
use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\Recomendacao;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use App\Support\Relatorio\IndicadorFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * STORY-068 / PDR-016 — manutenção dos 117 textos da matriz de recomendações.
 *
 * Um indicador por vez, com os 9 textos à vista (faixa × segmento) e sempre editáveis.
 * As alterações ficam pendentes, inclusive ao trocar de indicador, até "Salvar". Só o
 * texto muda: a faixa numérica é informativa, porque alterá-la mudaria o farol. Um
 * texto salvo vale para os diagnósticos seguintes; os emitidos guardam o da época.
 */
#[Layout('components.layouts.app')]
final class Recomendacoes extends Component
{
    use ExigePermissao;

    public const TEXTO_MAX = 300;

    public const SETORES = ['industria' => 'Indústria', 'comercio' => 'Comércio', 'servicos' => 'Serviços'];

    public const FAROIS = ['verde', 'amarelo', 'vermelho'];

    /** Nome curto na aba, para os 13 caberem em uma linha. O nome completo vem logo abaixo. */
    public const ROTULOS_ABAS = [
        'margem_bruta' => 'Margem Bruta',
        'margem_ebitda' => 'Margem EBITDA',
        'margem_liquida' => 'Margem Líquida',
        'divida_liquida_ebitda' => 'Dívida / EBITDA',
        'despesas_fin_ebitda' => 'Desp. / EBITDA',
        'fontes_recursos' => 'Fontes',
        'giro_ativo' => 'Giro',
        'ciclo_financeiro' => 'Ciclo',
        'ncg_vendas' => 'NCG / Vendas',
        'pmc' => 'PMC',
        'pme' => 'PME',
        'pmr' => 'PMR',
        'inadimplencia' => 'Inadimplência',
    ];

    #[Locked]
    public string $indicador = IndicadorFormatter::ORDEM_ESSENCIAIS[0];

    /** @var array<string, array<string, array<string, string>>> setor → indicador → farol → texto no banco */
    #[Locked]
    public array $base = [];

    /** @var array<string, array<string, array<string, mixed>>> setor → indicador → farol → texto na tela */
    public array $textos = [];

    public ?string $mensagem = null;

    public function mount(): void
    {
        $this->auditar('backoffice.recomendacoes_listadas');
        $this->carregar();
    }

    public function irPara(string $indicador): void
    {
        if (in_array($indicador, IndicadorFormatter::ORDEM_ESSENCIAIS, true)) {
            $this->indicador = $indicador;
            $this->mensagem = null;
            $this->carregar();
        }
    }

    public function desfazer(string $setor, string $indicador, string $farol): void
    {
        if (isset($this->base[$setor][$indicador][$farol])) {
            $this->textos[$setor][$indicador][$farol] = $this->base[$setor][$indicador][$farol];
            $this->resetValidation("textos.{$setor}.{$indicador}.{$farol}");
        }
    }

    public function descartar(): void
    {
        $this->textos = $this->base;
        $this->mensagem = 'Alterações descartadas.';
        $this->resetValidation();
    }

    public function salvar(): void
    {
        $alterados = $this->alterados();

        if ($alterados === []) {
            return;
        }

        $this->validarAlterados($alterados);

        $fonte = app(FonteRecomendacoes::class);

        DB::transaction(function () use ($alterados, $fonte): void {
            foreach ($alterados as ['setor' => $setor, 'indicador' => $indicador, 'farol' => $farol, 'texto' => $texto]) {
                $registro = Recomendacao::query()
                    ->where(['setor' => $setor, 'indicador' => $indicador, 'farol' => $farol])
                    ->firstOrFail();
                $antes = $registro->texto;
                $versaoAntes = $fonte->versao($setor);

                $registro->update(['texto' => $texto, 'editado_em' => now()]);

                $this->auditar(
                    'recomendacoes.texto_alterado',
                    $registro->id,
                    ['texto' => $antes, 'matrix_version' => $versaoAntes],
                    ['texto' => $texto, 'matrix_version' => $fonte->versao($setor)],
                    ['setor' => $setor, 'indicador' => $indicador, 'farol' => $farol],
                );
            }
        });

        foreach ($alterados as ['setor' => $setor, 'indicador' => $indicador, 'farol' => $farol, 'texto' => $texto]) {
            $this->base[$setor][$indicador][$farol] = $texto;
            $this->textos[$setor][$indicador][$farol] = $texto;
        }

        $total = count($alterados);
        $this->mensagem = $total === 1 ? '1 texto salvo.' : "{$total} textos salvos.";
        $this->resetValidation();
    }

    public function render(): View
    {
        $alterados = $this->alterados();
        $fonte = app(FonteRecomendacoes::class);

        return view('livewire.backoffice.recomendacoes', [
            'setores' => self::SETORES,
            'rotulosAbas' => self::ROTULOS_ABAS,
            'indicadores' => array_map(
                fn (string $codigo): string => IndicadorFormatter::NOMES[$codigo],
                array_combine(IndicadorFormatter::ORDEM_ESSENCIAIS, IndicadorFormatter::ORDEM_ESSENCIAIS),
            ),
            'versoes' => array_map(fn (string $setor): string => $fonte->versao($setor), array_combine(array_keys(self::SETORES), array_keys(self::SETORES))),
            'faixas' => Recomendacao::query()->where('indicador', $this->indicador)->get()
                ->mapWithKeys(fn (Recomendacao $r): array => ["{$r->setor}.{$r->farol}" => $r->faixa]),
            'alterados' => collect($alterados)->map(fn (array $a): string => "{$a['setor']}.{$a['indicador']}.{$a['farol']}")->all(),
            'indicadoresPendentes' => collect($alterados)->pluck('indicador')->unique()->all(),
        ])->layoutData(['title' => 'Recomendações']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::EditarRecomendacoes;
    }

    /** Traz os textos do indicador na primeira visita, sem tocar no que já está pendente. */
    private function carregar(): void
    {
        foreach (Recomendacao::query()->where('indicador', $this->indicador)->get() as $r) {
            if (! isset($this->base[$r->setor][$r->indicador][$r->farol])) {
                $this->base[$r->setor][$r->indicador][$r->farol] = $r->texto;
                $this->textos[$r->setor][$r->indicador][$r->farol] = $r->texto;
            }
        }
    }

    /**
     * Compara com o texto normalizado: o relatório mostra tudo em uma linha, e espaço
     * a mais não é alteração.
     *
     * @return list<array{setor: string, indicador: string, farol: string, texto: string}>
     */
    private function alterados(): array
    {
        $alterados = [];

        foreach ($this->base as $setor => $porIndicador) {
            foreach ($porIndicador as $indicador => $porFarol) {
                foreach ($porFarol as $farol => $original) {
                    $atual = $this->textos[$setor][$indicador][$farol] ?? '';
                    $texto = is_string($atual) ? trim((string) preg_replace('/\s+/u', ' ', $atual)) : '';

                    if ($texto !== $original) {
                        $alterados[] = ['setor' => $setor, 'indicador' => $indicador, 'farol' => $farol, 'texto' => $texto];
                    }
                }
            }
        }

        return $alterados;
    }

    /**
     * Valida tudo antes de gravar qualquer coisa. Se o erro estiver em outro indicador,
     * leva o usuário até ele.
     *
     * @param  list<array{setor: string, indicador: string, farol: string, texto: string}>  $alterados
     */
    private function validarAlterados(array $alterados): void
    {
        $dados = [];
        $regras = [];
        foreach ($alterados as ['setor' => $setor, 'indicador' => $indicador, 'farol' => $farol, 'texto' => $texto]) {
            $dados['textos'][$setor][$indicador][$farol] = $texto;
            $regras["textos.{$setor}.{$indicador}.{$farol}"] = ['required', 'string', 'max:'.self::TEXTO_MAX];
        }

        try {
            validator($dados, $regras, [
                'required' => 'Informe o texto da recomendação.',
                'max' => 'O texto pode ter até '.self::TEXTO_MAX.' caracteres.',
            ])->validate();
        } catch (ValidationException $e) {
            $this->indicador = explode('.', (string) array_key_first($e->errors()))[2];
            $this->mensagem = null;

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     * @param  array<string, mixed>|null  $contexto
     */
    private function auditar(string $action, ?string $subjectId = null, ?array $antes = null, ?array $depois = null, ?array $contexto = null): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: $action,
            subjectType: 'Recomendacao',
            subjectId: $subjectId,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            before: $antes,
            after: $depois,
            context: $contexto,
        );
    }
}
