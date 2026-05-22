<?php

declare(strict_types=1);

use App\Mail\EmailConfirmacaoMessage;

it('expõe subject "Confirme seu email — DEFOnline"', function () {
    $msg = new EmailConfirmacaoMessage('Roberto', 'https://exemplo.test/email/confirmar/X');

    expect($msg->envelope()->subject)->toBe('Confirme seu email — DEFOnline');
});

it('aponta para a view mail.email-confirmacao', function () {
    $msg = new EmailConfirmacaoMessage('Roberto', 'https://exemplo.test/email/confirmar/X');

    expect($msg->content()->view)->toBe('mail.email-confirmacao');
});

it('mantém nome e link como readonly properties', function () {
    $msg = new EmailConfirmacaoMessage('Maria', 'https://exemplo.test/x');

    expect($msg->nome)->toBe('Maria');
    expect($msg->link)->toBe('https://exemplo.test/x');
});
