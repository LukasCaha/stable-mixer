<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SpeechDebug
{
    /**
     * What this PHP process will use for the next transcription.
     * The queue worker is a different process and can still be on an older config cache.
     *
     * @return list<string>
     */
    public static function lines(): array
    {
        $lines = [
            self::keyLine(),
            'Provider: groq. Model: '.(string) config('stt.model').'. Timeout: '.(int) config('stt.timeout').'s.',
            self::cacheLine(),
            self::queueLine(),
        ];

        $worker = self::workerLine();
        if ($worker !== null) {
            $lines[] = $worker;
        }

        return $lines;
    }

    public static function missingKeyMessage(): string
    {
        $why = app()->configurationIsCached()
            ? 'Config is cached, so a key saved in .env is invisible until you run config:cache and restart the queue worker.'
            : 'This process loaded an empty GROQ_API_KEY.';

        return 'GROQ_API_KEY is empty in this process. '.$why;
    }

    private static function keyLine(): string
    {
        $key = config('stt.groq_key');

        if (! is_string($key) || trim($key) === '') {
            return 'Groq key: empty in this process.';
        }

        $key = trim($key);

        return 'Groq key: set, starts with '.substr($key, 0, 4).', '.strlen($key).' characters.';
    }

    private static function cacheLine(): string
    {
        if (app()->configurationIsCached()) {
            return 'Config cache: on. Saving the Environment file does nothing until config:cache runs and the queue worker restarts.';
        }

        return 'Config cache: off. This process is reading the current environment.';
    }

    private static function queueLine(): string
    {
        $connection = (string) config('queue.default');

        if ($connection === 'sync') {
            return 'Queue: sync. Jobs run inside the web request, so a Forge daemon is not involved.';
        }

        if ($connection !== 'database') {
            return 'Queue: '.$connection.'.';
        }

        $waiting = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<=', time())->count();
        $reserved = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $delayed = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '>', time())->count();
        $failed = DB::table('failed_jobs')->count();

        return "Queue: database. {$waiting} ready, {$reserved} in progress, {$delayed} delayed, {$failed} in failed_jobs.";
    }

    private static function workerLine(): ?string
    {
        if (config('queue.default') !== 'database') {
            return null;
        }

        $waiting = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<=', time())->count();
        $reserved = DB::table('jobs')->whereNotNull('reserved_at')->count();

        if ($waiting > 0 && $reserved === 0) {
            return 'Jobs are waiting and none are in progress. No queue worker is running.';
        }

        return null;
    }
}
