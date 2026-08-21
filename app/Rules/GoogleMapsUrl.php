<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GoogleMapsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $url = filter_var($value, FILTER_VALIDATE_URL);
        $scheme = is_string($url) ? parse_url($url, PHP_URL_SCHEME) : null;
        $host = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_HOST)) : '';
        $isGoogleHost = $host === 'google.com'
            || str_ends_with($host, '.google.com')
            || $host === 'maps.app.goo.gl'
            || $host === 'goo.gl';

        if (! $url || $scheme !== 'https' || ! $isGoogleHost) {
            $fail('The :attribute must be a valid HTTPS Google Maps link.');
        }
    }
}
