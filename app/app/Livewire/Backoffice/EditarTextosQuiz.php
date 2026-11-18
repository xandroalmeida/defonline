<?php

declare(strict_types=1);

namespace App\Livewire\Backoffice;

use App\Domain\Quiz\CatalogoPerguntas;
use App\Livewire\Backoffice\Concerns\ExigePermissao;
use App\Models\AuditLog;
use App\Models\QuizTexto;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use App\Support\PermissaoAdmin;
use App\Support\TextosQuiz;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * STORY-106 — a equipe edita o enunciado, o texto auxiliar e a ajuda (?) de cada pergunta do quiz,
 * com prévia, histórico e "restaurar o original". Só muda o texto: o cálculo, as validações e os
 * diagnósticos já feitos não mudam.
 */
#[Layout('components.layouts.app')]
final class EditarTextosQuiz extends Component
{
    use ExigePermissao;

    public const ROTULO_MAX = 160;

    public const AUXILIAR_MAX = 300;

    public const AJUDA_MAX = 1200;

    public ?string $editando = null;

    public string $rotulo = '';

    public string $auxiliar = '';

    public string $ajuda = '';

    /** Mensagem curta depois de salvar ou restaurar. */
    public ?string $aviso = null;

    public function mount(): void
    {
        $this->editar(array_key_first(CatalogoPerguntas::PADRAO));
    }

    public function editar(string $campo): void
    {
        abort_unless(array_key_exists($campo, CatalogoPerguntas::PADRAO), 404);

        $textos = TextosQuiz::todos()[$campo];
        $this->editando = $campo;
        $this->rotulo = $textos['rotulo'];
        $this->auxiliar = $textos['auxiliar'];
        $this->ajuda = $textos['ajuda'];
        $this->aviso = null;
        $this->resetValidation();
    }

    public function salvar(): void
    {
        $campo = $this->campoEmEdicao();
        $this->rotulo = trim($this->rotulo);
        $this->auxiliar = trim($this->auxiliar);
        $this->ajuda = trim($this->ajuda);

        $this->validate([
            'rotulo' => ['required', 'string', 'max:'.self::ROTULO_MAX],
            'auxiliar' => ['nullable', 'string', 'max:'.self::AUXILIAR_MAX],
            'ajuda' => ['nullable', 'string', 'max:'.self::AJUDA_MAX],
        ], [
            'rotulo.required' => 'O enunciado não pode ficar vazio.',
            'rotulo.max' => 'O enunciado pode ter até '.self::ROTULO_MAX.' caracteres.',
            'auxiliar.max' => 'O texto auxiliar pode ter até '.self::AUXILIAR_MAX.' caracteres.',
            'ajuda.max' => 'A ajuda pode ter até '.self::AJUDA_MAX.' caracteres.',
        ]);

        $antes = $this->textoAtual($campo);
        $depois = ['rotulo' => $this->rotulo, 'auxiliar' => $this->auxiliar, 'ajuda' => $this->ajuda];

        if ($antes === $depois) {
            return;
        }

        if ($depois === TextosQuiz::original($campo)) {
            QuizTexto::query()->whereKey($campo)->delete();
        } else {
            QuizTexto::query()->updateOrCreate(['campo' => $campo], [...$depois, 'alterado_por_usuario_id' => Auth::id()]);
        }

        TextosQuiz::esquecer();
        $this->auditar('quiz.texto_alterado', $campo, $antes, $depois);
        $this->editar($campo);
        $this->aviso = 'Salvo. O quiz já mostra o texto novo.';
    }

    public function restaurar(string $campo): void
    {
        abort_unless(array_key_exists($campo, CatalogoPerguntas::PADRAO), 404);

        $antes = $this->textoAtual($campo);
        $original = TextosQuiz::original($campo);

        if ($antes === $original) {
            return;
        }

        QuizTexto::query()->whereKey($campo)->delete();
        TextosQuiz::esquecer();
        $this->auditar('quiz.texto_restaurado', $campo, $antes, $original);

        if ($this->editando === $campo) {
            $this->editar($campo);
            $this->aviso = 'Texto original restaurado.';
        }
    }

    /** Descarta o que foi digitado e volta ao texto salvo. */
    public function descartar(): void
    {
        $this->editar($this->campoEmEdicao());
    }

    public function render(): View
    {
        $historico = $this->editando === null ? collect() : AuditLog::query()
            ->whereIn('action', ['quiz.texto_alterado', 'quiz.texto_restaurado'])
            ->where('subject_type', 'QuizTexto')
            ->where('context->campo', $this->editando)
            ->latest('occurred_at')
            ->limit(10)
            ->get();

        $nomes = Usuario::withTrashed()->whereIn('id', $historico->pluck('actor_id')->filter()->unique())->pluck('nome', 'id');

        $textos = TextosQuiz::todos();
        $atual = $this->editando === null ? null : $textos[$this->editando];

        return view('livewire.backoffice.editar-textos-quiz', [
            'textos' => $textos,
            'original' => $this->editando === null ? null : TextosQuiz::original($this->editando),
            'alterado' => $atual !== null && [trim($this->rotulo), trim($this->auxiliar), trim($this->ajuda)] !== [$atual['rotulo'], $atual['auxiliar'], $atual['ajuda']],
            'historico' => $historico,
            'nomes' => $nomes,
        ])->layoutData(['title' => 'Textos do quiz']);
    }

    protected function permissaoExigida(): PermissaoAdmin
    {
        return PermissaoAdmin::EditarTextosQuiz;
    }

    private function campoEmEdicao(): string
    {
        abort_if($this->editando === null || ! array_key_exists($this->editando, CatalogoPerguntas::PADRAO), 404);

        return $this->editando;
    }

    /** @return array{rotulo: string, auxiliar: string, ajuda: string} */
    private function textoAtual(string $campo): array
    {
        $t = TextosQuiz::todos()[$campo];

        return ['rotulo' => $t['rotulo'], 'auxiliar' => $t['auxiliar'], 'ajuda' => $t['ajuda']];
    }

    /**
     * @param  array<string, string>  $antes
     * @param  array<string, string>  $depois
     */
    private function auditar(string $action, string $campo, array $antes, array $depois): void
    {
        $atorId = (string) Auth::id();

        AuditLogger::log(
            action: $action,
            // `subject_id` é uuid; o campo (Q08, socios) vai no contexto.
            subjectType: 'QuizTexto',
            actorType: 'user',
            actorId: $atorId,
            usuarioId: $atorId,
            before: $antes,
            after: $depois,
            context: ['campo' => $campo],
        );
    }
}
