<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'stable_id',
        'memo_id',
        'question',
        'answer',
    ];

    public function stable(): BelongsTo
    {
        return $this->belongsTo(Stable::class);
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class);
    }
}
