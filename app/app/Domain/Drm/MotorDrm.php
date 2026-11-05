<?php

declare(strict_types=1);

namespace App\Domain\Drm;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use LogicException;

/**
 * Motor do DRM (STORY-048): transforma um lançamento mensal na demonstração, com valor
 * nominal e percentual sobre a receita em cada linha, nos dois modos de pró-labore.
 *
 * Referência aritmética: `design/estrutura-drm.md` v1.1 §1. Divergiu, é bug.
 *
 * **Os dois modos saem prontos.** `demonstracao.linhas.fora[]` e `.dentro[]` derivam dos
 * mesmos campos; alternar o modo na tela é escolher o outro array (ADR-007). O Lucro
 * Líquido do Mês (L11) é idêntico nos dois (§1.3) e há teste dedicado para isso.
 *
 * **Auto-descritivo.** Cada linha carrega código, nível, rótulo já resolvido, `tipo`,
 * `destaque`, nominal e percentual — quem lê o snapshot daqui a anos não precisa da
 * configuração de hoje para renderizá-lo (ADR-007, PDR-008 §1).
 *
 * **Pureza (IDR-010, `estrutura-drm` §7).** Sem banco, relógio, sessão ou usuário:
 * competência e carimbo de tempo entram por parâmetro, e a mesma entrada produz sempre a
 * mesma saída. A única coisa lida de fora é config (versão do motor e rótulos), como faz
 * o motor do Diagnóstico. **Aritmética exata**: centavos inteiros; percentual por divisão
 * inteira ({@see Percentual}); nominais serializados como string de duas casas.
 *
 * **Versão própria.** Estampa `config('drm.motor_drm_version')`, contador independente do
 * do Diagnóstico e do contador do pacote de conteúdo. Não há `matrix_version`: o DRM não usa a matriz.
 */
final class MotorDrm
{
    public const SEM_FATURAMENTO = 'indisponivel:sem_faturamento';

    public const GRUPO_AGREGADO = 'indisponivel:grupo_agregado';

    /**
     * Estrutura de cada modo, na ordem do contrato: [código, nível, tipo, destaque].
     * `tipo`: `total` (linha "= …"), `deducao` (nível 1 "(–)"/"(±)"), `subtotal` (nível 2)
     * ou `item` (a âncora L1 e o nível 3). Um card de trecho fecha em cada `total`.
     */
    private const ESTRUTURA = [
        'fora' => [
            ['L1', 1, 'item', true],
            ['L2', 1, 'deducao', false],
            ['L3', 1, 'total', false],
            ['L4', 1, 'deducao', false],
            ['L5', 1, 'total', false],
            ['L6', 1, 'deducao', false],
            ['L6a', 2, 'subtotal', false],
            ['L6b', 2, 'subtotal', false],
            ['L7', 1, 'total', false],
            ['L7b', 1, 'deducao', false],
            ['L8', 1, 'deducao', false],
            ['L8a', 2, 'subtotal', false],
            ['L8b', 2, 'subtotal', false],
            ['L9', 1, 'total', true],
            ['L10', 1, 'deducao', false],
            ['L11', 1, 'total', true],
        ],
        'dentro' => [
            ['L1', 1, 'item', true],
            ['L2', 1, 'deducao', false],
            ['L3', 1, 'total', false],
            ['L4', 1, 'deducao', false],
            ['L5', 1, 'total', false],
            ['L6', 1, 'deducao', false],
            ['L6a', 2, 'subtotal', false],
            ['L6b', 2, 'subtotal', false],
            ['L6c', 3, 'item', false],
            ['L7', 1, 'total', true],
            ['L7b', 1, 'deducao', false],
            ['L8', 1, 'deducao', false],
            ['L8a', 2, 'subtotal', false],
            ['L8b', 2, 'subtotal', false],
            ['L11', 1, 'total', true],
        ],
    ];

    public function __construct(private readonly ConteudoDrm $conteudo = new ConteudoDrm) {}

