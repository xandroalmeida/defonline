<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Situação cadastral da Empresa Analisada na RFB (espec V2 §1.5.2 / NRF §3.1).
 *
 * `nao_informada` é o default para cadastro manual onde o Usuário não soube
 * informar. A STORY-015 (RFB) vai preencher os outros valores via mock/API.
 */
enum SituacaoCadastral: string
{
    case Ativa = 'ativa';
    case Inapta = 'inapta';
    case Baixada = 'baixada';
    case Suspensa = 'suspensa';
    case NaoInformada = 'nao_informada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ativa => 'Ativa',
            self::Inapta => 'Inapta',
            self::Baixada => 'Baixada',
            self::Suspensa => 'Suspensa',
            self::NaoInformada => 'Não informada',
        };
    }
}
