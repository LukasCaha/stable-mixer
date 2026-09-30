<?php

namespace App\Filament\Widgets;

use App\Enums\MemoStatus;
use App\Models\Memo;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class MemoStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $memos = $this->memos();

        $failed = (clone $memos)->where('status', MemoStatus::Failed)->count();

        return [
            Stat::make('Memos today', (clone $memos)->whereDate('created_at', today())->count())
                ->description('Uploaded since midnight'),
            Stat::make('Pending transcription', (clone $memos)->pending()->count())
                ->description('Queued or processing')
                ->color('warning'),
            Stat::make('Failed', $failed)
                ->description('Speech-to-text errors')
                ->color($failed > 0 ? 'danger' : 'gray'),
        ];
    }

    /**
     * @return Builder<Memo>
     */
    private function memos(): Builder
    {
        return Memo::query()->whereBelongsTo(Filament::getTenant(), 'stable');
    }
}
