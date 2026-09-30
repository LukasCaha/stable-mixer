<?php

namespace App\Services;

use App\Contracts\SpeechTranscriber;
use App\Models\Memo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenAiCompatibleTranscriber implements SpeechTranscriber
{
    public function transcribe(Memo $memo): string
    {
        $apiKey = config('stt.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('STT_API_KEY is not configured.');
        }

        $disk = Storage::disk($memo->disk);

        if (! $disk->exists($memo->disk_path)) {
            throw new RuntimeException('Audio file is missing from storage.');
        }

        $response = Http::withToken($apiKey)
            ->baseUrl(rtrim((string) config('stt.base_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('stt.timeout'))
            ->attach(
                'file',
                $disk->get($memo->disk_path),
                basename($memo->disk_path),
                ['Content-Type' => $memo->mime],
            )
            ->post('/audio/transcriptions', [
                'model' => config('stt.model'),
            ]);

        $response->throw();

        $text = $response->json('text');

        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('STT response did not include a transcript.');
        }

        return trim($text);
    }
}
