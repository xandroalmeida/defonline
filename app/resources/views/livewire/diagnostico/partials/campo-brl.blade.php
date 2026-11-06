{{--
    Campo monetário (R$). Wrapper flex `.input-affix` evita desalinhamento de
    prefix absoluto sobre input com min-height de touch-target.
    Vars: $id (Q08, Q09, ...), $label, $help.
--}}
<div>
    <div class="flex items-center gap-1.5 mb-1">
        <x-label :for="$id" class="mb-0">{{ $label }}</x-label>
        <x-help :id="$id" :text="config('quiz.help-industria.campos.'.$id)" :label="$label"/>
    </div>
    <x-input-moeda :id="$id" :name="$id" wire:model.live.debounce.300ms="{{ $id }}" dusk="quiz-{{ $id }}"/>
    @if (! empty($help))
        <p class="text-xs text-[color:var(--color-secondary)] mt-1 mb-0">{{ $help }}</p>
    @endif
    @error($id)
        <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="quiz-erro-{{ $id }}">{{ $message }}</p>
    @enderror
</div>
