<?php

declare(strict_types=1);

use App\Domain\Quiz\ValidaSocios;

/*
| ----------------------------------------------------------------------
| Lista dinâmica de sócios — STORY-038 (+ nome, ajuste pós-devolutiva).
|
| Cada sócio é um objeto {cpf, nome}. Classe pura (hot path do Bloco 4).
| Cobre: descarte de linha vazia (CA-7), CPF inválido por linha (CA-4),
| nome obrigatório, duplicidade por CPF na 2ª ocorrência (CA-5), mínimo 1
| (CA-1), cap 10 (CA-6) e canonicalização alfabética por CPF (CA-9).
|
| CPFs válidos usados: 11144477735 · 12345678909 · 52998224725
| ----------------------------------------------------------------------
*/

/** Atalho para montar um sócio {cpf, nome}. */
function socio(string $cpf = '', string $nome = ''): array
{
    return ['cpf' => $cpf, 'nome' => $nome];
}

// ============================================================================
// canonicalizar() — descarte de vazios + normalização + ordenação (CA-7, CA-9)
// ============================================================================

it('canonicaliza 1 sócio para {cpf só-dígitos, nome aparado}', function () {
    expect(ValidaSocios::canonicalizar([socio('111.444.777-35', '  Ana Lima ')]))
        ->toBe([['cpf' => '11144477735', 'nome' => 'Ana Lima']]);
});

it('descarta linhas totalmente vazias antes de canonicalizar (CA-7)', function () {
    $socios = [socio('', ''), socio('111.444.777-35', 'Ana'), socio('   ', '  '), socio('529.982.247-25', 'Bruno')];

    expect(ValidaSocios::canonicalizar($socios))->toBe([
        ['cpf' => '11144477735', 'nome' => 'Ana'],
        ['cpf' => '52998224725', 'nome' => 'Bruno'],
    ]);
});

it('ordena alfabeticamente pelo CPF, independente da ordem de digitação (CA-9)', function () {
    $esperado = [
        ['cpf' => '11144477735', 'nome' => 'Ana'],
        ['cpf' => '12345678909', 'nome' => 'Bruno'],
        ['cpf' => '52998224725', 'nome' => 'Carla'],
    ];

    expect(ValidaSocios::canonicalizar([socio('529.982.247-25', 'Carla'), socio('111.444.777-35', 'Ana'), socio('123.456.789-09', 'Bruno')]))->toBe($esperado)
        ->and(ValidaSocios::canonicalizar([socio('123.456.789-09', 'Bruno'), socio('529.982.247-25', 'Carla'), socio('111.444.777-35', 'Ana')]))->toBe($esperado);
});

it('canonicaliza lista totalmente vazia para lista vazia', function () {
    expect(ValidaSocios::canonicalizar([socio('', ''), socio('  ', '  ')]))->toBe([]);
});

it('mantém o sócio mesmo quando só o nome ou só o CPF está preenchido (descarte só do 100% vazio)', function () {
    // Ordenado por CPF: a string vazia ("") ordena antes dos dígitos.
    expect(ValidaSocios::canonicalizar([socio('', 'Sem Cpf'), socio('111.444.777-35', '')]))->toBe([
        ['cpf' => '', 'nome' => 'Sem Cpf'],
        ['cpf' => '11144477735', 'nome' => ''],
    ]);
});

// ============================================================================
// erros() — validação por linha (CA-1, CA-4, CA-5, CA-6) + nome obrigatório
// ============================================================================

it('não retorna erro para 1 sócio válido com nome (CA-1)', function () {
    expect(ValidaSocios::erros([socio('111.444.777-35', 'Ana Lima')]))->toBe([]);
});

it('não retorna erro para vários sócios válidos distintos', function () {
    $socios = [socio('111.444.777-35', 'Ana'), socio('123.456.789-09', 'Bruno'), socio('529.982.247-25', 'Carla')];

    expect(ValidaSocios::erros($socios))->toBe([]);
});

