<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Cpf;
use App\Models\Usuario;
use App\Observabilidade\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cadastro de Usuário (STORY-011 CA-1..CA-6).
 *
 * Campos: CPF (normalizado a dígitos), nome, email (CITEXT unique), senha (bcrypt
 * 12, regra Laravel `Password::min(8)->letters()->numbers()`), confirmação de senha,
 * telefone BR (10-11 dígitos).
 *
 * Em duplicação de CPF/email, a mensagem é genérica ("este dado já está em uso") —
 * conformidade com security-discipline.md (sem vazar existência de cadastro).
 *
 * Audit log (`usuario.cadastrado`) + log estruturado emitidos na mesma transação.
 * Evento de produto `usuario_cadastrado` **não** é emitido aqui — depende da
 * confirmação de email da STORY-013 (ADR-004 §2.2).
 */
#[Layout('layouts.app')]
final class Cadastro extends Component
{
    public string $cpf = '';

    public string $nome = '';

    public string $email = '';

    public string $senha = '';

    public string $senha_confirmation = '';

    public string $telefone = '';

    public function submit(): mixed
    {
        // Estado do componente guarda o valor mascarado (UI). Normalização para
        // dígitos vive APENAS aqui dentro — assim, se a validação falhar, o
        // re-render do Livewire mantém o que o usuário vê com a máscara intacta.
        $dados = [
            'cpf' => Cpf::normalizar($this->cpf),
            'nome' => trim($this->nome),
            'email' => trim($this->email),
            'senha' => $this->senha,
            'senha_confirmation' => $this->senha_confirmation,
            'telefone' => preg_replace('/\D+/', '', $this->telefone) ?? '',
        ];

        $validados = Validator::make($dados, [
            'cpf' => [
                'required',
                'string',
                'size:11',
                function (string $attr, mixed $valor, \Closure $fail): void {
                    if (! is_string($valor) || ! Cpf::valido($valor)) {
                        $fail('Informe um CPF válido.');
                    }
                },
                Rule::unique('usuarios', 'cpf')->withoutTrashed(),
            ],
            'nome' => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('usuarios', 'email')->withoutTrashed(),
            ],
            'senha' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'telefone' => [
                'required',
                'string',
                'regex:/^[1-9]{2}9?\d{8}$/',                  // DDD + 8 ou 9 dígitos
            ],
        ], [
            'cpf.required' => 'Informe o CPF.',
            'cpf.size' => 'O CPF deve ter 11 dígitos.',
            'cpf.unique' => 'Este dado já está em uso.',
            'nome.required' => 'Informe o nome completo.',
            'nome.min' => 'O nome deve ter pelo menos 2 caracteres.',
            'nome.max' => 'O nome não pode passar de 120 caracteres.',
            'email.required' => 'Informe o email.',
            'email.email' => 'Informe um email válido.',
            'email.max' => 'O email não pode passar de 255 caracteres.',
            'email.unique' => 'Este dado já está em uso.',
            'senha.required' => 'Informe a senha.',
            'senha.confirmed' => 'A confirmação de senha não confere.',
            'telefone.required' => 'Informe o telefone WhatsApp.',
            'telefone.regex' => 'Informe um telefone válido (DDD + número).',
        ])->validate();

        $usuario = DB::transaction(function () use ($validados): Usuario {
            $u = Usuario::create([
                'cpf' => $validados['cpf'],
                'nome' => $validados['nome'],
                'email' => $validados['email'],
                'senha_hash' => Hash::make($validados['senha']),
                'telefone' => $validados['telefone'],
            ]);

            AuditLogger::log(
                action: 'usuario.cadastrado',
                subjectType: 'Usuario',
                subjectId: $u->id,
                actorType: 'user',
                actorId: $u->id,
                usuarioId: $u->id,
                after: [
                    'nome' => $u->nome,
                    'email' => $u->email,
                    'cpf' => $u->cpf,
                    'telefone' => $u->telefone,
                ],
                context: [
                    'ip' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 255),
                ],
            );

            return $u;
        });

        // Log estruturado — PII mascarada automaticamente pelo LogSanitizer (ADR-003/004).
        Log::info('usuario.cadastrado', [
            'usuario_id' => $usuario->id,
            'cpf' => $usuario->cpf,
            'email' => $usuario->email,
            'telefone' => $usuario->telefone,
            'module' => 'cadastro',
            'action' => 'cadastrar',
        ]);

        session()->flash('cadastro_sucesso', 'Conta criada com sucesso. Faça login para continuar.');

        return $this->redirect('/login', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.cadastro');
    }
}
