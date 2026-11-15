<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Checks submitted values against simple rules and returns plain English
 * error messages, one per field.
 *
 *   $errors = Validator::validate($data, [
 *       'email'    => 'required|email|maxlen:120',
 *       'capacity' => 'required|int|min:1|max:2000',
 *       'type'     => 'required|in:lecture,lab',
 *   ], ['capacity' => 'Room capacity']);
 *
 * Empty optional fields skip every rule except "required".
 * min and max compare numbers; minlen and maxlen compare text length.
 */
final class Validator
{
    /** @return array<string,string> field => first error message (empty when valid) */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', (string) $field));
            $value = $data[$field] ?? null;
            $text = is_scalar($value) ? trim((string) $value) : '';
            $rules = explode('|', $ruleString);

            if ($text === '') {
                if (in_array('required', $rules, true)) {
                    $errors[$field] = "{$label} is required.";
                }
                continue;
            }
            foreach ($rules as $rule) {
                $message = self::check($rule, $text, $label);
                if ($message !== null) {
                    $errors[$field] = $message;
                    break;
                }
            }
        }
        return $errors;
    }

    /** Returns an error message, or null when the value passes the rule. */
    private static function check(string $rule, string $value, string $label): ?string
    {
        [$name, $arg] = array_pad(explode(':', $rule, 2), 2, '');
        return match ($name) {
            'required' => null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null : "{$label} must be a valid email address.",
            'int' => preg_match('/^-?\d+$/', $value)
                ? null : "{$label} must be a whole number.",
            'numeric' => is_numeric($value)
                ? null : "{$label} must be a number.",
            'min' => is_numeric($value) && (float) $value >= (float) $arg
                ? null : "{$label} must be at least {$arg}.",
            'max' => is_numeric($value) && (float) $value <= (float) $arg
                ? null : "{$label} must be at most {$arg}.",
            'minlen' => mb_strlen($value) >= (int) $arg
                ? null : "{$label} must be at least {$arg} characters.",
            'maxlen' => mb_strlen($value) <= (int) $arg
                ? null : "{$label} must be at most {$arg} characters.",
            'in' => in_array($value, explode(',', $arg), true)
                ? null : "{$label} must be one of: " . str_replace(',', ', ', $arg) . '.',
            'time' => self::isTime($value)
                ? null : "{$label} must be a time like 08:00.",
            'code' => preg_match('/^[A-Za-z0-9 _.\/-]+$/', $value)
                ? null : "{$label} may contain only letters, numbers, spaces and - _ . /",
            default => throw new \InvalidArgumentException("Unknown validation rule: {$name}"),
        };
    }

    private static function isTime(string $value): bool
    {
        if (!preg_match('/^(\d{2}):(\d{2})(:00)?$/', $value, $m)) {
            return false;
        }
        return (int) $m[1] <= 23 && (int) $m[2] <= 59;
    }
}
