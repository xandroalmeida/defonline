{{--
    Campo monetário (R$). Wrapper flex `.input-affix` evita desalinhamento de
    prefix absoluto sobre input com min-height de touch-target.
    Vars: $id (Q08, Q09, ...), $label, $help, $mask (JS).
--}}
<div>
    <div class="flex items-center gap-1.5 mb-1">
        <x-label :for="$id" class="mb-0">{{ $label }}</x-label>
        <x-help :id="$id" :text="config('quiz.help-industria.campos.'.$id)" :label="$label"/>
    </div>
    <label class="input-affix" for="{{ $id }}">
        <span class="input-affix__symbol" aria-hidden="true">R$</span>
        <input type="text" id="{{ $id }}" name="{{ $id }}"
               class="input-affix__input"
               wire:model.live.debounce.300ms="{{ $id }}"
               inputmode="decimal" autocomplete="off"
               placeholder="0,00"
               x-data
               x-on:input="{!! $mask !!}"
               dusk="quiz-{{ $id }}">
    </label>
    @if (! empty($help))
        <p class="text-xs text-[color:var(--color-secondary)] mt-1 mb-0">{{ $help }}</p>
    @endif
    @error($id)
        <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="quiz-erro-{{ $id }}">{{ $message }}</p>
    @enderror
</div>
