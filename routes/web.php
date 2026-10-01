<?php

use App\Exports\EventParticipantsImportTemplate;
use App\Exports\ParticipantsExport;
use App\Exports\ParticipantsImportTemplate;
use App\Exports\QuestionsImportTemplateExport;
use App\Exports\SkbQuestionsImportTemplate;
use App\Http\Controllers\Admin\EventParticipantImportController;
use App\Http\Controllers\Admin\EventParticipantsExportController;
use App\Http\Controllers\Admin\EventResultsExportController;
use App\Http\Controllers\Admin\ExamResultsExportController;
use App\Http\Controllers\Admin\ParticipantImportController;
use App\Http\Controllers\Admin\QuestionContentImageController;
use App\Http\Controllers\Admin\QuestionImportController;
use App\Http\Controllers\Admin\SkbQuestionImportController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Peserta\CertificateController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Middleware\TrackPesertaPresence;
use App\Livewire\Admin\CoinPurchases\Index as CoinPurchasesIndex;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Events\Index as EventsIndex;
use App\Livewire\Admin\Events\LiveScore as EventLiveScore;
use App\Livewire\Admin\Events\Participants as EventParticipants;
use App\Livewire\Admin\Events\Results as EventResults;
use App\Livewire\Admin\Events\Sessions as EventSessions;
use App\Livewire\Admin\Exams\Index as ExamsIndex;
use App\Livewire\Admin\Formations\Index as FormationsIndex;
use App\Livewire\Admin\JabatanSkb\Index as JabatanSkbIndex;
use App\Livewire\Admin\JabatanSkb\SoalIndex as JabatanSkbSoalIndex;
use App\Livewire\Admin\OnlineParticipants\Index as OnlineParticipantsIndex;
use App\Livewire\Admin\Questions\Generate as QuestionsGenerate;
use App\Livewire\Admin\Questions\Index as QuestionsIndex;
use App\Livewire\Admin\Reports\Index as ReportsIndex;
use App\Livewire\Admin\Results\Index as ResultsIndex;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Admin\Testimonials\Index as TestimonialsIndex;
use App\Livewire\Admin\Users\ExamHistory as UserExamHistory;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Peserta\AudioMode;
use App\Livewire\Peserta\Dashboard as PesertaDashboard;
use App\Livewire\Peserta\DrillSetup;
use App\Livewire\Peserta\DuelLobby;
use App\Livewire\Peserta\DuelRoom;
use App\Livewire\Peserta\Evaluasi as PesertaEvaluasi;
use App\Livewire\Peserta\Events\Index as PesertaEventsIndex;
use App\Livewire\Peserta\ExamHistory;
use App\Livewire\Peserta\ExamReview;
use App\Livewire\Peserta\ExamRoom;
use App\Livewire\Peserta\KartuSakti;
use App\Livewire\Peserta\LeaderboardHub;
use App\Livewire\Peserta\MateriBelajar;
use App\Livewire\Peserta\MateriBelajarShow;
use App\Livewire\Peserta\ModeUjian\Dashboard as ModeUjianDashboard;
use App\Livewire\Peserta\ModeUjian\SkbExamRoom;
use App\Livewire\Peserta\ModeUjian\SkbResult;
use App\Livewire\Peserta\ModeUjian\SkdResult;
use App\Livewire\Peserta\RencanaBelajar;
use App\Livewire\Peserta\Shop;
use App\Livewire\Peserta\SimulasiFormasi;
use App\Livewire\Peserta\SimulasiIndex;
use App\Livewire\Peserta\Statistik;
use App\Livewire\Peserta\Testimonials;
use App\Livewire\Public\LiveScoreIndex as PublicLiveScoreIndex;
use App\Livewire\Public\LiveScoreShow as PublicLiveScoreShow;
use App\Models\Instansi;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

