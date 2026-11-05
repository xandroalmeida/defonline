<?php

declare(strict_types=1);

namespace App\Support\Drm;

use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Percentual;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * O snapshot da demonstração (`drm_lancamentos.demonstracao`, ADR-007) pronto para a tela.
 *
 * **Lê, não calcula.** Nada aqui soma, subtrai ou recalcula: converte o texto do snapshot em
 * texto de tela e decide a apresentação (destaques, cards por trecho, linhas omitidas).
 *
 * **Render genérico (CA-8b, ADR-007).** Não existe `L1..L11` no código: a ordem, o nível, o
 * `tipo` e o `destaque` vêm de cada linha do snapshot, e o que a tela acrescenta (nome do
 * trecho, prefixo, rótulo do destaque) é configuração em `config/drm/tela.php`. Estrutura que
 * a tela não conhece cai num padrão — campo ausente, tipo novo, código novo, modo ausente —
 * e nunca derruba o render, para que um bump MAJOR do motor não quebre os meses antigos.
 */
final class DemonstracaoVisao
{
    private const NOMINAL = '/^-?\d+(\.\d+)?$/';

    private const PERCENTUAL = '/^-?\d+\.\d{2,}$/';

    private const SEM_FATURAMENTO = 'indisponivel:sem_faturamento';

    private const GRUPO_AGREGADO = 'indisponivel:grupo_agregado';

    /**
     * @param  list<LinhaVisao>  $linhas
     * @param  list<array{rotulo: string, linha: LinhaVisao}>  $destaques
     * @param  list<array{titulo: string, linhas: list<LinhaVisao>}>  $trechos
     */
    private function __construct(
        public readonly string $posicao,
        public readonly array $linhas,
        public readonly array $destaques,
        public readonly array $trechos,
        public readonly ?string $semFaturamento,
        public readonly bool $prejuizo,
        public readonly ?string $motorVersion,
        public readonly ?CarbonImmutable $geradoEm,
    ) {}

    /**
     * @param  array<string, mixed>  $demonstracao  o snapshot, como gravado
     * @param  string  $posicaoPedida  `fora` ou `dentro`; se o snapshot não tem, usa a que ele tiver
     */
    public static function de(array $demonstracao, string $posicaoPedida): self
    {
        $porPosicao = is_array($demonstracao['linhas'] ?? null) ? $demonstracao['linhas'] : [];
        $posicao = array_key_exists($posicaoPedida, $porPosicao) ? $posicaoPedida : (string) (array_key_first($porPosicao) ?? $posicaoPedida);
        $brutas = is_array($porPosicao[$posicao] ?? null) ? array_values(array_filter($porPosicao[$posicao], 'is_array')) : [];

        $indiceFinal = self::indiceDoResultadoFinal($brutas);
        $linhas = [];
        $prejuizo = false;
        $semFaturamento = null;
        $brutasVisiveis = [];
        foreach ($brutas as $i => $bruta) {
            if (self::omitida($bruta)) {
                continue;
            }
            $linha = self::linha($bruta, $i === $indiceFinal);
            $linhas[] = $linha;
            $brutasVisiveis[] = $bruta;
            $prejuizo = $prejuizo || $linha->prejuizo;
            if (($bruta['motivo'] ?? null) === self::SEM_FATURAMENTO) {
                $semFaturamento ??= self::texto($bruta['mensagem'] ?? null) ?? (string) config('drm.rotulos.indisponibilidade.'.self::SEM_FATURAMENTO);
            }
        }

        return new self(
            posicao: $posicao,
            linhas: $linhas,
            destaques: self::destaques($brutasVisiveis, $linhas),
            trechos: self::trechos($linhas, $posicao),
            semFaturamento: $semFaturamento,
            prejuizo: $prejuizo,
            motorVersion: self::texto($demonstracao['motor_drm_version'] ?? null),
            geradoEm: self::data($demonstracao['gerado_em'] ?? null),
        );
    }

