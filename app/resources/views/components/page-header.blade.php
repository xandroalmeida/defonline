@props([
    'title' => null,
    'subtitle' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-col gap-2 mb-6 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="flex flex-col gap-1">
        @if ($title)
            <h1 class="text-[length:var(--text-h1)] font-medium leading-tight tracking-tight text-[color:var(--color-primary)]">
                {{ $title }}
            </h1>
        @endif
        @if ($subtitle)
            <p class="text-[color:var(--color-secondary)] text-sm m-0">
                {{ $subtitle }}
            </p>
        @endif
    </div>
    @isset($actions)
        <div class="flex gap-3 flex-shrink-0">
            {{ $actions }}
        </div>
    @endisset
</header>
