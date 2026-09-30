<?php

namespace App\Services;

use App\Contracts\SpeechTranscriber;
use App\Models\Memo;
use App\Support\SpeechDebug;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\AiManager;
use Laravel\Ai\Files\StoredAudio;
use Laravel\Ai\Transcription;
use RuntimeException;

class GroqTranscriber implements SpeechTranscriber
{
    public function transcribe(Memo $memo): string
    {
        $apiKey = config('stt.groq_key');
        $apiKey = is_string($apiKey) ? trim($apiKey) : '';

        if ($apiKey === '') {
            throw new RuntimeException(SpeechDebug::missingKeyMessage());
        }

        config(['ai.providers.groq.key' => $apiKey]);
        app(AiManager::class)->forgetInstance('groq');

        $disk = Storage::disk($memo->disk);

        if (! $disk->exists($memo->disk_path)) {
            throw new RuntimeException('Audio file is missing from storage.');
        }

        $audio = (new StoredAudio($memo->disk_path, $memo->disk))
            ->withMimeType((string) $memo->mime);

        $text = (string) Transcription::of($audio)
            ->timeout((int) config('stt.timeout'))
            ->generate('groq', (string) config('stt.model'));

        if (trim($text) === '') {
            throw new RuntimeException('Groq did not include a transcript.');
        }

        return trim($text);
    }
}
