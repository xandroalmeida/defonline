<?php

declare(strict_types=1);

use App\Domain\TipoDocumento;
use App\Domain\Uf;
use App\Models\EmpresaAnalisada;
use App\Models\Scopes\BelongsToUsuarioScope;
use App\Models\Usuario;

/**
 * Comportamento do model — relação, scope e helpers.
 */
it('relaciona-se com Usuario via FK usuario_id', function () {
    $usuario = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);

    expect($empresa->usuario)->not->toBeNull();
    expect($empresa->usuario->id)->toBe($usuario->id);
});

it('aplica Global Scope filtrando por auth()->id() (ADR-003)', function () {
    $a = Usuario::factory()->create();
    $b = Usuario::factory()->create();

    EmpresaAnalisada::factory()->count(2)->create(['usuario_id' => $a->id]);
    EmpresaAnalisada::factory()->count(3)->create(['usuario_id' => $b->id]);

    $this->actingAs($a);
    expect(EmpresaAnalisada::query()->count())->toBe(2);

    $this->actingAs($b);
    expect(EmpresaAnalisada::query()->count())->toBe(3);
});

it('bypassa scope com withoutGlobalScope', function () {
    $a = Usuario::factory()->create();
    $b = Usuario::factory()->create();
    EmpresaAnalisada::factory()->count(2)->create(['usuario_id' => $a->id]);
    EmpresaAnalisada::factory()->count(3)->create(['usuario_id' => $b->id]);

    $this->actingAs($a);

    expect(EmpresaAnalisada::withoutGlobalScope(BelongsToUsuarioScope::class)->count())->toBe(5);
});

it('não aplica scope quando não há Usuário autenticado (jobs/comandos)', function () {
    Usuario::factory()->create();
    EmpresaAnalisada::factory()->count(2)->create();
    EmpresaAnalisada::factory()->count(3)->create();

    expect(EmpresaAnalisada::query()->count())->toBe(5);
});

it('expõe documentoFormatado para CNPJ e CPF', function () {
    $usuario = Usuario::factory()->create();

    $cnpj = EmpresaAnalisada::factory()->create([
        'usuario_id' => $usuario->id,
        'tipo_documento' => TipoDocumento::Cnpj,
        'documento' => '11222333000181',
    ]);
    expect($cnpj->documentoFormatado())->toBe('11.222.333/0001-81');

    $cpf = EmpresaAnalisada::factory()->comoCpf()->create([
        'usuario_id' => $usuario->id,
        'documento' => '52998224725',
    ]);
    expect($cpf->documentoFormatado())->toBe('529.982.247-25');
});

it('ufEnum devolve o enum correspondente à coluna uf', function () {
    $empresa = EmpresaAnalisada::factory()->create(['uf' => 'SP']);

    expect($empresa->ufEnum())->toBe(Uf::SP);
});
