{{--
    Campo percentual com sufixo "%".
    Vars: $id. Enunciado, auxiliar e ajuda vêm de TextosQuiz (STORY-106).
--}}
@php
    // STORY-106 — enunciado, texto auxiliar e ajuda vêm dos textos do quiz (editáveis no backoffice).
    $label = \App\Support\TextosQuiz::rotulo($id);
    $help = \App\Support\TextosQuiz::auxiliar($id);
@endphp
<div>
    <div class="flex items-center gap-1.5 mb-1">
        <x-label :for="$id" class="mb-0">{{ $label }}</x-label>
        <x-help :id="$id" :text="\App\Support\TextosQuiz::ajuda($id)" :label="$label"/>
    </div>
    <x-input-percentual :id="$id" :name="$id" wire:model.live.debounce.300ms="{{ $id }}" dusk="quiz-{{ $id }}"/>
    @if (! empty($help))
        <p class="text-xs text-[color:var(--color-secondary)] mt-1 mb-0">{{ $help }}</p>
    @endif
    @error($id)
        <p class="text-[color:var(--color-destructive)] text-sm mt-1 mb-0" dusk="quiz-erro-{{ $id }}">{{ $message }}</p>
    @enderror
</div>
