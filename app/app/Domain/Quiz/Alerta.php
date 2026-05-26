<?php

declare(strict_types=1);

namespace App\Domain\Quiz;

/**
 * Alerta de validação cruzada — uma inconsistência detectada entre blocos do
 * quiz (STORY-034, espec §6.6). Não-bloqueante: descreve o problema e aponta o
 * campo suspeito para correção (mensagem acionável, CA-4).
 */
final class Alerta
{
    /**
     * @param  list<string>  $camposEnvolvidos  Códigos do Anexo A (ex.: ['Q16', 'Q06']).
     * @param  float  $valorEnvolvido  Lado esquerdo da condição — guardado na auditoria.
     */
    public function __construct(
        public readonly string $regra,
        public readonly string $severidade,
        public readonly string $mensagem,
        public readonly array $camposEnvolvidos,
        public readonly string $campoFoco,
        public readonly string $botaoLabel,
        public readonly float $valorEnvolvido,
    ) {}

    /**
     * Forma serializável para o componente Livewire e a view (Livewire não
     * persiste objetos em propriedades públicas).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'regra' => $this->regra,
            'severidade' => $this->severidade,
            'mensagem' => $this->mensagem,
            'campos_envolvidos' => $this->camposEnvolvidos,
            'campo_foco' => $this->campoFoco,
            'botao_label' => $this->botaoLabel,
            'valor_envolvido' => $this->valorEnvolvido,
        ];
    }
}
