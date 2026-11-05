<x-layouts.app title="Pagamento simulado">
    <x-page-header title="Pagamento simulado" subtitle="Página de teste do provedor fake. Não existe em produção."/>

    <x-card class="flex flex-col gap-4 max-w-[640px]" dusk="pagamento-fake">
        <p class="m-0 text-[color:var(--color-primary)]">
            {{ $invoice->descricao }} · R$ {{ number_format($invoice->valor_centavos / 100, 2, ',', '.') }}
        </p>
        <form method="POST" action="{{ route('pagamentos.fake.simular', $invoice) }}" class="flex flex-wrap gap-3 m-0">
            @csrf
            <x-button type="submit" name="acao" value="pagar" variant="primary" dusk="pagamento-fake-pagar">Simular pagamento do Pix</x-button>
            <x-button type="submit" name="acao" value="expirar" variant="secondary" dusk="pagamento-fake-expirar">Simular Pix expirado</x-button>
        </form>
    </x-card>
</x-layouts.app>
