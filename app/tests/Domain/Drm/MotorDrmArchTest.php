<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\MotorDrm;
use App\Domain\Drm\Setor;
use Carbon\CarbonImmutable;

require_once __DIR__.'/MotorDrmHelpers.php';

/*
|-------------------------------------------------------------------------
| STORY-048 CA-7 (pureza) e CA-9 (versionamento).
|
| Mesmo padrão do motor do Diagnóstico: o que o motor NÃO pode tocar é verificado
| por teste, não por disciplina. Cobre o motor e tudo que ele usa para calcular.
|-------------------------------------------------------------------------
*/

const MOTOR_DRM_ARQUIVOS = ['MotorDrm.php', 'Percentual.php', 'Dinheiro.php', 'Grupo.php', 'Setor.php', 'Modo.php', 'Competencia.php'];

/** Código sem comentários: o que importa é o que roda, não o que a documentação cita. */
function motorDrm_codigoFonte(string $arquivo): string
{
    $codigo = '';
    foreach (token_get_all((string) file_get_contents(app_path("Domain/Drm/{$arquivo}"))) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $codigo .= is_array($token) ? $token[1] : $token;
    }

    return $codigo;
}

it('CA-7 — o motor não lê banco, relógio, sessão, usuário, requisição nem arquivo', function (string $proibido) {
    $achados = [];
    foreach (MOTOR_DRM_ARQUIVOS as $arquivo) {
        // `Dinheiro::mascara()` formata para a tela (number_format sobre float) e o motor nunca a chama;
        // o ponto flutuante é vetado onde se calcula, e a saída inteira é verificada sem float (MotorDrmTest).
        if ($arquivo === 'Dinheiro.php' && str_contains($proibido, 'floatval')) {
            continue;
        }
        if (preg_match($proibido, motorDrm_codigoFonte($arquivo)) === 1) {
            $achados[] = $arquivo;
        }
    }

    expect($achados)->toBe([]);
})->with([
    'banco (DB::)' => ['/\bDB::/'],
    'Eloquent / Models' => ['/App\\\\Models\\\\|Illuminate\\\\Database/'],
    'relógio: now()' => ['/\bnow\s*\(/'],
    'relógio: Carbon::now/today/parse sem argumento' => ['/(Carbon|CarbonImmutable)::(now|today|tomorrow|yesterday)\s*\(/'],
    'relógio: time()/date()/new DateTime sem argumento' => ['/\b(time|date|microtime|hrtime)\s*\(|new\s+\\\\?DateTime(Immutable)?\s*\(\s*\)/'],
    'aleatoriedade' => ['/\b(rand|mt_rand|random_int|shuffle|uniqid)\s*\(|Str::(uuid|random|ulid)/'],
    'autenticação (Auth, auth())' => ['/\bAuth::|\bauth\s*\(/'],
    'sessão e requisição' => ['/\bsession\s*\(|\bSession::|\brequest\s*\(|\bRequest::/'],
    'sistema de arquivos / rede' => ['/\b(file_get_contents|file_put_contents|fopen|Storage::|File::|Http::|curl_)/'],
    'cache e filas' => ['/\bCache::|\bQueue::|\bLog::/'],
    'ponto flutuante' => ['/\b(floatval|round|floor|ceil|fdiv|number_format)\s*\(|\(float\)|\(double\)|bc(add|sub|div|mul)/'],
]);

it('CA-7 — o motor não depende do motor, dos models nem do canonicalizador do Diagnóstico', function () {
    $fonte = motorDrm_codigoFonte('MotorDrm.php');

    expect($fonte)->not->toContain('Domain\\Motor')->not->toContain('QuizPayload')->not->toContain('matrix_version');
});

it('CA-7 — mesma entrada, mesma saída, bit a bit, em instantes diferentes do relógio', function () {
    $payload = motorDrm_comercioCompleto();
    $chamar = fn () => (new MotorDrm)->calcular($payload, Setor::Comercio, Competencia::deTexto('2026-09'), new DateTimeImmutable('2026-10-02T12:00:00+00:00'));

    CarbonImmutable::setTestNow('2030-01-01 00:00:00');
    $a = $chamar();
    CarbonImmutable::setTestNow('2041-07-15 23:59:59');
    $b = $chamar();
    CarbonImmutable::setTestNow();

    expect(json_encode($b))->toBe(json_encode($a))
        ->and($a['gerado_em'])->toBe('2026-10-02T12:00:00+00:00');
});

it('CA-9 — a versão estampada é a do contador próprio do DRM', function () {
    config(['drm.motor_drm_version' => '7.8.9']);
    $d = motorDrm_calcular(motorDrm_comercioCompleto());

    expect($d['motor_drm_version'])->toBe('7.8.9')->and(array_keys($d))->not->toContain('matrix_version');
});

it('CA-9 — a versão do motor é independente da do Diagnóstico e da do conteúdo', function () {
    $antes = motorDrm_calcular(motorDrm_comercioCompleto());

    config(['motor.version' => '9.9.9', 'motor.matrix_version' => '9.9.9', 'drm.conteudo_version' => '9.9.9']);
    $depois = motorDrm_calcular(motorDrm_comercioCompleto());

    expect($depois['motor_drm_version'])->toBe($antes['motor_drm_version'])->toBe('1.0.0');
});

it('CA-9 — a versão vigente é 1.0.0 e é um semver', function () {
    expect(config('drm.motor_drm_version'))->toBe('1.0.0');
});
