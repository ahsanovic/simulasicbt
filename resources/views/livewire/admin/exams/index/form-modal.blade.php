@if ($showModal)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
        <div class="relative max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
                <h2 class="text-lg font-bold text-slate-900">{{ $editingId ? 'Edit Ujian' : 'Buat Ujian Baru' }}</h2>
                <button type="button" wire:click="closeModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form wire:submit="save" class="space-y-4 p-6">
                <div>
                    <label class="ui-label">Judul Ujian</label>
                    <input type="text" wire:model="title" class="ui-input">
                    @error('title') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="ui-label">Deskripsi</label>
                    <textarea wire:model="description" rows="2" class="ui-input"></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="ui-label">Durasi (menit)</label>
                        <input type="number" wire:model="duration_minutes" min="1" class="ui-input">
                    </div>
                    <div>
                        <label class="ui-label">Mulai</label>
                        <input type="datetime-local" wire:model="starts_at" class="ui-input">
                    </div>
                    <div>
                        <label class="ui-label">Selesai</label>
                        <input type="datetime-local" wire:model="ends_at" class="ui-input">
                    </div>
                </div>
                <div>
                    <label class="ui-label">Status</label>
                    <select wire:model="status" class="ui-select">
                        <option value="draft">Draft</option>
                        <option value="published">Dipublikasikan</option>
                        <option value="archived">Diarsipkan</option>
                    </select>
                </div>

                <div class="rounded-xl border border-primary-200 bg-primary-50/50 p-4">
                    <h3 class="text-sm font-bold text-slate-900">Komposisi Soal (otomatis &amp; acak)</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Total <strong>110 soal</strong> diambil secara acak dari bank soal aktif:
                        TWK <strong>30</strong>, TIU <strong>35</strong>, TKP <strong>45</strong>.
                    </p>
                    <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                        @foreach (['twk' => 'TWK', 'tiu' => 'TIU', 'tkp' => 'TKP'] as $code => $label)
                            @php $stats = $questionAvailability[$code]; @endphp
                            <li @class([
                                'rounded-lg border px-3 py-2',
                                'border-emerald-200 bg-emerald-50 text-emerald-800' => $stats['available'] >= $stats['required'],
                                'border-rose-200 bg-rose-50 text-rose-800' => $stats['available'] < $stats['required'],
                            ])>
                                <span class="font-semibold">{{ $label }}</span>
                                <span class="block text-xs">{{ $stats['available'] }} / {{ $stats['required'] }} tersedia</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <label class="ui-label">Tingkat Kesulitan Soal</label>
                    <select wire:model.live="difficulty" class="ui-select" @disabled($editingExamHasAttempts)>
                        <option value="all">Semua tingkat</option>
                        <option value="easy">Mudah</option>
                        <option value="medium">Sedang</option>
                        <option value="hard">Sulit</option>
                    </select>
                    @error('difficulty') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    @if ($editingExamHasAttempts)
                        <p class="mt-1.5 text-xs text-amber-700">Ujian sudah dimulai peserta — komposisi soal tidak dapat diubah.</p>
                    @else
                        <p class="mt-1.5 text-xs text-slate-500">Soal dipilih acak sesuai filter ini setiap kali ujian disimpan.</p>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" wire:click="closeModal" class="ui-btn-secondary">Batal</button>
                    <button type="submit" class="ui-btn-primary">Simpan Ujian</button>
                </div>
            </form>
        </div>
    </div>
@endif
