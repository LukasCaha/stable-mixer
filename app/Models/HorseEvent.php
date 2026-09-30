<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorseEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'horse_id',
        'memo_id',
        'occurred_on',
        'summary',
        'detail',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class);
    }
}
