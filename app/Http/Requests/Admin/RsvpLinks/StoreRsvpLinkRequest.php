<?php

namespace App\Http\Requests\Admin\RsvpLinks;

use App\Http\Requests\Admin\AdminStoreRequest;
use App\Rules\GoogleMapsUrl;

class StoreRsvpLinkRequest extends AdminStoreRequest
{
    protected function isPermitted(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'event_time' => ['required', 'string', 'max:80'],
            'venue' => ['required', 'string', 'max:160'],
            'venue_map_url' => ['nullable', 'string', 'max:2048', new GoogleMapsUrl],
            'expires_at' => ['required', 'date', 'after:now'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
