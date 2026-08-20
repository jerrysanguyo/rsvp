<?php

namespace App\Http\Requests\Admin;

abstract class AdminUpdateRequest extends AdminRequest
{
    final protected function allowedMethods(): array
    {
        return ['PUT', 'PATCH'];
    }
}