Route::prefix(config('app.base_path'))->group(function () {
Route::get('storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('storage.public');

// Public livescore — accessible without login (for venue display screens).
Route::get('livescore', PublicLiveScoreIndex::class)->name('public.livescore.index');
Route::get('livescore/{event:public_code}', PublicLiveScoreShow::class)->name('public.livescore.show');

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('login', Login::class)->name('login');
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::post('logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/users', UsersIndex::class)->name('users.index');
    Route::get('/users/{user}/riwayat', UserExamHistory::class)->name('users.exam-history');
    Route::post('/users/import', [ParticipantImportController::class, 'store'])->name('users.import');
    Route::get('/users/import-template', function () {
        return Excel::download(
            new ParticipantsImportTemplate,
            'template-import-peserta.xlsx',
        );
    })->name('users.import-template');
    Route::get('/questions', QuestionsIndex::class)->name('questions.index');
    Route::get('/questions/generate', QuestionsGenerate::class)->name('questions.generate');
    Route::post('/questions/upload-image', [QuestionContentImageController::class, 'store'])->name('questions.upload-image');
    Route::post('/questions/import', [QuestionImportController::class, 'store'])->name('questions.import');
    Route::get('/questions/import-template', function () {
        return Excel::download(
            new QuestionsImportTemplateExport,
            'template-import-soal.xlsx',
        );
    })->name('questions.import-template');
    Route::get('/exams', ExamsIndex::class)->name('exams.index');
    Route::get('/formations', FormationsIndex::class)->name('formations.index');
    Route::get('/jabatan-skb', JabatanSkbIndex::class)->name('jabatan-skb.index');
    Route::get('/jabatan-skb/{jabatanSkb}/soal', JabatanSkbSoalIndex::class)->name('jabatan-skb.soal.index');
    Route::post('/jabatan-skb/{jabatanSkb}/soal/import', [SkbQuestionImportController::class, 'store'])->name('jabatan-skb.soal.import');
    Route::get('/jabatan-skb/{jabatanSkb}/soal/import-template', function () {
        return Excel::download(new SkbQuestionsImportTemplate, 'template-import-soal-skb.xlsx');
    })->name('jabatan-skb.soal.import-template');
    Route::get('/events', EventsIndex::class)->name('events.index');
    Route::get('/events/{event}/sessions', EventSessions::class)->name('events.sessions');
    Route::get('/events/{event}/sessions/{session}/livescore', EventLiveScore::class)->name('events.sessions.livescore');
    Route::get('/events/{event}/hasil', EventResults::class)->name('events.results');
    Route::get('/events/{event}/hasil/export', EventResultsExportController::class)->name('events.results.export');
    Route::get('/events/{event}/export', [EventParticipantsExportController::class, 'event'])->name('events.export');
    Route::get('/events/{event}/sessions/{session}/export', [EventParticipantsExportController::class, 'session'])->name('events.sessions.export');
    Route::get('/events/{event}/peserta', EventParticipants::class)->name('events.participants');
    Route::post('/events/{event}/peserta/import', [EventParticipantImportController::class, 'store'])->name('events.participants.import');
    Route::get('/events/{event}/peserta/import-template', function (\App\Models\Event $event) {
        $sessionNames = $event->sessions()->orderBy('name')->pluck('name')->all();

        return Excel::download(new EventParticipantsImportTemplate($sessionNames), 'template-import-peserta-event.xlsx');
    })->name('events.participants.import-template');
    Route::get('/peserta-ujian', OnlineParticipantsIndex::class)->name('online-participants.index');
    Route::get('/results', ResultsIndex::class)->name('results.index');
    Route::get('/results/exports/{exportRequest}/download', [ExamResultsExportController::class, 'download'])
        ->name('results.exports.download');
    Route::get('/pembelian-koin', CoinPurchasesIndex::class)->name('coin-purchases.index');
    Route::get('/testimoni', TestimonialsIndex::class)->name('testimonials.index');
    Route::get('/reports', ReportsIndex::class)->name('reports.index');
    Route::get('/reports/export-participants', function () {
        $instansiId = request()->integer('instansi') ?: null;

        if ($instansiId && ! Instansi::query()->whereKey($instansiId)->exists()) {
            abort(404);
        }

        $filename = $instansiId
            ? 'peserta-'.Str::slug(Instansi::query()->find($instansiId)?->nama ?? 'instansi').'-'.now()->format('Y-m-d').'.xlsx'
            : 'peserta-semua-instansi-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ParticipantsExport($instansiId), $filename);
    })->name('reports.export-participants');
    Route::get('/settings', SettingsIndex::class)->name('settings.index');
});

Route::middleware(['auth', 'peserta', TrackPesertaPresence::class])->prefix('peserta')->name('peserta.')->group(function () {
    Route::get('/', PesertaDashboard::class)->name('dashboard');
    Route::get('/statistik', Statistik::class)->name('statistik.index');
    Route::get('/simulasi', SimulasiIndex::class)->name('simulasi.index');
    Route::get('/drill', DrillSetup::class)->name('drill.index');
    Route::get('/riwayat', ExamHistory::class)->name('history');
    Route::get('/evaluasi', PesertaEvaluasi::class)->name('evaluasi');
    Route::get('/simulasi-formasi', SimulasiFormasi::class)->name('simulasi-formasi');
    Route::get('/rapor', fn () => redirect()->route('peserta.evaluasi'));
    Route::get('/riwayat/{attempt}/review', ExamReview::class)->name('exam.review');
    Route::get('/riwayat/{attempt}/sertifikat', [CertificateController::class, 'download'])->name('certificate.download');
    Route::get('/ujian/{exam}', ExamRoom::class)->name('exam.room');
    Route::get('/event', PesertaEventsIndex::class)->name('events.index');
    Route::get('/duel', DuelLobby::class)->name('duel.index');
    Route::get('/duel/{session}', DuelRoom::class)->name('duel.room');
    Route::get('/peringkat', LeaderboardHub::class)->name('leaderboard.index');
    Route::get('/testimoni', Testimonials::class)->name('testimonials.index');
    Route::get('/materi', MateriBelajar::class)->name('materi.index');
    Route::get('/materi/{subjectCode}/{materialSlug}', MateriBelajarShow::class)->name('materi.show');
    Route::get('/audio', AudioMode::class)->name('audio.index');
    Route::get('/rencana-belajar', RencanaBelajar::class)->name('rencana-belajar.index');
    Route::get('/kartu-sakti', KartuSakti::class)->name('kartu-sakti.index');
    Route::get('/toko', Shop::class)->name('shop.index');
    Route::get('/mode-ujian', ModeUjianDashboard::class)->name('mode-ujian.dashboard');
    Route::get('/mode-ujian/skd/{attempt}/hasil', SkdResult::class)->name('mode-ujian.skd-result');
    Route::get('/mode-ujian/skb', SkbExamRoom::class)->name('mode-ujian.skb.room');
    Route::get('/mode-ujian/skb/{attempt}/hasil', SkbResult::class)->name('mode-ujian.skb-result');
});
});
