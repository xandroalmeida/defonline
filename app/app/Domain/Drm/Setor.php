<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/**
 * Setor da Empresa Analisada para fins do DRM (Anexo K §K.4).
 *
 * A estrutura de linhas da demonstração é a mesma nos três; o setor só decide
 * quais campos de custo aparecem e como o grupo de custo se chama.
 */
enum Setor: string
{
    case Industria = 'industria';
    case Comercio = 'comercio';
    case Servicos = 'servicos';

    /**
     * Setor a partir do CNAE (7 dígitos) da empresa.
     *
     * [ESCALONAMENTO-PO] `empresas_analisadas` não tem coluna de setor e o
     * Quiz fixa "industria" (EPIC-002). Até o PO decidir a origem do dado, o
     * setor é derivado da divisão do CNAE pela convenção IBGE: 05–33 indústria,
     * 45–47 comércio, o resto serviços. Sem CNAE, `Industria` — é o setor sem
     * campo suprimido, então ninguém perde um campo que usaria.
     */
    public static function doCnae(?string $cnae): self
    {
        if ($cnae === null || strlen($cnae) < 2 || ! ctype_digit(substr($cnae, 0, 2))) {
            return self::Industria;
        }

        $divisao = (int) substr($cnae, 0, 2);

        return match (true) {
            $divisao >= 5 && $divisao <= 33 => self::Industria,
            $divisao >= 45 && $divisao <= 47 => self::Comercio,
            default => self::Servicos,
        };
    }

    /**
     * Campos que o setor não coleta; gravados como nulos (Anexo K §K.4).
     *
     * @return list<string>
     */
    public function camposSuprimidos(): array
    {
        return match ($this) {
            self::Industria => [],
            self::Comercio => ['D05'],
            self::Servicos => ['D04', 'D05'],
        };
    }
}
