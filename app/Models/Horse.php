<?php

namespace App\Models;

use App\Enums\SubjectKind;
use Database\Factories\HorseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Horse extends Model
{
    /** @use HasFactory<HorseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'stable_id',
        'name',
        'name_key',
        'aliases',
        'knowledge',
        'kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'kind' => SubjectKind::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Horse $horse): void {
            $horse->name_key = mb_strtolower(trim($horse->name));
        });
    }

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HorseEvent::class)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');
    }
}
