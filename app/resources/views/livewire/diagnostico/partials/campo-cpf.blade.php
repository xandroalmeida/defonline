{{--
    Campo CPF.
    Vars: $id, $label, $mask (JS).
--}}
<div>
    <div class="flex items-center gap-1.5 mb-1">
        <x-label :for="$id" class="mb-0">{{ $label }}</x-label>
        <x-help :id="$id" :text="config('quiz.help-industria.campos.'.$id)" :label="$label"/>
    </div>
    <x-input type="text" :id="$id" :name="$id"
             wire:model.live.debounce.300ms="{{ $id }}"
             inputmode="numeric" autocomplete="off"
             maxlength="14"
             placeholder="000.000.000-00"
             x-data
             x-on:input="{!! $mask !!}"
             :dusk="'quiz-' . $id"/>
    @error($id)
        <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="quiz-erro-{{ $id }}">{{ $message }}</p>
    @enderror
</div>
