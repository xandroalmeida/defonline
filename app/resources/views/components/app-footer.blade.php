@props([
    'variant' => 'app',
])

<footer data-testid="app-footer"
        class="border-t border-[color:var(--color-border)] bg-[color:var(--color-surface)] px-4 sm:px-6 py-5">
    <div class="max-w-[960px] mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-[color:var(--color-secondary)]">
        @if ($variant === 'app')
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <a href="{{ route('termos.termo-adesao') }}" class="link"
                   dusk="app-footer-termo">Termo de Adesão</a>
                <a href="{{ route('termos.politica-privacidade') }}" class="link"
                   dusk="app-footer-privacidade">Política de Privacidade</a>
                <span>© DEFOnline {{ now()->year }}</span>
            </div>
        @else
            <span>© DEFOnline {{ now()->year }}</span>
        @endif

        <x-footer-version/>
    </div>
</footer>
