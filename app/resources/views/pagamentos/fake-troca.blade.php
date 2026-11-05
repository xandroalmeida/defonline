<x-layouts.app title="Novo cartão simulado">
    <x-page-header title="Novo cartão simulado" subtitle="Página de teste do provedor fake. Não existe em produção."/>

    <x-card class="flex flex-col gap-4 max-w-[640px]" dusk="pagamento-fake">
        <p class="m-0 text-[color:var(--color-primary)]">Troca de cartão da assinatura{{ $troca->cobra_agora ? ' (cobra a renovação em atraso agora)' : '' }}</p>
        <form method="POST" action="{{ route('pagamentos.fake.troca.simular', $troca) }}" class="flex flex-wrap gap-3 m-0">
            @csrf
            <x-button type="submit" name="acao" value="pagar" variant="primary" dusk="pagamento-fake-pagar">Simular cartão aprovado</x-button>
            <x-button type="submit" name="acao" value="recusar" variant="secondary" dusk="pagamento-fake-recusar">Simular cartão recusado</x-button>
            <x-button type="submit" name="acao" value="expirar" variant="secondary" dusk="pagamento-fake-expirar">Simular desistência (prazo vencido)</x-button>
        </form>
    </x-card>
</x-layouts.app>
