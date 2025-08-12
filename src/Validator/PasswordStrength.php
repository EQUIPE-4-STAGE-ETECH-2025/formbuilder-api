<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PasswordStrength extends Constraint
{
    public string $message = 'Le mot de passe est trop faible. Il doit contenir au moins : {{ rules }}.';

    public function __construct(
        public int $minLength = 8,
        public bool $requireUppercase = true,
        public bool $requireLowercase = true,
        public bool $requireDigit = true,
        public bool $requireSpecialChar = true,
        string $message = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);

        if ($message) {
            $this->message = $message;
        }
    }
}
