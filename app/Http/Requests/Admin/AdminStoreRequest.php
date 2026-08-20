<?php

namespace App\Http\Requests\Admin;

abstract class AdminStoreRequest extends AdminRequest
{
    final protected function allowedMethods(): array
    {
        return ['POST'];
    }
}
