<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnswersRequest;
use App\Models\Horse;
use App\Models\HorseEvent;
use Illuminate\Http\JsonResponse;

class RecordController extends Controller
{
    public function index(AnswersRequest $request): JsonResponse
    {
        $stable = $request->stable();

        $records = Horse::query()
            ->where('stable_id', $stable->id)
            ->with(['events' => function ($query): void {
                $query->whereNull('retracted_at');
            }])
            ->orderBy('name')
            ->limit(100)
            ->get();

        return response()->json([
            'records' => $records->map(fn (Horse $horse): array => [
                'id' => $horse->id,
                'name' => $horse->name,
                'kind' => $horse->kind?->value ?? 'animal',
                'knowledge' => $horse->knowledge,
                'events' => $horse->events->take(12)->map(fn (HorseEvent $event): array => [
                    'occurred_on' => $event->occurred_on?->toDateString(),
                    'summary' => $event->summary,
                    'detail' => $event->detail,
                ])->all(),
            ])->all(),
        ]);
    }
}
