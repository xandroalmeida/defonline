{{-- Único jeito de pôr um <select> numa tela: garante a mesma seta e as mesmas margens em todo o sistema. --}}
<select {{ $attributes->merge(['class' => 'input select']) }}>
    {{ $slot }}
</select>
