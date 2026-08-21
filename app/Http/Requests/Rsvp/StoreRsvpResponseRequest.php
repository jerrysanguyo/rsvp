<?php

namespace App\Http\Requests\Rsvp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRsvpResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'submission_key' => $this->header('X-Idempotency-Key'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'submission_key' => ['required', 'uuid'],
            'will_attend' => ['required', 'boolean'],
            'participants' => ['required', 'array', 'min:1', 'max:8'],
            'participants.*.full_name' => ['required', 'string', 'min:2', 'max:120', 'distinct:ignore_case'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('will_attend') && count((array) $this->input('participants')) > 1) {
                    $validator->errors()->add('participants', 'A declined RSVP may contain only the respondent’s name.');
                }
            },
        ];
    }

    /** @return array{submission_key: string, will_attend: bool, participants: list<array{full_name: string}>} */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'submission_key' => $validated['submission_key'],
            'will_attend' => (bool) $validated['will_attend'],
            'participants' => $validated['participants'],
        ];
    }
}