    /**
     * Ressalvas que o usuário aceitou no lançamento (CA-7), em texto de leitura.
     *
     * @param  array<int, mixed>  $alertasAceitos  `alertas_aceitos` da linha vigente
     * @return list<string>
     */
    public static function ressalvas(array $alertasAceitos): array
    {
        $textos = [];
        $vistas = [];
        foreach ($alertasAceitos as $aceito) {
            $regra = is_array($aceito) ? ($aceito['regra'] ?? null) : $aceito;
            if (! is_string($regra) || $regra === '') {
                continue;
            }
            $valor = is_array($aceito) && is_string($aceito['valor_envolvido'] ?? null) && preg_match(self::NOMINAL, $aceito['valor_envolvido']) === 1
                ? Dinheiro::brl(abs(Dinheiro::deDecimal($aceito['valor_envolvido'])))
                : null;

            $texto = self::texto(config("drm.tela.ressalvas.{$regra}"));
            $dependeDoValor = $texto !== null && str_contains($texto, ':valor');

            // A4 dispara uma vez por campo, mas a frase não depende do valor: aparece uma vez só.
            if ($texto !== null && ! $dependeDoValor) {
                if (isset($vistas[$regra])) {
                    continue;
                }
                $vistas[$regra] = true;
            }
            $textos[] = $texto === null || ($valor === null && $dependeDoValor)
                ? (string) config('drm.tela.ressalva_generica')
                : strtr($texto, [':valor' => (string) $valor]);
        }

        return $textos;
    }

    // ------------------------------------------------------------------ linhas

