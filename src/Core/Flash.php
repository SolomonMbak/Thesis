<?php
declare(strict_types=1);

namespace App\Core;

/**
 * One-time messages shown on the next page (e.g. "Course saved.").
 */
final class Flash
{
    private const KEY = '_flash';

    private array $store;

    public function __construct(array &$store)
    {
        $this->store = &$store;
    }

    public function success(string $message): void
    {
        $this->add('success', $message);
    }

    public function error(string $message): void
    {
        $this->add('error', $message);
    }

    public function info(string $message): void
    {
        $this->add('info', $message);
    }

    /** Returns all messages as [['type' => ..., 'message' => ...]] and clears them. */
    public function take(): array
    {
        $messages = $this->store[self::KEY] ?? [];
        unset($this->store[self::KEY]);
        return $messages;
    }

    private function add(string $type, string $message): void
    {
        $this->store[self::KEY][] = ['type' => $type, 'message' => $message];
    }
}
