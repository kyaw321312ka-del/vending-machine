<?php

declare(strict_types=1);

namespace App\Http;

use Closure;
use JsonException;

final class JsonRequest
{
    private Closure $bodyReader;

    public function __construct(?callable $bodyReader = null)
    {
        $this->bodyReader = Closure::fromCallable(
            $bodyReader ?? static fn (): string|false => file_get_contents('php://input')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strtolower(trim(explode(';', $contentType, 2)[0])) !== 'application/json') {
            throw new JsonInputException(415, 'Content-Type must be application/json.');
        }

        $rawBody = ($this->bodyReader)();
        if (!is_string($rawBody) || $rawBody === '') {
            throw new JsonInputException(400, 'A JSON object body is required.');
        }

        try {
            $decoded = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new JsonInputException(400, 'Request body must contain valid JSON.');
        }

        if (!$decoded instanceof \stdClass) {
            throw new JsonInputException(400, 'Request body must be a JSON object.');
        }

        return get_object_vars($decoded);
    }
}
