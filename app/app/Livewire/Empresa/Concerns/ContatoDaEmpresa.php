<?php

declare(strict_types=1);

namespace App\Livewire\Empresa\Concerns;

/**
 * PDR-019 — e-mail e telefone/WhatsApp da empresa, opcionais. A tela mantém o telefone
 * com máscara; a validação e a gravação usam só os dígitos.
 */
trait ContatoDaEmpresa
{
    public string $email = '';

    public string $telefone = '';

    /** @return array{email: string|null, telefone: string|null} */
    private function dadosContato(): array
    {
        $email = trim($this->email);
        $telefone = preg_replace('/\D+/', '', $this->telefone) ?? '';

        return [
            'email' => $email === '' ? null : $email,
            'telefone' => $telefone === '' ? null : $telefone,
        ];
    }

    /** @return array<string, list<string>> */
    private function regrasContato(): array
    {
        return [
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'telefone' => ['nullable', 'string', 'regex:/^[1-9]{2}9?\d{8}$/'],
        ];
    }

    /** @return array<string, string> */
    private function mensagensContato(): array
    {
        return [
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail não pode passar de 255 caracteres.',
            'telefone.regex' => 'Informe um telefone válido (DDD + número).',
        ];
    }
}
