@php
    /*
     * Versão deployada do app. Formato (ux-specs §4):
     *   - production:   v0.4.2
     *   - homol/local:  v0.4.2 · homol  (middle-dot U+00B7)
     *   - testing:      v0.4.2          (sem sufixo de ambiente)
     */
    $version = config('app.version') ?? 'v?';
    $env = config('app.env');
    $showEnv = $env !== 'production' && $env !== 'testing';
    $envLabel = match ($env) {
        'local' => 'local',
        'staging', 'homolog', 'homologation' => 'homol',
        default => $env,
    };
@endphp

<span {{ $attributes->merge(['class' => 'text-[color:var(--color-secondary)] text-sm font-normal']) }}
      data-testid="footer-version"
      dusk="footer-version">
    {{ $version }}@if ($showEnv) · {{ $envLabel }}@endif
</span>
