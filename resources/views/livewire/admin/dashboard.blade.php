<div>
    <x-ui.page-header title="Dashboard" description="Ringkasan aktivitas dan statistik simulasi ujian.">
        <span class="ui-badge bg-primary-100 text-primary-700">{{ now()->translatedFormat('l, d F Y') }}</span>
    </x-ui.page-header>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card
            label="Total Pengguna"
            :value="number_format($stats['users'])"
            color="primary"
            trend="Admin & peserta terdaftar"
            icon="users"
        />
        <x-ui.stat-card
            label="Bank Soal"
            :value="number_format($stats['questions'])"
            color="emerald"
            trend="TWK, TIU, dan TKP"
            icon="questions"
        />
        <x-ui.stat-card
            label="Paket Ujian"
            :value="number_format($stats['exams'])"
            color="violet"
            trend="Ujian yang dibuat"
            icon="exams"
        />
        <x-ui.stat-card
            label="Ujian Selesai"
            :value="number_format($stats['attempts'])"
            color="amber"
            trend="Total attempt submitted"
            icon="results"
        />
    </div>

    <div class="mt-8">
        @island(name: 'active-exams')
            <div wire:poll.10s.visible>
                @include('livewire.admin.dashboard.active-exams')
            </div>
        @endisland
    </div>

    <div class="mt-8 grid gap-5 lg:grid-cols-2">
        <div class="ui-card p-6">
            <h2 class="text-base font-bold text-slate-900">Selamat datang kembali</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                Kelola bank soal, jadwalkan ujian, dan pantau hasil peserta dari panel admin ini.
                Gunakan menu sidebar untuk navigasi cepat antar modul.
            </p>
        </div>
        <div class="ui-card overflow-hidden p-0">
            <div class="border-b border-slate-100 bg-gradient-to-r from-primary-600 to-indigo-600 px-6 py-5">
                <h2 class="text-base font-bold text-white">Akses Cepat</h2>
                <p class="mt-1 text-sm text-primary-100">Modul yang sering digunakan</p>
            </div>
            <div class="grid grid-cols-2 gap-px bg-slate-100">
                @foreach([
                    ['admin.questions.index', 'Tambah Soal'],
                    ['admin.exams.index', 'Buat Ujian'],
                    ['admin.users.index', 'Kelola Peserta'],
                    ['admin.results.index', 'Lihat Hasil'],
                ] as [$route, $label])
                    <a href="{{ route($route) }}" wire:navigate class="bg-white px-5 py-4 text-sm font-semibold text-slate-700 transition hover:bg-primary-50 hover:text-primary-700">
                        {{ $label }} →
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
