@use('App\Support\SystemHealth')

@php
    $labels = [
        SystemHealth::PASS => 'LULUS',
        SystemHealth::WARN => 'PERINGATAN',
        SystemHealth::FAIL => 'GAGAL',
        SystemHealth::PENDING => 'MENUNGGU',
    ];
    $overall = [
        SystemHealth::PASS => 'SIAP — semua pemeriksaan lulus',
        SystemHealth::WARN => 'SIAP DENGAN PERINGATAN',
        SystemHealth::FAIL => 'TIDAK SIAP — ada pemeriksaan gagal',
    ];
    $metrics = $log->metrics ?? [];
    $dash = fn ($value, string $suffix = '') => $value === null ? '—' : $value.$suffix;
    $metricRows = [
        'Peserta sedang ujian' => $dash($metrics['skd_in_progress'] ?? null).' SKD · '.$dash($metrics['skb_in_progress'] ?? null).' SKB',
        'Memori Redis' => $dash($metrics['redis_used_mb'] ?? null, ' MB').' dari '.$dash($metrics['redis_max_mb'] ?? null, ' MB').' · '.$dash($metrics['redis_clients'] ?? null).' koneksi',
        'Koneksi MySQL' => $dash($metrics['db_connections'] ?? null).' dari batas '.$dash($metrics['db_max_connections'] ?? null),
        'OPcache' => 'hit '.$dash($metrics['opcache_hit_rate'] ?? null, '%').' · memori '.$dash($metrics['opcache_memory_percent'] ?? null, '%'),
        'Sisa disk' => $dash($metrics['disk_free_gb'] ?? null, ' GB'),
        'Job antrean gagal' => $dash($metrics['failed_jobs'] ?? null),
    ];
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Kesehatan Sistem #{{ $log->id }}</title>
    <style>
        :root { --text: #1e293b; --muted: #64748b; --line: #e2e8f0; --pass: #047857; --warn: #b45309; --fail: #be123c; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; background: #fff; color: var(--text); font: 14px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 820px; margin: 0 auto; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        h2 { margin: 28px 0 8px; font-size: 15px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
        .meta { color: var(--muted); margin: 0 0 20px; }
        .verdict { padding: 12px 16px; border: 2px solid; border-radius: 10px; font-weight: 700; }
        .verdict.pass { color: var(--pass); } .verdict.warn { color: var(--warn); } .verdict.fail { color: var(--fail); }
        .verdict span { font-weight: 400; color: var(--text); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 7px 8px; border-bottom: 1px solid var(--line); }
        th { width: 34%; font-weight: 600; }
        .status { width: 110px; font-weight: 700; white-space: nowrap; }
        .status.pass { color: var(--pass); } .status.warn { color: var(--warn); } .status.fail { color: var(--fail); }
        .hint { color: var(--muted); font-size: 13px; overflow-wrap: anywhere; }
        .info td { color: var(--muted); }
        .actions { margin-bottom: 24px; }
        .actions button { padding: 8px 16px; border: 0; border-radius: 8px; background: #4f46e5; color: #fff; font-weight: 600; cursor: pointer; }
        .sign { margin-top: 48px; display: flex; justify-content: flex-end; }
        .sign div { width: 240px; text-align: center; }
        .sign .space { height: 64px; }
        @media print { body { padding: 0; } .actions { display: none; } h2 { break-after: avoid; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
<main>
    <div class="actions"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>

    <h1>Laporan Kesehatan Sistem</h1>
    <p class="meta">
        {{ config('app.name') }} · Pemeriksaan #{{ $log->id }} ·
        {{ $log->created_at->locale('id')->translatedFormat('l, d F Y H:i:s') }} ·
        dijalankan oleh {{ $log->user?->name ?? 'sistem' }}
    </p>

    <div class="verdict {{ $log->status }}">
        {{ $overall[$log->status] ?? strtoupper($log->status) }}
        <span>— {{ $log->passed }} lulus, {{ $log->warnings }} peringatan, {{ $log->failures }} gagal</span>
    </div>

    <h2>Beban saat pemeriksaan</h2>
    <table>
        @foreach ($metricRows as $label => $value)
            <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    @foreach (collect($log->results)->groupBy('section') as $section => $items)
        <h2>{{ $section }}</h2>
        <table>
            @foreach ($items as $item)
                @if ($item['status'] === SystemHealth::INFO)
                    <tr class="info"><td colspan="2">{{ $item['label'] }}</td></tr>
                @else
                    <tr>
                        <td class="status {{ $item['status'] }}">{{ $labels[$item['status']] ?? $item['status'] }}</td>
                        <td>
                            {{ $item['label'] }}
                            @if ($item['hint'] !== '')
                                <div class="hint">{{ $item['hint'] }}</div>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    @endforeach

    <div class="sign">
        <div>
            <p>Pemeriksa,</p>
            <div class="space"></div>
            <p>{{ $log->user?->name ?? '..............................' }}</p>
        </div>
    </div>
</main>
</body>
</html>
