<?php

namespace App\Filament\Widgets;

use App\Support\SpeechDebug as SpeechDebugReport;
use Filament\Widgets\Widget;

class SpeechDebug extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.speech-debug';

    /**
     * @return array{lines: list<string>}
     */
    protected function getViewData(): array
    {
        return [
            'lines' => SpeechDebugReport::lines(),
        ];
    }
}
