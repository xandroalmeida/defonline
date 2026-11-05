<?php

declare(strict_types=1);

namespace App\Livewire\Empresa;

use App\Livewire\Empresa\Concerns\ContatoDaEmpresa;
use App\Models\EmpresaAnalisada;
use App\Observabilidade\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * PDR-019 — contato da empresa em `/empresas/{id}`. É como as empresas já cadastradas
 * recebem o dado, e como o usuário corrige o que digitou no cadastro.
 */
final class Contato extends Component
{
    use ContatoDaEmpresa;

    #[Locked]
    public string $empresaId;

    public bool $salvo = false;

    public function mount(EmpresaAnalisada $empresa): void
    {
        $this->empresaId = $empresa->id;
        $this->email = (string) $empresa->email;
        $this->telefone = (string) $empresa->telefoneFormatado();
    }

    public function salvar(): void
    {
        $this->salvo = false;

        $dados = $this->dadosContato();
        Validator::make($dados, $this->regrasContato(), $this->mensagensContato())->validate();

        $empresa = EmpresaAnalisada::query()->findOrFail($this->empresaId);
        $alterados = array_keys(array_filter($dados, fn (?string $valor, string $campo): bool => $valor !== $empresa->{$campo}, ARRAY_FILTER_USE_BOTH));

        if ($alterados !== []) {
            $empresa->update($dados);

            $usuarioId = (string) Auth::id();
            // Só os campos alterados: os valores são dado de contato.
            AuditLogger::log(
                action: 'empresa.contato_alterado',
                subjectType: 'EmpresaAnalisada',
                subjectId: $empresa->id,
                actorType: 'user',
                actorId: $usuarioId,
                usuarioId: $usuarioId,
                context: ['campos' => $alterados],
            );
        }

        $this->email = (string) $empresa->email;
        $this->telefone = (string) $empresa->telefoneFormatado();
        $this->salvo = true;
    }

    public function render(): View
    {
        return view('livewire.empresa.contato');
    }
}
