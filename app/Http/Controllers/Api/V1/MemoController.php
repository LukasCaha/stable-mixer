<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemoRequest;
use App\Jobs\TranscribeMemo;
use App\Models\Memo;
use Illuminate\Http\JsonResponse;

class MemoController extends Controller
{
    /**
     * Companion upload. v0 trusts the stable tenant code; user auth comes later.
     */
    public function store(StoreMemoRequest $request): JsonResponse
    {
        $stable = $request->stable();
        $file = $request->file('file');
        $disk = (string) config('memos.disk');
        $path = $file->store('stables/'.$stable->id, $disk);

        $memo = Memo::query()->create([
            'stable_id' => $stable->id,
            'disk' => $disk,
            'disk_path' => $path,
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
            'status' => MemoStatus::Queued,
            'recorded_at' => $request->date('recorded_at'),
        ]);

        TranscribeMemo::dispatch($memo);

        return response()->json([
            'id' => $memo->id,
            'status' => $memo->status->value,
        ], 201);
    }
}
