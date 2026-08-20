<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RsvpLinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'expires_at' => $this->expires_at->toIso8601String(),
            'expires_label' => $this->expires_at->format('M j, Y · g:i A'),
            'is_active' => $this->is_active,
            'status' => $this->status(),
            'public_url' => route('rsvp.show', $this->resource),
            'update_url' => route('admin.rsvp-links.update', $this->resource),
            'destroy_url' => route('admin.rsvp-links.destroy', $this->resource),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