it('exige ao menos um sócio quando todas as linhas estão vazias (CA-1 inverso)', function () {
    expect(ValidaSocios::erros([socio('', ''), socio('  ', '  ')]))
        ->toBe(['socios.0.cpf' => 'Informe o CPF de ao menos um sócio.']);
});

it('aponta CPF inválido apenas na própria linha (CA-4)', function () {
    $socios = [socio('111.444.777-35', 'Ana'), socio('111.111.111-11', 'Bruno'), socio('529.982.247-25', 'Carla')];

    expect(ValidaSocios::erros($socios))->toBe([
        'socios.1.cpf' => 'CPF inválido — confira os dígitos.',
    ]);
});

it('exige o CPF quando a linha tem nome mas não tem CPF', function () {
    expect(ValidaSocios::erros([socio('', 'Ana Sem Cpf')]))->toBe([
        'socios.0.cpf' => 'Informe o CPF do sócio.',
    ]);
});

it('exige o nome quando a linha tem CPF mas não tem nome', function () {
    expect(ValidaSocios::erros([socio('111.444.777-35', '')]))->toBe([
        'socios.0.nome' => 'Informe o nome do sócio.',
    ]);
});

it('pode acusar CPF e nome ausentes na mesma linha quando há outra linha válida', function () {
    // 2ª linha só com espaços nos dois campos seria descartada; aqui ela tem CPF inválido + nome vazio.
    $socios = [socio('111.444.777-35', 'Ana'), socio('111.111.111-11', '')];

    expect(ValidaSocios::erros($socios))->toBe([
        'socios.1.cpf' => 'CPF inválido — confira os dígitos.',
        'socios.1.nome' => 'Informe o nome do sócio.',
    ]);
});

it('marca duplicidade de CPF na 2ª ocorrência em diante (CA-5)', function () {
    $socios = [socio('111.444.777-35', 'Ana'), socio('529.982.247-25', 'Bruno'), socio('111.444.777-35', 'Ana de novo')];

    expect(ValidaSocios::erros($socios))->toBe([
        'socios.2.cpf' => 'CPF duplicado na lista — cada sócio só pode aparecer uma vez.',
    ]);
});

it('detecta duplicidade mesmo com máscaras diferentes do mesmo CPF (CA-5)', function () {
    $socios = [socio('111.444.777-35', 'Ana'), socio('11144477735', 'Outro nome')];

    expect(ValidaSocios::erros($socios))->toBe([
        'socios.1.cpf' => 'CPF duplicado na lista — cada sócio só pode aparecer uma vez.',
    ]);
});

it('linhas vazias entre preenchidas não geram erro nem viram mínimo (CA-7)', function () {
    $socios = [socio('111.444.777-35', 'Ana'), socio('', ''), socio('529.982.247-25', 'Bruno')];

    expect(ValidaSocios::erros($socios))->toBe([]);
});

it('aplica o cap de 10 sócios preenchidos na última linha (CA-6 defensivo)', function () {
    $cpf = function (string $base9): string {
        $dv = function (string $d, int $t): int {
            $soma = 0;
            $peso = $t + 1;
            for ($i = 0; $i < $t; $i++) {
                $soma += ((int) $d[$i]) * $peso;
                $peso--;
            }
            $resto = $soma % 11;

            return $resto < 2 ? 0 : 11 - $resto;
        };
        $d1 = $dv($base9, 9);

        return $base9.$d1.$dv($base9.$d1, 10);
    };

    $validos = array_map(
        fn (int $n): array => socio($cpf(str_pad((string) (100000000 + $n), 9, '0', STR_PAD_LEFT)), 'Sócio '.$n),
        range(1, 11),
    );

    $erros = ValidaSocios::erros($validos);

    expect($erros)->toHaveKey('socios.10.cpf')
        ->and($erros['socios.10.cpf'])->toBe('Limite de 10 sócios por empresa.');
});

it('expõe os limites como constantes públicas', function () {
    expect(ValidaSocios::MINIMO)->toBe(1)
        ->and(ValidaSocios::MAXIMO)->toBe(10);
});
