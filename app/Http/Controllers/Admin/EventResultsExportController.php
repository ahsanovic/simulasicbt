<?php

namespace App\Http\Controllers\Admin;

use App\Exports\EventResultsExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use App\Services\EventResultsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventResultsExportController extends Controller
{
    /**
     * ?jenis=skd|skb (required, must be a type this event runs)
     * ?sesi={id}      (optional — present = partial export of that session,
     *                  absent = full export across every session)
     */
    public function __invoke(Request $request, Event $event, EventResultsService $results): BinaryFileResponse
    {
        $type = (string) $request->query('jenis');
        abort_unless(in_array($type, $results->availableTypes($event), true), 404);

        $session = null;

        if ($request->filled('sesi')) {
            $session = EventSession::query()
                ->where('event_id', $event->id)
                ->findOrFail((int) $request->query('sesi'));
        }

        $filename = 'hasil-'.$type.'-'.Str::slug($event->name.($session ? '-'.$session->name : '-semua-sesi'))
            .'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new EventResultsExport($event, $type, $session), $filename);
    }
}
