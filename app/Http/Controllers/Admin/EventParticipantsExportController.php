<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EventParticipantsExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventParticipantsExportController extends Controller
{
    public function event(Event $event): BinaryFileResponse
    {
        $filename = 'peserta-'.Str::slug($event->name).'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new EventParticipantsExport($event), $filename);
    }

    public function session(Event $event, EventSession $session): BinaryFileResponse
    {
        abort_unless($session->event_id === $event->id, 404);

        $filename = 'peserta-'.Str::slug($event->name.'-'.$session->name).'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new EventParticipantsExport($event, $session), $filename);
    }
}
