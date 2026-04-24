<?php

namespace App\Services\Factus;

use RuntimeException;

class FactusApiException extends RuntimeException
{
    public function __construct(
        string $message,
        protected readonly array $context = [],
        int $code = 0
    ) {
        parent::__construct($message, $code);
    }

    public function context(): array
    {
        return $this->context;
    }
}
