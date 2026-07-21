<?php

namespace App\Exceptions;

use RuntimeException;

class AgentEnrollmentException extends RuntimeException
{
    public function __construct(
        string $message = 'Kod instalacyjny jest nieprawidłowy, wygasł albo został już wykorzystany.',
        public readonly int $httpStatus = 422
    ) {
        parent::__construct($message);
    }
}
