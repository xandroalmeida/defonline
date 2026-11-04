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
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-068 / PDR-016 — manutenção dos 117 textos da matriz de recomendações.
 *
 * Só o texto muda. A faixa numérica é informativa: alterá-la mudaria o farol, que é
 * cálculo. Um texto novo vale para os diagnósticos seguintes; os emitidos guardam o
 * texto da época no próprio snapshot.
 */
#[Layout('components.layouts.app')]
final class Recomendacoes extends Component
{
    use ExigePermissao;

    public const TEXTO_MAX = 300;

    public const SETORES = ['industria' => 'Indústria', 'comercio' => 'Comércio', 'servicos' => 'Serviços'];

    public const FAROIS = ['verde', 'amarelo', 'vermelho'];

    public string $setor = 'industria';

    public string $indicador = IndicadorFormatter::ORDEM_ESSENCIAIS[0];

    public ?string $editandoId = null;

    public ?string $salvoId = null;

    public string $texto = '';

    public function mount(): void
    {
        $this->auditar('backoffice.recomendacoes_listadas');
    }

    public function updatedSetor(): void
    {
        if (! array_key_exists($this->setor, self::SETORES)) {
            $this->setor = 'industria';
        }

        $this->limpar();
    }

    public function updatedIndicador(): void
    {
        if (! in_array($this->indicador, IndicadorFormatter::ORDEM_ESSENCIAIS, true)) {
            $this->indicador = IndicadorFormatter::ORDEM_ESSENCIAIS[0];
        }

        $this->limpar();
    }

    public function editar(string $id): void
    {
        $registro = Recomendacao::query()->findOrFail($id);

        $this->limpar();
        $this->editandoId = $registro->id;
        $this->texto = $registro->texto;
    }

    public function salvar(): void
    {
        $registro = Recomendacao::query()->findOrFail($this->editandoId);

        // O relatório mostra o texto em uma linha só: quebras não têm efeito ali.
        $this->texto = trim((string) preg_replace('/\s+/u', ' ', $this->texto));

        $this->validate([
            'texto' => ['required', 'string', 'max:'.self::TEXTO_MAX],
        ], [
            'texto.required' => 'Informe o texto da recomendação.',
            'texto.max' => 'O texto pode ter até '.self::TEXTO_MAX.' caracteres.',
        ]);

        $antes = $registro->texto;
        $fonte = app(FonteRecomendacoes::class);

        if ($antes !== $this->texto) {
            $versaoAntes = $fonte->versao($registro->setor);
            $registro->update(['texto' => $this->texto, 'editado_em' => now()]);

            $this->auditar(
                'recomendacoes.texto_alterado',
                $registro->id,
                ['texto' => $antes, 'matrix_version' => $versaoAntes],
                ['texto' => $this->texto, 'matrix_version' => $fonte->versao($registro->setor)],
                $registro->only('setor', 'indicador', 'farol'),
            );
        }

        $this->limpar();
        $this->salvoId = $registro->id;
    }

    public function limpar(): void
    {
        $this->reset('editandoId', 'salvoId', 'texto');
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.backoffice.recomendacoes', [
            'setores' => self::SETORES,
            'indicadores' => array_intersect_key(IndicadorFormatter::NOMES, array_flip(IndicadorFormatter::ORDEM_ESSENCIAIS)),
            'versao' => app(FonteRecomendacoes::class)->versao($this->setor),
            'recomendacoes' => Recomendacao::query()
                ->where(['setor' => $this->setor, 'indicador' => $this->indicador])
                ->get()
                ->sortBy(fn (Recomendacao $r): int|false => array_search($r->farol, self::FAROIS, true)),
        ])->layoutData(['title' => 'Recomendações']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::EditarRecomendacoes;
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
