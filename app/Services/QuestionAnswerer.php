<?php

namespace App\Services;

use App\Ai\Agents\QuestionAgent;
use App\Enums\SubjectKind;
use App\Models\Answer;
use App\Models\Horse;
use App\Models\HorseEvent;
use App\Models\Memo;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class QuestionAnswerer
{
    public const Unknown = 'The records do not say.';

    public static function unknown(string $language): string
    {
        return $language === 'cs' ? 'V záznamech to není.' : self::Unknown;
    }

    public function answer(Memo $memo): void
    {
        try {
            $this->interpret($memo);
        } catch (Throwable) {
            // A failed answer pass leaves the transcript and any earlier answers in place.
        }
    }

    private function interpret(Memo $memo): void
    {
        $transcript = trim((string) $memo->transcript);

        if ($transcript === '') {
            Answer::query()->where('memo_id', $memo->id)->delete();

            return;
        }

        $memo->load([
            'stable.horses.events' => function ($query): void {
                $query->whereNull('retracted_at');
            },
        ]);

        $agent = QuestionAgent::make();
        $agent->language = $memo->stable->language?->value ?? 'en';
        $response = $agent->prompt($this->prompt($memo, $transcript));
        $questions = $response['questions'] ?? null;

        if (! is_array($questions)) {
            throw new RuntimeException('Question response did not include a questions list.');
        }

        $entries = $this->normalize($questions);

        DB::transaction(function () use ($memo, $entries): void {
            Answer::query()->where('memo_id', $memo->id)->delete();

            foreach ($entries as $entry) {
                $memo->answers()->create([
                    'stable_id' => $memo->stable_id,
                    'question' => $entry['question'],
                    'answer' => $entry['answer'],
                ]);
            }
        });
    }

    private function prompt(Memo $memo, string $transcript): string
    {
        $roster = $memo->stable->horses
            ->map(function (Horse $horse): string {
                $aliases = $horse->aliases === [] ? 'none' : implode(', ', $horse->aliases);
                $knowledge = trim($horse->knowledge) === '' ? 'none' : $horse->knowledge;
                $events = $horse->events
                    ->map(function (HorseEvent $event): string {
                        $when = $event->occurred_on?->toDateString() ?? 'undated';
                        $detail = trim($event->detail) === '' ? '—' : $event->detail;

                        return "  - {$when} | {$event->summary} | {$detail}";
                    })
                    ->implode("\n");

                if ($events === '') {
                    $events = '  none';
                }

                $kind = $horse->kind?->value ?? SubjectKind::Animal->value;

                return "- {$horse->name} [{$kind}] (aliases: {$aliases})\n  Knowledge:\n{$knowledge}\n  Events:\n{$events}";
            })
            ->implode("\n");

        if ($roster === '') {
            $roster = 'No records yet.';
        }

        $language = $memo->stable->language?->promptName() ?? 'English';

        return <<<TEXT
Stable language: {$language}

Roster:
{$roster}

Transcript:
{$transcript}
TEXT;
    }

    /**
     * @param  list<mixed>  $questions
     * @return list<array{question: string, answer: string}>
     */
    private function normalize(array $questions): array
    {
        $entries = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $asked = is_string($question['question'] ?? null) ? trim($question['question']) : '';
            $reply = is_string($question['answer'] ?? null) ? trim($question['answer']) : '';

            if ($asked === '' || $reply === '') {
                continue;
            }

            $entries[] = [
                'question' => str($asked)->limit(2000, '')->toString(),
                'answer' => str($reply)->limit(5000, '')->toString(),
            ];
        }

        return $entries;
    }
}
