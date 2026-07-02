@if ($showPreviewModal && $previewQuestion)
    <div wire:key="question-preview-{{ $previewQuestion->id }}" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closePreviewModal"></div>
        <div class="relative w-full max-w-4xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-5 py-3.5 backdrop-blur">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Pratinjau Soal</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $previewQuestion->material->display_name }}</p>
                </div>
                <button type="button" wire:click="closePreviewModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="space-y-6 p-5">
                <div class="flex flex-wrap items-center gap-2">
                    @php $code = $previewQuestion->subject->code->value; @endphp
                    <span @class([
                        'ui-badge',
                        'bg-blue-100 text-blue-700' => $code === 'twk',
                        'bg-amber-100 text-amber-700' => $code === 'tiu',
                        'bg-violet-100 text-violet-700' => $code === 'tkp',
                    ])>{{ $previewQuestion->subject->code->label() }}</span>
                    <span @class([
                        'ui-badge',
                        'bg-emerald-100 text-emerald-700' => $previewQuestion->is_active,
                        'bg-slate-100 text-slate-600' => ! $previewQuestion->is_active,
                    ])>{{ $previewQuestion->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    <span class="ui-badge bg-slate-100 text-slate-600">
                        {{ match($previewQuestion->difficulty) {
                            'easy' => 'Mudah',
                            'hard' => 'Sulit',
                            default => 'Sedang',
                        } }}
                    </span>
                </div>

                <div class="prose-exam text-base text-slate-800">
                    {!! $previewQuestion->content !!}
                </div>

                <div class="space-y-2.5">
                    <h3 class="text-sm font-semibold text-slate-800">Pilihan Jawaban</h3>
                    @php $isTkp = $previewQuestion->usesWeightedScoring(); @endphp
                    @foreach ($previewQuestion->options->sortBy('sort_order') as $option)
                        <div @class([
                            'flex items-start gap-3 rounded-xl border p-3.5',
                            'border-emerald-300 bg-emerald-50/40' => ! $isTkp && $option->is_correct,
                            'border-slate-200 bg-white' => $isTkp || ! $option->is_correct,
                        ])>
                            <span @class([
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                                'bg-emerald-600 text-white' => ! $isTkp && $option->is_correct,
                                'bg-slate-100 text-slate-600' => $isTkp || ! $option->is_correct,
                            ])>{{ $option->label }}</span>
                            <div class="min-w-0 flex-1 pt-0.5 text-sm leading-relaxed text-slate-800">
                                @if ($option->isImage())
                                    <img src="{{ $option->imageUrl() }}" alt="Pilihan {{ $option->label }}" class="max-h-48 max-w-full rounded-lg object-contain">
                                @else
                                    {!! $option->content !!}
                                @endif
                            </div>
                            @if ($isTkp)
                                <span class="ui-badge shrink-0 bg-violet-50 text-violet-700">Bobot {{ $option->score_weight }}</span>
                            @elseif ($option->is_correct)
                                <span class="shrink-0 text-xs font-medium text-emerald-700">Benar</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($previewQuestion->explanation)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="mb-2 text-sm font-semibold text-slate-800">Pembahasan</h3>
                        <div class="prose-exam text-sm text-slate-700">{!! $previewQuestion->explanation !!}</div>
                    </div>
                @endif
            </div>

            <div class="sticky bottom-0 flex justify-end border-t border-slate-100 bg-white/95 px-5 py-3 backdrop-blur">
                <button type="button" wire:click="closePreviewModal" class="ui-btn-secondary">Tutup</button>
            </div>
        </div>
    </div>
@endif
