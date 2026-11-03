<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Tabela CNAE 2.3 — subclasses (7 dígitos) do IBGE/CONCLA.
 *
 * Fonte versionada em `resources/data/cnae-subclasses.json`, gerada pelo
 * comando `cnae:atualizar` a partir da API de dados do IBGE. Usada para
 * validar o CNAE informado no cadastro manual (autônomo) e alimentar o
 * widget de busca (código ou descrição).
 */
final class Cnae
{
    /** @var array<string, string>|null código (7 dígitos) => descrição */
    private static ?array $tabela = null;

    public static function arquivo(): string
    {
        return dirname(__DIR__, 2).'/resources/data/cnae-subclasses.json';
    }

    /**
     * @return array<string, string> código (7 dígitos) => descrição, ordenado por código
     */
    public static function todas(): array
    {
        if (self::$tabela === null) {
            /** @var list<array{codigo: string, descricao: string}> $linhas */
            $linhas = json_decode((string) file_get_contents(self::arquivo()), true, flags: JSON_THROW_ON_ERROR);
            $tabela = [];
            foreach ($linhas as $linha) {
                $tabela[$linha['codigo']] = $linha['descricao'];
            }
            self::$tabela = $tabela;
        }

        return self::$tabela;
    }

    public static function normalizar(string $cnae): string
    {
        return preg_replace('/\D+/', '', $cnae) ?? '';
    }

    public static function existe(string $cnae): bool
    {
        return isset(self::todas()[self::normalizar($cnae)]);
    }

    public static function descricao(string $cnae): ?string
    {
        return self::todas()[self::normalizar($cnae)] ?? null;
    }

    /** Formato oficial `0000-0/00`; devolve a entrada intacta se não tiver 7 dígitos. */
    public static function formatar(string $cnae): string
    {
        $d = self::normalizar($cnae);

        if (strlen($d) !== 7) {
            return $cnae;
        }

        return substr($d, 0, 4).'-'.$d[4].'/'.substr($d, 5, 2);
    }

    /** Descrição no padrão da tela: só a primeira letra maiúscula (IBGE manda tudo em caixa alta). */
    public static function descricaoLegivel(string $descricao): string
    {
        $s = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $descricao)));

        return mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }
}
