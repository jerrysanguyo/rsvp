<?php

namespace App\Http\Requests\Admin\RsvpLinks;

use App\Http\Requests\Admin\AdminUpdateRequest;

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
            'expires_at' => ['sometimes', 'required', 'date', 'after:now'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
