<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Thrown to stop a request with an HTTP status and a message that is safe
 * to show the user (plain English, says what to do next).
 */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message, $status);
    }
}
