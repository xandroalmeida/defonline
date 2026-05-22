<?php

declare(strict_types=1);

namespace App\Observabilidade;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Mascaramento centralizado de PII em logs (ADR-003 + ADR-004).
 *
 * Único ponto de mascaramento em log estruturado. Aplicado via `Log::tap()` em todos
 * os canais (stdout + daily). Processa recursivamente o `context` e `extra` da linha
 * de log, redigindo chaves sensíveis.
 *
 * Categorias:
 * - credencial: senha/token/api_key/authorization/secret → REDACTED total.
 * - PII direta: cpf/cnpj/email/telefone → máscara parcial.
 * - PII derivada: nome_completo/endereco/cep/data_nascimento → REDACTED total.
 * - Financeiro do quiz: faturamento_, balanco_, receita_, custo_ (regex) → REDACTED.
 *
 * Aplica também em audit log de aplicação (Log::channel('daily')); audit_logs jurídico
 * (tabela do banco, ADR-003) NÃO usa este sanitizer — PII é preservada por lei.
 */
final class LogSanitizer
{
    /** @var array<string, string> exatos → categoria */
    private const SENSITIVE_KEYS = [
        // credencial → REDACTED
        'password' => 'credential',
        'senha' => 'credential',
        'token' => 'credential',
        'api_key' => 'credential',
        'apikey' => 'credential',
        'authorization' => 'credential',
        'secret' => 'credential',
        'remember_token' => 'credential',

        // PII direta → máscara parcial
        'cpf' => 'cpf',
        'cnpj' => 'cnpj',
        'email' => 'email',
        'telefone' => 'phone',
        'phone' => 'phone',

        // PII derivada → REDACTED
        'nome_completo' => 'pii',
        'endereco' => 'pii',
        'cep' => 'pii',
        'data_nascimento' => 'pii',
    ];

    /** @var list<string> regex → REDACTED */
    private const FINANCIAL_REGEX = [
        '/^faturamento(_.*)?$/',
        '/^balanco(_.*)?$/',
        '/^receita(_.*)?$/',
        '/^custo(_.*)?$/',
    ];

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new class implements ProcessorInterface {
                public function __invoke(LogRecord $record): LogRecord
                {
                    $record = $record->with(
                        context: LogSanitizer::sanitize($record->context),
                        extra: LogSanitizer::sanitize($record->extra)
                    );

                    return $record;
                }
            });
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);
            $category = self::categoryFor($normalized);

            if ($category !== null) {
                $payload[$key] = self::maskByCategory((string) (is_scalar($value) ? $value : ''), $category);

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::sanitize($value);
            }
        }

        return $payload;
    }

    public static function maskByCategory(string $value, string $category): string
    {
        if ($value === '') {
            return '[REDACTED]';
        }

        return match ($category) {
            'credential', 'pii', 'financial' => '[REDACTED]',
            'cpf' => self::maskCpf($value),
            'cnpj' => self::maskCnpj($value),
            'email' => self::maskEmail($value),
            'phone' => self::maskPhone($value),
            default => '[REDACTED]',
        };
    }

    private static function categoryFor(string $key): ?string
    {
        if (isset(self::SENSITIVE_KEYS[$key])) {
            return self::SENSITIVE_KEYS[$key];
        }

        foreach (self::FINANCIAL_REGEX as $regex) {
            if (preg_match($regex, $key) === 1) {
                return 'financial';
            }
        }

        return null;
    }

    private static function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';

        if (strlen($digits) < 11) {
            return '[REDACTED]';
        }

        return '***.***.***-'.substr($digits, -2);
    }

    private static function maskCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D+/', '', $cnpj) ?? '';

        if (strlen($digits) < 14) {
            return '[REDACTED]';
        }

        // RNF: CNPJ pleno proibido em log; expõe só primeiros 8 dígitos (raiz).
        return substr($digits, 0, 8).'/****-**';
    }

    private static function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '[REDACTED]';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localMasked = strlen($local) <= 1 ? '*' : $local[0].str_repeat('*', max(0, strlen($local) - 1));
        $domainParts = explode('.', $domain);
        $domainMasked = str_repeat('*', max(1, strlen($domainParts[0])));
        $tld = count($domainParts) > 1 ? '.'.end($domainParts) : '';

        return $localMasked.'@'.$domainMasked.$tld;
    }

    private static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return '[REDACTED]';
        }

        return '(**) *****-'.substr($digits, -4);
    }
}
