<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RsvpResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'rsvp_link_id',
        'submission_key',
        'will_attend',
        'participant_count',
        'submitted_at',
    ];

    protected $hidden = ['submission_key'];

    protected function casts(): array
    {
        return [
            'will_attend' => 'boolean',
            'participant_count' => 'integer',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function rsvpLink(): BelongsTo
    {
        return $this->belongsTo(RsvpLink::class)->withTrashed();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(RsvpParticipant::class);
    }
}
