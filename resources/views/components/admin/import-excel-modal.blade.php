@props([
    'show' => false,
    'title',
    'description',
    'formAction',
    'templateRoute',
    'templateLabel' => 'Unduh Template Excel',
    'maxSize' => '10 MB',
])

@if ($show)
    <div
        x-data="{
            fileName: null,
            isDragging: false,
            isSubmitting: false,
            setFile(file) {
                this.fileName = file?.name ?? null;
            },
            clearFile() {
                this.fileName = null;
                this.$refs.fileInput.value = '';
            },
        }"
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showImportModal', false)"></div>

        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-900/20">
            <div class="border-b border-slate-100 bg-gradient-to-r from-emerald-50 to-primary-50 px-6 py-5">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">{{ $title }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
                    </div>
                </div>
            </div>

            <form
                action="{{ $formAction }}"
                method="POST"
                enctype="multipart/form-data"
                class="space-y-5 p-6"
                @submit="isSubmitting = true"
            >
                @csrf

                <a href="{{ $templateRoute }}" class="ui-btn-secondary w-full justify-center">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    {{ $templateLabel }}
                </a>

                @if (trim($slot) !== '')
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600">
                        {{ $slot }}
                    </div>
                @endif

                <div>
                    <input
                        x-ref="fileInput"
                        type="file"
                        name="file"
                        accept=".xlsx,.xls,.csv"
                        required
                        class="sr-only"
                        @change="setFile($event.target.files[0])"
                    >

                    <div
                        class="relative cursor-pointer rounded-2xl border-2 border-dashed px-6 py-8 text-center transition"
                        :class="isDragging ? 'border-primary-400 bg-primary-50/60' : 'border-slate-200 bg-slate-50 hover:border-primary-300 hover:bg-primary-50/40'"
                        @click="$refs.fileInput.click()"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="isDragging = false; if ($event.dataTransfer.files.length) { $refs.fileInput.files = $event.dataTransfer.files; setFile($event.dataTransfer.files[0]); }"
                    >
                        <template x-if="!fileName">
                            <div>
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-slate-700">Klik atau seret file ke sini</p>
                                <p class="mt-1 text-xs text-slate-500">Format .xlsx, .xls, atau .csv · Maks. {{ $maxSize }}</p>
                            </div>
                        </template>

                        <template x-if="fileName">
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 text-left shadow-sm ring-1 ring-slate-200">
                                <div class="min-w-0 flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800" x-text="fileName"></p>
                                        <p class="text-xs text-emerald-600">File siap diimpor</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                                    @click.stop="clearFile()"
                                    aria-label="Hapus file"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="$set('showImportModal', false)" class="ui-btn-secondary" :disabled="isSubmitting">
                        Batal
                    </button>
                    <button type="submit" class="ui-btn-primary min-w-[8rem]" :disabled="isSubmitting">
                        <span x-show="!isSubmitting">Import</span>
                        <span x-show="isSubmitting" x-cloak class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Memproses...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
