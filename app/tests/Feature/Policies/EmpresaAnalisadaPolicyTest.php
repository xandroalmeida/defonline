<?php

declare(strict_types=1);

use App\Models\EmpresaAnalisada;
use App\Models\Usuario;
use App\Policies\EmpresaAnalisadaPolicy;

it('libera view/update/delete quando o Usuário é dono da empresa', function () {
    $usuario = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $usuario->id]);

    $policy = new EmpresaAnalisadaPolicy;

    expect($policy->view($usuario, $empresa))->toBeTrue();
    expect($policy->update($usuario, $empresa))->toBeTrue();
    expect($policy->delete($usuario, $empresa))->toBeTrue();
});

it('nega view/update/delete quando o Usuário não é dono (multi-tenancy)', function () {
    $dono = Usuario::factory()->create();
    $outro = Usuario::factory()->create();
    $empresa = EmpresaAnalisada::factory()->create(['usuario_id' => $dono->id]);

    $policy = new EmpresaAnalisadaPolicy;

    expect($policy->view($outro, $empresa))->toBeFalse();
    expect($policy->update($outro, $empresa))->toBeFalse();
    expect($policy->delete($outro, $empresa))->toBeFalse();
});
