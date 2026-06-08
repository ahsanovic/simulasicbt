<div>
    @include('livewire.admin.questions.index.header')
    @include('livewire.admin.questions.index.table')
    @include('livewire.admin.questions.index.form-modal')
    @include('livewire.admin.questions.index.import-modal')
    <x-ui.import-error-modal />
</div>

@push('scripts')
    @vite(['resources/js/quill-editor.js'])
@endpush
