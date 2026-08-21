<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PlainTextSanitizer
{
    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function sanitizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $values[$key] = $this->sanitize($value, (string) $key);
            }
        }

        return $values;
    }

    public function sanitize(string $value, string $field = 'value'): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException(sprintf('%s must contain valid UTF-8 text.', $field));
        }

        $originalValue = $value;
        $value = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $value) ?? '';
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }

        $value = trim($value);

        if ($value === '' && trim($originalValue) !== '') {
            throw ValidationException::withMessages([
                $field => 'This field must contain valid plain text.',
            ]);
        }

        return $value;
    }
}
