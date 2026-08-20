<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdminRequest extends FormRequest
{
    final public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->is_active
            && in_array($this->method(), $this->allowedMethods(), true)
            && $this->isPermitted();
    }

    /**
     * Every concrete request must explicitly authorize its resource/action.
     * For example: return $this->user()->can('rsvp-links.create');
     */
    abstract protected function isPermitted(): bool;

    /** @return list<string> */
    abstract protected function allowedMethods(): array;

    /** @return array<string, mixed> */
    final public function payload(): array
    {
        return $this->validated();
    }
}
