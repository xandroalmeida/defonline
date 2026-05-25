{{--
    Campo CPF.
    Vars: $id, $label, $mask (JS).
--}}
<div>
    <x-label :for="$id">{{ $label }}</x-label>
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
