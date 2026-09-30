<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnswersRequest;
use App\Models\Answer;
use App\Models\Memo;
use Illuminate\Http\JsonResponse;

class AnswerController extends Controller
{
    public function index(AnswersRequest $request): JsonResponse
    {
        $stable = $request->stable();

        $answers = Answer::query()
            ->where('stable_id', $stable->id)
            ->latest()
            ->limit(50)
            ->get();

        $pending = Memo::query()
            ->where('stable_id', $stable->id)
            ->whereIn('status', [MemoStatus::Queued, MemoStatus::Processing])
            ->exists();

        return response()->json([
            'pending' => $pending,
            'answers' => $answers->map(fn (Answer $answer): array => [
                'id' => $answer->id,
                'memo_id' => $answer->memo_id,
                'question' => $answer->question,
                'answer' => $answer->answer,
                'asked_at' => $answer->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
