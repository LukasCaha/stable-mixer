<?php

namespace App\Models;

use Database\Factories\StableFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use RuntimeException;

class Stable extends Model implements HasName
{
    /** @use HasFactory<StableFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'tenant_code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Stable $stable): void {
            $code = filled($stable->tenant_code)
                ? strtoupper((string) $stable->tenant_code)
                : static::generateTenantCode();

            if (! preg_match('/^[A-Z0-9]{8}$/', $code)) {
                throw new InvalidArgumentException('Tenant code must be 8 characters, A-Z and 0-9.');
            }

            $stable->tenant_code = $code;
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function memos(): HasMany
    {
        return $this->hasMany(Memo::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->latest();
    }

    public function horses(): HasMany
    {
        return $this->hasMany(Horse::class)->orderBy('name');
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public static function generateTenantCode(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = '';

            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, $length)];
            }

            if (! static::query()->where('tenant_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate a unique tenant code.');
    }

    public function regenerateTenantCode(): string
    {
        $code = static::generateTenantCode();
        $this->update(['tenant_code' => $code]);

        return $code;
    }
}
