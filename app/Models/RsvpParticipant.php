<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsvpParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'rsvp_response_id',
        'full_name',
        'will_attend',
    ];

    protected function casts(): array
    {
        return ['will_attend' => 'boolean'];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(RsvpResponse::class, 'rsvp_response_id');
    }
}
