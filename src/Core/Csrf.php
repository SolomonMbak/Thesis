<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cross-site request forgery protection.
 *
 * One random token per session. Forms send it in a hidden field;
 * JavaScript fetch() calls send it in the X-CSRF-Token header.
 * The store is passed by reference so tests can use a plain array.
 */
final class Csrf
{
    public const FIELD = '_csrf';
    public const HEADER = 'X-CSRF-Token';
    private const KEY = '_csrf_token';

    private array $store;

    public function __construct(array &$store)
    {
        $this->store = &$store;
    }

    public function token(): string
    {
        if (empty($this->store[self::KEY]) || !is_string($this->store[self::KEY])) {
            $this->store[self::KEY] = bin2hex(random_bytes(32));
        }
        return $this->store[self::KEY];
    }

    public function isValid(?string $submitted): bool
    {
        $expected = $this->store[self::KEY] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals($expected, $submitted);
    }

    /** Check a POST request, accepting the token from the form field or the header. */
    public function checkRequest(Request $request): bool
    {
        $token = $request->input(self::FIELD);
        if (!is_string($token) || $token === '') {
            $token = $request->header(self::HEADER);
        }
        return $this->isValid($token);
    }

    /** Hidden input for HTML forms. */
    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e($this->token()) . '">';
    }
}
