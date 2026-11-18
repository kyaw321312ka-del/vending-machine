<?php

declare(strict_types=1);

namespace App\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public readonly string $method;

    public function __construct(
        string $method,
        public readonly string $path,
        public readonly array $middleware = []
    ) {
        $this->method = strtoupper($method);
    }
}
