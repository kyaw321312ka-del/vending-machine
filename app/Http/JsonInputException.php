<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class JsonInputException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message
    ) {
        parent::__construct($message);
    }
}