    /**
     * Item de grupo agregado: nunca foi informado, não há o que mostrar (CA-2).
     *
     * @param  array<string, mixed>  $bruta
     */
    private static function omitida(array $bruta): bool
    {
        return (int) ($bruta['nivel'] ?? 1) >= 2
            && ($bruta['motivo_nominal'] ?? null) === self::GRUPO_AGREGADO
            && ! self::nominalValido($bruta['nominal'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $bruta
     */
    private static function linha(array $bruta, bool $final): LinhaVisao
    {
        $codigo = self::texto($bruta['codigo'] ?? null) ?? '';
        $tipo = self::texto($bruta['tipo'] ?? null) ?? 'item';
        $nominal = self::nominalValido($bruta['nominal'] ?? null) ? Dinheiro::deDecimal((string) $bruta['nominal']) : null;
        $percentual = is_string($bruta['percentual'] ?? null) && preg_match(self::PERCENTUAL, $bruta['percentual']) === 1
            ? Percentual::formatar($bruta['percentual'])
            : null;

        return new LinhaVisao(
            codigo: $codigo,
            nivel: max(1, (int) ($bruta['nivel'] ?? 1)),
            tipo: $tipo,
            rotulo: self::texto($bruta['rotulo'] ?? null) ?? $codigo,
            prefixo: (string) (config("drm.tela.prefixos.codigos.{$codigo}") ?? config("drm.tela.prefixos.tipo.{$tipo}") ?? ''),
            nominal: $nominal === null ? null : self::moeda($nominal, true),
            nominalSemMoeda: $nominal === null ? null : self::moeda($nominal, false),
            percentual: $percentual === null ? null : self::sinal($percentual).'%',
            nominalAusente: $nominal === null ? self::ausente('valor_indisponivel', $bruta['motivo_nominal'] ?? null, $bruta['mensagem_nominal'] ?? null) : null,
            percentualAusente: $percentual === null ? self::ausente('percentual_indisponivel', $bruta['motivo'] ?? null, $bruta['mensagem'] ?? null) : null,
            negativo: $nominal !== null && $nominal < 0,
            final: $final,
            prejuizo: $final && $nominal !== null && $nominal < 0,
        );
    }

    private static function nominalValido(mixed $nominal): bool
    {
        return is_string($nominal) && preg_match(self::NOMINAL, $nominal) === 1;
    }

    /** `−R$ 3.240,00`: sinal de menos de verdade (U+2212), nunca cor (DDR-004 item 6). */
    private static function moeda(int $centavos, bool $comMoeda): string
    {
        return Formato::moeda($centavos, $comMoeda);
    }

    private static function sinal(string $percentualFormatado): string
    {
        return Formato::sinal($percentualFormatado);
    }

    private static function ausente(string $modelo, mixed $motivo, mixed $mensagem): string
    {
        $curto = is_string($motivo) ? self::texto(config('drm.tela.motivos_curtos.'.$motivo)) : null;
        $porque = $curto ?? self::texto($mensagem) ?? 'motivo não informado';

        return strtr((string) config("drm.tela.{$modelo}"), [':motivo' => $porque]);
    }

    // ------------------------------------------------------------------ destaques e trechos

    /**
     * A última linha de nível 1 do array é o resultado final nos dois modos (DDR-004 item 3).
     *
     * @param  list<array<string, mixed>>  $brutas
     */
    private static function indiceDoResultadoFinal(array $brutas): ?int
    {
        $indice = null;
        foreach ($brutas as $i => $bruta) {
            if ((int) ($bruta['nivel'] ?? 1) === 1) {
                $indice = $i;
            }
        }

        return $indice;
    }

    /**
     * Os três números antes da conta. Vêm do `destaque` do próprio snapshot; sem nenhuma marca
     * (snapshot de outra geração), vale a regra declarativa do DDR-004 item 3.
     *
     * @param  list<array<string, mixed>>  $brutas
     * @param  list<LinhaVisao>  $linhas
     * @return list<array{rotulo: string, linha: LinhaVisao}>
     */
    private static function destaques(array $brutas, array $linhas): array
    {
        $escolhidas = [];
        foreach ($brutas as $i => $bruta) {
            if (($bruta['destaque'] ?? false) === true) {
                $escolhidas[] = $i;
            }
        }

        if ($escolhidas === []) {
            $escolhidas = self::destaquesPorRegra($brutas, $linhas);
        }

        return array_map(
            static fn (int $i): array => ['rotulo' => self::rotuloDoDestaque($linhas[$i]), 'linha' => $linhas[$i]],
            $escolhidas,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $brutas
     * @param  list<LinhaVisao>  $linhas
     * @return list<int>
     */
    private static function destaquesPorRegra(array $brutas, array $linhas): array
    {
        $ancora = null;
        $segundo = null;
        foreach ($brutas as $i => $bruta) {
            $ancora ??= ($bruta['percentual'] ?? null) === '100.0000' ? $i : null;
            if ($linhas[$i]->codigo === 'L9' || ($segundo === null && $linhas[$i]->codigo === 'L7')) {
                $segundo = $i;
            }
        }
        $final = null;
        foreach ($linhas as $i => $linha) {
            $final = $linha->final ? $i : $final;
        }

        return array_values(array_unique(array_filter([$ancora, $segundo, $final], static fn (?int $i): bool => $i !== null)));
    }

    private static function rotuloDoDestaque(LinhaVisao $linha): string
    {
        if ($linha->final) {
            return (string) config('drm.tela.resultado_final.'.($linha->prejuizo ? 'prejuizo' : 'lucro'));
        }

        return self::texto(config("drm.tela.destaques.{$linha->codigo}")) ?? $linha->rotulo;
    }

    /**
     * Um card fecha em cada totalizador (DDR-004 item 4); o que sobra depois do último vira um card à parte.
     *
     * @param  list<LinhaVisao>  $linhas
     * @return list<array{titulo: string, linhas: list<LinhaVisao>}>
     */
    private static function trechos(array $linhas, string $posicao): array
    {
        $trechos = [];
        $corrente = [];
        foreach ($linhas as $linha) {
            $corrente[] = $linha;
            if ($linha->ehTotal()) {
                $titulo = self::texto(config("drm.tela.trechos.{$posicao}.{$linha->codigo}"))
                    ?? self::texto(config('drm.tela.trechos.'.$linha->codigo))
                    ?? $linha->rotulo;
                $trechos[] = ['titulo' => $titulo, 'linhas' => $corrente];
                $corrente = [];
            }
        }
        if ($corrente !== []) {
            $trechos[] = ['titulo' => (string) config('drm.tela.trecho_restante'), 'linhas' => $corrente];
        }

        return $trechos;
    }

    // ------------------------------------------------------------------ utilidades

    private static function texto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    private static function data(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($valor);
        } catch (Throwable) {
            return null;
        }
    }
}
