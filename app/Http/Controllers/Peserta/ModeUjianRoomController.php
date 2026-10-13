<?php

namespace App\Http\Controllers\Peserta;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModeUjianAnswersRequest;
use App\Services\ModeUjianRoom\AnswerBatchWriter;
use App\Services\ModeUjianRoom\ClientRoomFinisher;
use App\Services\ModeUjianRoom\DeviceReplaced;
use App\Services\ModeUjianRoom\RoomClosed;
use App\Services\ModeUjianRoom\RoomKinds;
use Illuminate\Http\JsonResponse;

/**
 * The browser exam room's two server calls: answers saved in the background
 * on every question change, and "Selesai Ujian" / time up. Plain JSON, no
 * Livewire render, so each call is small and quick.
 */
class ModeUjianRoomController extends Controller
{
    public function answers(ModeUjianAnswersRequest $request, AnswerBatchWriter $writer): JsonResponse
    {
        try {
            $result = $writer->write(
                RoomKinds::get($request->validated('kind')),
                (int) $request->user()->id,
                (int) $request->validated('attempt_id'),
                $request->validated('device_token'),
                $request->answers(),
            );
        } catch (RoomClosed) {
            return response()->json(['error' => 'closed'], 409);
        } catch (DeviceReplaced) {
            return response()->json(['error' => 'device'], 409);
        }

        return response()->json($result);
    }

    public function finish(ModeUjianAnswersRequest $request, ClientRoomFinisher $finisher): JsonResponse
    {
        $kind = RoomKinds::get($request->validated('kind'));

        $result = $finisher->finish(
            $kind,
            (int) $request->user()->id,
            (int) $request->validated('attempt_id'),
            $request->validated('device_token'),
            $request->answers(),
            $request->validated('reason', 'manual') === 'time_up',
        );

        return match ($result['status']) {
            'device' => response()->json(['error' => 'device'], 409),
            'extended' => response()->json(['remaining' => max(0, $result['attempt']->expires_at->timestamp - now()->timestamp)]),
            default => response()->json(['result_url' => $kind->resultUrl($result['attempt'])]),
        };
    }
}
