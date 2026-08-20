<?php

namespace App\Http\Requests\Admin\RsvpLinks;

use App\Http\Requests\Admin\AdminDestroyRequest;

class DestroyRsvpLinkRequest extends AdminDestroyRequest
{
    protected function isPermitted(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
