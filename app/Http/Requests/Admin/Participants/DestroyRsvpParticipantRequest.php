<?php

namespace App\Http\Requests\Admin\Participants;

use App\Http\Requests\Admin\AdminDestroyRequest;

class DestroyRsvpParticipantRequest extends AdminDestroyRequest
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
