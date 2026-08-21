<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RsvpLink extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'event_date',
        'event_time',
        'venue',
        'venue_map_url',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'immutable_date',
            'expires_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RsvpLink $link): void {
            if ($link->token) {
                return;
            }

            do {
                $link->token = Str::random(48);
            } while (static::query()->where('token', $link->token)->exists());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RsvpResponse::class);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        return $this->expires_at->isPast() ? 'expired' : 'active';
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('expires_at', '>', now());
    }
}
