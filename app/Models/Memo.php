<?php

namespace App\Models;

use App\Enums\HorseLogStatus;
use App\Enums\MemoStatus;
use Database\Factories\MemoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memo extends Model
{
    /** @use HasFactory<MemoFactory> */
    use HasFactory, HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'stable_id',
        'disk',
        'disk_path',
        'mime',
        'size',
        'status',
        'transcript',
        'error',
        'log_status',
        'log_error',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'status' => MemoStatus::class,
            'log_status' => HorseLogStatus::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function horseEvents(): HasMany
    {
        return $this->hasMany(HorseEvent::class)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');
    }

    /**
     * @param  Builder<Memo>  $query
     * @return Builder<Memo>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            MemoStatus::Queued,
            MemoStatus::Processing,
        ]);
    }
}
