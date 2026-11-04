<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\GlossarioTermo;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-067 / PDR-013 — manutenção do glossário que aparece no relatório.
 */
#[Layout('components.layouts.app')]
final class Glossario extends Component
{
    use ExigePermissao;

    public const TERMO_MAX = 120;

    public const DEFINICAO_MAX = 1000;

    public ?string $editandoId = null;

    public string $termo = '';

    public string $definicao = '';

    public function mount(): void
    {
        $this->auditar('backoffice.glossario_listado');
    }

    public function salvar(): void
    {
        $this->termo = trim($this->termo);
        $this->definicao = trim($this->definicao);

        $this->validate([
            'termo' => ['required', 'string', 'max:'.self::TERMO_MAX, Rule::unique('glossario_termos', 'termo')->ignore($this->editandoId)],
            'definicao' => ['required', 'string', 'max:'.self::DEFINICAO_MAX],
        ], [
            'termo.required' => 'Informe o termo.',
            'termo.max' => 'O termo pode ter até '.self::TERMO_MAX.' caracteres.',
            'termo.unique' => 'Esse termo já está no glossário.',
            'definicao.required' => 'Informe a definição.',
            'definicao.max' => 'A definição pode ter até '.self::DEFINICAO_MAX.' caracteres.',
        ]);

        $dados = ['termo' => $this->termo, 'definicao' => $this->definicao];

        if ($this->editandoId === null) {
            $registro = GlossarioTermo::query()->create([...$dados, 'ordem' => (int) GlossarioTermo::query()->max('ordem') + 1]);
            $this->auditar('glossario.termo_criado', $registro->id, null, $dados);
        } else {
            $registro = GlossarioTermo::query()->findOrFail($this->editandoId);
            $antes = $registro->only('termo', 'definicao');
            $registro->update($dados);
            $this->auditar('glossario.termo_alterado', $registro->id, $antes, $dados);
        }

        $this->limpar();
    }

    public function editar(string $id): void
    {
        $registro = GlossarioTermo::query()->findOrFail($id);

        $this->resetValidation();
        $this->editandoId = $registro->id;
        $this->termo = $registro->termo;
        $this->definicao = $registro->definicao;
    }

    public function excluir(string $id): void
    {
        $registro = GlossarioTermo::query()->findOrFail($id);
        $antes = $registro->only('termo', 'definicao', 'ordem');
        $registro->delete();
        $this->auditar('glossario.termo_excluido', $id, $antes);

        if ($this->editandoId === $id) {
            $this->limpar();
        }
    }

    public function mover(string $id, int $direcao): void
    {
        $ids = GlossarioTermo::query()->orderBy('ordem')->pluck('id')->all();
        $origem = array_search($id, $ids, true);
        $destino = $origem === false ? null : $origem + ($direcao < 0 ? -1 : 1);

        if ($origem === false || ! isset($ids[$destino])) {
            return;
        }

        [$ids[$origem], $ids[$destino]] = [$ids[$destino], $ids[$origem]];

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $i => $termoId) {
                GlossarioTermo::query()->whereKey($termoId)->update(['ordem' => $i + 1]);
            }
        });

        $this->auditar('glossario.termo_movido', $id, ['posicao' => $origem + 1], ['posicao' => $destino + 1]);
    }

    public function limpar(): void
    {
        $this->reset('editandoId', 'termo', 'definicao');
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.backoffice.glossario', [
            'termos' => GlossarioTermo::query()->orderBy('ordem')->get(),
        ])->layoutData(['title' => 'Glossário']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::EditarGlossario;
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $depois
     */
    private function auditar(string $action, ?string $subjectId = null, ?array $antes = null, ?array $depois = null): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: $action,
            subjectType: 'GlossarioTermo',
            subjectId: $subjectId,
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            before: $antes,
            after: $depois,
        );
    }
}
