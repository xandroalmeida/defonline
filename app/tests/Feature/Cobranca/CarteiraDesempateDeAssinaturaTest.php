<?php

declare(strict_types=1);

use App\Domain\Cobranca\Carteira;
use App\Domain\Cobranca\Origem;
use App\Models\Assinatura;
use App\Models\Plano;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * `Carteira::snapshot` escolhe a assinatura que vale por `data_inicio` e `created_at`; trial encerrado e plano
 * pago criados no mesmo instante empatam nos dois. O desempate tem que ser determinístico, porque decide de
 * qual cota o consumo sai: a paga vale sobre o trial, e a não encerrada sobre a encerrada, seja qual for o id.
 */
it('empate de início e criação: a assinatura paga vale sobre o trial encerrado, com qualquer ordem de id', function (string $idDoTrial, string $idDaPaga) {
    $usuario = Usuario::factory()->create();
    $plano = Plano::query()->where('cota_mensal', 10)->firstOrFail();
    $carteira = app(Carteira::class);

    $trial = $carteira->concederCota($usuario->id, 1, now(), now()->addDays(7), null, null, true);
    $pago = $carteira->concederCota($usuario->id, $plano->cota_mensal, now(), now()->addMonth(), $plano->id, 'mensal');

    $iguais = ['data_inicio' => now()->format('Y-m-d H:i:sP'), 'created_at' => now()->format('Y-m-d H:i:sP')];
    DB::table('assinaturas')->where('id', $trial->id)->update([...$iguais, 'id' => $idDoTrial]);
    DB::table('assinaturas')->where('id', $pago->id)->update([...$iguais, 'id' => $idDaPaga]);

    for ($i = 0; $i < 5; $i++) {
        $decisao = $carteira->verificar($usuario->id);
        expect($decisao->origem)->toBe(Origem::Cota)->and($decisao->assinaturaId)->toBe($idDaPaga);
    }

    expect(Assinatura::query()->where('usuario_id', $usuario->id)->count())->toBe(2);
})->with([
    'trial com id maior' => ['ffffffff-ffff-7fff-bfff-ffffffffffff', '00000000-0000-7000-8000-000000000000'],
    'trial com id menor' => ['00000000-0000-7000-8000-000000000000', 'ffffffff-ffff-7fff-bfff-ffffffffffff'],
]);
