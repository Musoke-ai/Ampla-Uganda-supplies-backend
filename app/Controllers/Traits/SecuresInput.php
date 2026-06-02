<?php

namespace App\Controllers\Traits;

trait SecuresInput
{
    protected function secureText($value, int $maxLength = 255, bool $nullable = false): ?string
    {
        if ($value === null) {
            return $nullable ? null : '';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        $text = trim((string) $value);
        $text = strip_tags($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        if ($text === '') {
            return $nullable ? null : '';
        }

        return function_exists('mb_substr')
            ? mb_substr($text, 0, $maxLength)
            : substr($text, 0, $maxLength);
    }

    protected function secureUpperText($value, int $maxLength = 255, bool $nullable = false): ?string
    {
        $text = $this->secureText($value, $maxLength, $nullable);

        return $text === null ? null : strtoupper($text);
    }

    protected function secureEmail($value, bool $nullable = true): ?string
    {
        $email = strtolower((string) $this->secureText($value, 255, $nullable));

        if ($email === '' || $email === null) {
            return $nullable ? null : '';
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    protected function secureInt($value, ?int $fallback = null): ?int
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $fallback;
    }

    protected function secureDecimal($value, $fallback = null)
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $normalized = str_replace(',', '', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : $fallback;
    }

    protected function secureNonNegativeDecimal($value, $fallback = 0)
    {
        $number = $this->secureDecimal($value, $fallback);

        return $number === null ? null : max(0, (float) $number);
    }

    protected function secureAllowed($value, array $allowed, string $fallback): string
    {
        $text = strtolower((string) $this->secureText($value, 60, true));

        return in_array($text, $allowed, true) ? $text : $fallback;
    }

    protected function secureArrayRecursive(array $values, int $maxStringLength = 1000): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $cleanKey = is_string($key) ? (string) $this->secureText($key, 120) : $key;

            if (is_array($value)) {
                $clean[$cleanKey] = $this->secureArrayRecursive($value, $maxStringLength);
                continue;
            }

            if (is_string($value)) {
                $clean[$cleanKey] = $this->secureText($value, $maxStringLength, true);
                continue;
            }

            $clean[$cleanKey] = $value;
        }

        return $clean;
    }
}
