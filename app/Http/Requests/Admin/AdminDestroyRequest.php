<?php

namespace App\Http\Requests\Admin;

abstract class AdminDestroyRequest extends AdminRequest
{
    final protected function allowedMethods(): array
    {
        return ['DELETE'];
    }
}
