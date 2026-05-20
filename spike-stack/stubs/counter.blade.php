<div style="font-family: system-ui; padding: 2rem;">
    <h1>DEFOnline — Spike Stack POC</h1>
    <p>Contador Livewire: <strong dusk="count">{{ $count }}</strong></p>
    <button dusk="increment" wire:click="increment" style="padding: .5rem 1rem;">+1</button>

    <hr style="margin: 2rem 0;">

    <p>Banco: PostgreSQL via <code>config('database.default')</code> =
        <strong dusk="db-driver">{{ config('database.default') }}</strong>
    </p>
    <p>Hora do servidor (lida do Postgres):
        <strong dusk="db-now">{{ \Illuminate\Support\Facades\DB::select('select now() as now')[0]->now ?? 'erro' }}</strong>
    </p>
</div>
