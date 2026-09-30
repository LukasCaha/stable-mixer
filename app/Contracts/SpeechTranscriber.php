<?php

namespace App\Contracts;

use App\Models\Memo;

interface SpeechTranscriber
{
    public function transcribe(Memo $memo): string;
}
