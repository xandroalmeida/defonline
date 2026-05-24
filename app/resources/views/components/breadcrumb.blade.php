@props([
    'items' => [],
])

@if (count($items) > 0)
    <nav aria-label="breadcrumb" class="mb-4 text-sm" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-2 m-0 p-0 list-none">
            @foreach ($items as $index => $item)
                @php $isLast = $index === count($items) - 1; @endphp
                <li class="flex items-center gap-2">
                    @if ($isLast)
                        <span aria-current="page"
                              class="text-[color:var(--color-primary)] font-medium">
                            {{ $item['label'] }}
                        </span>
                    @else
                        <a href="{{ $item['url'] }}"
                           class="text-[color:var(--color-secondary)] hover:text-[color:var(--color-tertiary)] no-underline hover:underline">
                            {{ $item['label'] }}
                        </a>
                        <span aria-hidden="true" class="text-[color:var(--color-secondary)]">›</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
