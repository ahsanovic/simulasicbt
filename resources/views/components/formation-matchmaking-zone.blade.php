@props(['analysis'])

@php
    $zone = $analysis['zone'] ?? null;
    $zoneConfig = match ($zone) {
        'safe' => [
            'label' => 'Zona Aman',
            'emoji' => '🟢',
            'border' => 'border-emerald-200',
            'bg' => 'bg-emerald-50/70',
            'title' => 'text-emerald-900',
            'body' => 'text-emerald-800',
        ],
        'caution' => [
            'label' => 'Zona Waspada',
            'emoji' => '🟡',
            'border' => 'border-amber-200',
            'bg' => 'bg-amber-50/70',
            'title' => 'text-amber-900',
            'body' => 'text-amber-800',
        ],
        default => [
            'label' => 'Zona Risiko',
            'emoji' => '🔴',
            'border' => 'border-rose-200',
            'bg' => 'bg-rose-50/70',
            'title' => 'text-rose-900',
            'body' => 'text-rose-800',
        ],
    };
@endphp

<div class="ui-card overflow-hidden {{ $zoneConfig['border'] }} {{ $zoneConfig['bg'] }}">
<div class="border-b border-inherit px-6 py-4">
        <div class="flex items-center gap-2">
            <span class="text-xl" aria-hidden="true">{{ $zoneConfig['emoji'] }}</span>
            <h3 class="text-lg font-bold {{ $zoneConfig['title'] }}">{{ $zoneConfig['label'] }}</h3>
        </div>
    </div>

    <div class="space-y-4 px-6 py-5">
        <p class="text-sm leading-relaxed {{ $zoneConfig['body'] }}">{{ $analysis['message'] }}</p>

        @if ($analysis['rank'])
            <p class="text-sm font-semibold {{ $zoneConfig['title'] }}">
                Peringkat Anda: #{{ $analysis['rank'] }} dari {{ $analysis['applicant_count'] }} pelamar
                @if ($analysis['percentile'])
                    <span class="font-normal">(persentil {{ $analysis['percentile'] }}%)</span>
                @endif
            </p>
        @endif

        @if ($zone === 'caution' && $analysis['improvement'])
            <p class="text-sm {{ $zoneConfig['body'] }}">
                Disarankan mendongkrak {{ $analysis['improvement']['subject'] }} +{{ $analysis['improvement']['points'] }} poin
                @if ($analysis['alternative'])
                    atau mempertimbangkan jabatan {{ $analysis['alternative']['name'] }} di rumpun {{ $analysis['alternative']['group'] }} yang kompetisinya lebih longgar.
                @else
                    atau mempertimbangkan jabatan lain di rumpun {{ $analysis['formation']->group }}.
                @endif
            </p>
        @endif

        @if ($zone === 'risk')
            <p class="text-sm {{ $zoneConfig['body'] }}">
                Fokuskan latihan pada komponen skor yang masih di bawah Passing Grade, lalu ulangi simulasi untuk memantau progres.
            </p>
        @endif
    </div>
</div>
