@if ($showPreviewModal && $previewQuestion)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closePreviewModal"></div>
        <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-5 py-3.5 backdrop-blur">
                <h2 class="text-base font-bold text-slate-900">Pratinjau Soal</h2>
                <button type="button" wire:click="closePreviewModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="space-y-4 p-5">
                <div class="prose-exam">{!! html_for_display($previewQuestion->content) !!}</div>
                <div class="space-y-2">
                    @foreach ($previewQuestion->options as $option)
                        <div @class([
                            'flex items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                            'border-emerald-300 bg-emerald-50' => $option->is_correct,
                            'border-slate-200' => ! $option->is_correct,
                        ])>
                            <span class="font-bold">{{ $option->label }}.</span>
                            @if ($option->content_type === \App\Enums\QuestionOptionContentType::Image)
                                <img src="{{ storage_asset($option->image_path) }}" alt="Pilihan {{ $option->label }}" class="max-h-20 rounded">
                            @else
                                <span>{{ $option->content }}</span>
                            @endif
                            @if ($option->is_correct)
                                <span class="ml-auto ui-badge bg-emerald-100 text-emerald-700">Benar</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($previewQuestion->explanation)
                    <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                        <p class="mb-1 font-semibold text-slate-700">Pembahasan</p>
                        {!! $previewQuestion->explanation !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
