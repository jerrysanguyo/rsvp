<?php

namespace App\Http\Requests\Admin\RsvpLinks;

use App\Http\Requests\Admin\AdminUpdateRequest;
use App\Rules\GoogleMapsUrl;

class UpdateRsvpLinkRequest extends AdminUpdateRequest
{
    protected function isPermitted(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'event_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'event_time' => ['sometimes', 'required', 'string', 'max:80'],
            'venue' => ['sometimes', 'required', 'string', 'max:160'],
            'venue_map_url' => ['sometimes', 'nullable', 'string', 'max:2048', new GoogleMapsUrl],
            'expires_at' => ['sometimes', 'required', 'date', 'after:now'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
