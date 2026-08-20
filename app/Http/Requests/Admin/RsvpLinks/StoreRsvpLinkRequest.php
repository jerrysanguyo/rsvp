<?php

namespace App\Http\Requests\Admin\RsvpLinks;

use App\Http\Requests\Admin\AdminStoreRequest;

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
            'expires_at' => ['required', 'date', 'after:now'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
