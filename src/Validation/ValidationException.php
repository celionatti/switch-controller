<?php

declare(strict_types=1);

namespace Switch\Controller\Validation;

use RuntimeException;

class ValidationException extends RuntimeException
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $errors;

    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(array $errors, string $message = 'The given data was invalid.')
    {
        parent::__construct($message, 422);
        $this->errors = $errors;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first validation error message.
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }
}