    /**
     * @param  array<string, mixed>  $payload  `lancamento_payload` canônico: D01..D42 como decimal
     *                                         de duas casas (ou nulo) e `modos` por grupo.
     * @return array{
     *     motor_drm_version: string,
     *     competencia: string,
     *     setor: string,
     *     gerado_em: string,
     *     modos: array<string, string>,
     *     linhas: array{fora: list<array<string, mixed>>, dentro: list<array<string, mixed>>}
     * }
     *
     * @throws InvalidArgumentException payload fora do contrato (campo malformado ou negativo, modos ausentes)
     * @throws LogicException rótulo de linha ausente no pacote de conteúdo
     */
    public function calcular(array $payload, Setor $setor, Competencia $competencia, DateTimeInterface $geradoEm): array
    {
        $modos = $this->modos($payload);

        $linhas = [];
        foreach (array_keys(self::ESTRUTURA) as $modo) {
            $linhas[$modo] = $this->linhas($modo, $payload, $setor, $modos);
        }

        return [
            'motor_drm_version' => (string) config('drm.motor_drm_version'),
            'competencia' => $competencia->texto(),
            'setor' => $setor->value,
            // Sempre em UTC: o mesmo instante tem um só texto, qualquer que seja o fuso de quem chama
            // (relido do banco, o carimbo volta em outro fuso e o snapshot tem de ser reproduzível).
            'gerado_em' => DateTimeImmutable::createFromInterface($geradoEm)->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'modos' => array_map(static fn (Modo $m): string => $m->value, $modos),
            'linhas' => $linhas,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, Modo>  $modos
     * @return list<array<string, mixed>>
     */
    private function linhas(string $modo, array $payload, Setor $setor, array $modos): array
    {
        $valores = $this->valores($modo, $payload, $setor, $modos);
        $receita = $this->centavos($payload, 'D01');
        $dentro = $modo === 'dentro';
        $detalhadoAdm = $modos[Grupo::Administrativas->value] === Modo::Detalhado;

        $linhas = [];
        foreach (self::ESTRUTURA[$modo] as [$codigo, $nivel, $tipo, $destaque]) {
            // Sem linha zerada na tela: L7b some quando D41 e D42 são ambos zero (não quando se anulam).
            if ($codigo === 'L7b' && $this->centavos($payload, 'D41') === 0 && $this->centavos($payload, 'D42') === 0) {
                continue;
            }
            // Nível 3 só existe com o grupo detalhado (`estrutura-drm` §1.2).
            if ($codigo === 'L6c' && ! ($dentro && $detalhadoAdm)) {
                continue;
            }

            $linhas[] = $this->linha($codigo, $nivel, $tipo, $destaque, $valores[$codigo], $receita, $modo, $setor);
        }

        return $linhas;
    }

    /**
     * Valores em centavos de todas as linhas do modo. `null` = item de grupo agregado,
     * que o usuário não informou (e que nunca se infere do total).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, Modo>  $modos
     * @return array<string, int|null>
     */
    private function valores(string $modo, array $payload, Setor $setor, array $modos): array
    {
        $v = fn (string $campo): int => $this->centavos($payload, $campo);
        $total = function (Grupo $grupo) use ($v, $setor, $modos): int {
            if ($modos[$grupo->value] === Modo::Agregado) {
                return $v($grupo->totalizador());
            }

            return array_sum(array_map($v, $grupo->itens($setor)));
        };
        $itemFinanceiro = fn (string $campo): ?int => $modos[Grupo::Financeiras->value] === Modo::Detalhado ? $v($campo) : null;

        $l1 = $v('D01');
        $l3 = $l1 - $v('D02');
        $l5 = $l3 - $total(Grupo::Custo);
        $l6a = $total(Grupo::Vendas);
        $prolabore = $v('D40');
        $dentro = $modo === 'dentro';
        $l6b = $total(Grupo::Administrativas) + ($dentro ? $prolabore : 0);
        $l6 = $l6a + $l6b;
        $l7 = $l5 - $l6;
        $l7b = $v('D41') - $v('D42');
        $l8 = $total(Grupo::Financeiras);
        $antesDoProlabore = $l7 + $l7b - $l8;

        $valores = [
            'L1' => $l1,
            'L2' => $v('D02'),
            'L3' => $l3,
            'L4' => $total(Grupo::Custo),
            'L5' => $l5,
            'L6' => $l6,
            'L6a' => $l6a,
            'L6b' => $l6b,
            'L6c' => $prolabore,
            'L7' => $l7,
            'L7b' => $l7b,
            'L8' => $l8,
            'L8a' => $itemFinanceiro('D38'),
            'L8b' => $itemFinanceiro('D39'),
        ];

        if ($dentro) {
            // O pró-labore já foi deduzido em L6b: L9 e L10 colapsam no Lucro Líquido.
            $valores['L11'] = $antesDoProlabore;
        } else {
            $valores['L9'] = $antesDoProlabore;
            $valores['L10'] = $prolabore;
            $valores['L11'] = $antesDoProlabore - $prolabore;
        }

        return $valores;
    }

    /**
     * @return array<string, mixed>
     */
    private function linha(string $codigo, int $nivel, string $tipo, bool $destaque, ?int $nominal, int $receita, string $modo, Setor $setor): array
    {
        $rotulo = $this->conteudo->rotuloLinha($codigo, $modo, $setor->value);
        if ($rotulo === null) {
            throw new LogicException("Rótulo da linha {$codigo} ({$modo}, {$setor->value}) ausente no pacote de conteúdo.");
        }

        $motivoNominal = $nominal === null ? self::GRUPO_AGREGADO : null;
        $percentual = $nominal === null ? null : Percentual::deCentavos($nominal, $receita);
        $motivo = match (true) {
            $receita === 0 => self::SEM_FATURAMENTO,
            $nominal === null => self::GRUPO_AGREGADO,
            default => null,
        };

        return [
            'codigo' => $codigo,
            'nivel' => $nivel,
            'rotulo' => $rotulo,
            'tipo' => $tipo,
            'destaque' => $destaque,
            'nominal' => $nominal === null ? null : Dinheiro::decimal($nominal),
            'percentual' => $percentual,
            'motivo' => $motivo,
            'mensagem' => $motivo === null ? null : $this->mensagem($motivo),
            'motivo_nominal' => $motivoNominal,
            'mensagem_nominal' => $motivoNominal === null ? null : $this->mensagem($motivoNominal),
        ];
    }

    private function mensagem(string $motivo): string
    {
        return $this->conteudo->indisponibilidade($motivo)
            ?? throw new LogicException("Mensagem de {$motivo} ausente no pacote de conteúdo.");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, Modo>
     */
    private function modos(array $payload): array
    {
        $informados = $payload['modos'] ?? null;
        if (! is_array($informados)) {
            throw new InvalidArgumentException('Payload sem `modos`.');
        }

        $modos = [];
        foreach (Grupo::cases() as $grupo) {
            $modo = is_string($informados[$grupo->value] ?? null) ? Modo::tryFrom($informados[$grupo->value]) : null;
            if ($modo === null) {
                throw new InvalidArgumentException("Modo do grupo {$grupo->value} ausente ou inválido.");
            }
            $modos[$grupo->value] = $modo;
        }

        return $modos;
    }

    /**
     * Centavos de um campo. Ausente ou nulo (campo que o setor ou o modo não coleta) vale
     * zero na soma, sem alterar a estrutura das linhas; texto fora de `\d+.\d\d` é erro.
     *
     * @param  array<string, mixed>  $payload
     */
    private function centavos(array $payload, string $campo): int
    {
        $valor = $payload[$campo] ?? null;
        if ($valor === null) {
            return 0;
        }

        if (! is_string($valor) || preg_match('/^\d+\.\d{2}$/', $valor) !== 1) {
            throw new InvalidArgumentException("Campo {$campo} fora do contrato (esperado decimal não negativo de duas casas).");
        }

        return Dinheiro::deDecimal($valor);
    }
}
