<?php

namespace App\Services;

use App\Ai\Agents\HorseLogAgent;
use App\Enums\HorseLogStatus;
use App\Models\Horse;
use App\Models\HorseEvent;
use App\Models\Memo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class HorseLogWriter
{
    public function record(Memo $memo): void
    {
        try {
            $this->interpret($memo);
        } catch (Throwable $exception) {
            $memo->update([
                'log_status' => HorseLogStatus::Failed,
                'log_error' => str($exception->getMessage() ?: 'Horse log failed.')->limit(2000)->toString(),
            ]);
        }
    }

    private function interpret(Memo $memo): void
    {
        $transcript = trim((string) $memo->transcript);

        if ($transcript === '') {
            DB::transaction(function () use ($memo): void {
                HorseEvent::query()->where('memo_id', $memo->id)->delete();
                $memo->update([
                    'log_status' => HorseLogStatus::Skipped,
                    'log_error' => null,
                ]);
            });

            return;
        }

        $memo->load('stable.horses');
        $memo->update([
            'log_status' => HorseLogStatus::Pending,
            'log_error' => null,
        ]);

        $response = HorseLogAgent::make()->prompt($this->prompt($memo, $transcript));
        $horses = $response['horses'] ?? null;

        if (! is_array($horses)) {
            throw new RuntimeException('Horse log response did not include a horses list.');
        }

        $this->apply($memo, $horses);
    }

    /**
     * @param  list<mixed>  $horses
     */
    private function apply(Memo $memo, array $horses): void
    {
        $entries = $this->normalize($horses);

        DB::transaction(function () use ($memo, $entries): void {
            HorseEvent::query()->where('memo_id', $memo->id)->delete();

            foreach ($entries as $entry) {
                $horse = $this->upsertHorse($memo, $entry);

                foreach ($entry['events'] as $event) {
                    $horse->events()->create([
                        'memo_id' => $memo->id,
                        'occurred_on' => $event['occurred_on'],
                        'summary' => $event['summary'],
                        'detail' => $event['detail'],
                    ]);
                }
            }

            $memo->update([
                'log_status' => HorseLogStatus::Done,
                'log_error' => null,
            ]);
        });
    }

    private function prompt(Memo $memo, string $transcript): string
    {
        $roster = $memo->stable->horses
            ->map(function (Horse $horse): string {
                $aliases = $horse->aliases === [] ? 'none' : implode(', ', $horse->aliases);
                $knowledge = trim($horse->knowledge) === '' ? 'none' : $horse->knowledge;

                return "- {$horse->name} (aliases: {$aliases})\n  Knowledge:\n{$knowledge}";
            })
            ->implode("\n");

        if ($roster === '') {
            $roster = 'No horses yet.';
        }

        $recordedAt = $memo->recorded_at?->toIso8601String() ?? 'unknown';

        return <<<TEXT
Recorded at: {$recordedAt}

Roster:
{$roster}

Transcript:
{$transcript}
TEXT;
    }

    /**
     * @param  list<mixed>  $horses
     * @return list<array{name: string, name_key: string, aliases: list<string>, knowledge: string, events: list<array{occurred_on: ?string, summary: string, detail: string}>}>
     */
    private function normalize(array $horses): array
    {
        $grouped = [];

        foreach ($horses as $horse) {
            if (! is_array($horse)) {
                continue;
            }

            $name = is_string($horse['name'] ?? null) ? trim($horse['name']) : '';

            if ($name === '') {
                continue;
            }

            $name = str($name)->limit(255, '')->toString();
            $key = mb_strtolower($name);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'name' => $name,
                    'name_key' => $key,
                    'aliases' => [],
                    'knowledge' => '',
                    'events' => [],
                ];
            }

            $grouped[$key]['aliases'] = $this->mergeAliases(
                $grouped[$key]['aliases'],
                $this->stringList($horse['aliases'] ?? []),
            );
            $grouped[$key]['knowledge'] = is_string($horse['knowledge'] ?? null) ? $horse['knowledge'] : '';

            foreach ($this->events($horse['events'] ?? []) as $event) {
                $grouped[$key]['events'][] = $event;
            }
        }

        return array_values($grouped);
    }

    /**
     * @param  array{name: string, name_key: string, aliases: list<string>, knowledge: string, events: list<array{occurred_on: ?string, summary: string, detail: string}>}  $entry
     */
    private function upsertHorse(Memo $memo, array $entry): Horse
    {
        $horse = Horse::query()
            ->where('stable_id', $memo->stable_id)
            ->where('name_key', $entry['name_key'])
            ->first();

        if ($horse === null) {
            $horse = new Horse([
                'stable_id' => $memo->stable_id,
                'name' => $entry['name'],
            ]);
        }

        $horse->aliases = $this->mergeAliases($horse->aliases ?? [], $entry['aliases']);
        $horse->knowledge = $entry['knowledge'];
        $horse->save();

        return $horse;
    }

    /**
     * @param  list<string>  $current
     * @param  list<string>  $incoming
     * @return list<string>
     */
    private function mergeAliases(array $current, array $incoming): array
    {
        $merged = [];

        foreach ([...$current, ...$incoming] as $alias) {
            $alias = trim($alias);

            if ($alias === '') {
                continue;
            }

            $alias = str($alias)->limit(255, '')->toString();
            $key = mb_strtolower($alias);

            if (! array_key_exists($key, $merged)) {
                $merged[$key] = $alias;
            }
        }

        return array_values($merged);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    /**
     * @return list<array{occurred_on: ?string, summary: string, detail: string}>
     */
    private function events(mixed $events): array
    {
        if (! is_array($events)) {
            return [];
        }

        $normalized = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $summary = is_string($event['summary'] ?? null) ? trim($event['summary']) : '';

            if ($summary === '') {
                continue;
            }

            $detail = is_string($event['detail'] ?? null) ? $event['detail'] : '';

            $normalized[] = [
                'occurred_on' => $this->dateOrNull($event['occurred_on'] ?? null),
                'summary' => str($summary)->limit(2000, '')->toString(),
                'detail' => str($detail)->limit(5000, '')->toString(),
            ];
        }

        return $normalized;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
