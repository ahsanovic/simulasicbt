<div class="ui-card p-4">
    <div class="mb-2 flex items-center justify-between text-sm">
        <span class="font-semibold text-slate-700">Progress Pengerjaan</span>
        <span class="font-bold text-primary-600">{{ $this->progressPercent }}%</span>
    </div>
    <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-gradient-to-r from-primary-500 to-indigo-500 transition-all duration-500 ease-out" style="width: {{ $this->progressPercent }}%"></div>
    </div>
</div>
