<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Observabilidade\AuditLogger;

it('persists an audit log entry with request_id and action', function () {
    $log = AuditLogger::log(
        action: 'usuario.created',
        subjectType: 'Usuario',
        subjectId: '0190b1aa-0000-7000-8000-000000000001',
        actorType: 'user',
        usuarioId: '0190b1aa-0000-7000-8000-000000000001',
        after: ['nome' => 'Roberto'],
    );

    expect($log)->toBeInstanceOf(AuditLog::class);
    expect($log->action)->toBe('usuario.created');
    expect($log->request_id)->not->toBeEmpty();
});

it('forbids update on AuditLog model', function () {
    $log = AuditLogger::log(action: 'test', subjectType: 'Test');

    expect(fn () => $log->update(['action' => 'mudado']))->toThrow(RuntimeException::class);
});

it('forbids delete on AuditLog model', function () {
    $log = AuditLogger::log(action: 'test', subjectType: 'Test');

    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
});
