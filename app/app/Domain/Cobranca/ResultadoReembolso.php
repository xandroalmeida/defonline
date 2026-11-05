<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-085 / ADR-010 §1 — o que o provedor respondeu ao pedido de estorno.
 *
 * - `Concluido`: o dinheiro já voltou (cartão estornado por inteiro);
 * - `AguardandoAutorizacao`: o provedor exige que alguém autorize no painel dele (Pix, ação crítica);
 * - `EmAndamento`: aceito, mas ainda não concluído (cartão em processamento no adquirente);
 * - `Recusado`: o provedor respondeu e não aceitou (já estornada, prazo, saldo...).
 */
enum ResultadoReembolso: string
{
    case Concluido = 'concluido';
    case AguardandoAutorizacao = 'aguardando_autorizacao';
    case EmAndamento = 'em_andamento';
    case Recusado = 'recusado';
}
