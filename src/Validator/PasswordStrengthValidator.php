<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PasswordStrengthValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof PasswordStrength) {
            return;
        }

        if (! is_string($value) || empty($value)) {
            return;
        }

        $errors = [];

        if (strlen($value) < $constraint->minLength) {
            $errors[] = "{$constraint->minLength} caractères";
        }

        if ($constraint->requireUppercase && ! preg_match('/[A-Z]/', $value)) {
            $errors[] = "une majuscule";
        }

        if ($constraint->requireLowercase && ! preg_match('/[a-z]/', $value)) {
            $errors[] = "une minuscule";
        }

        if ($constraint->requireDigit && ! preg_match('/\d/', $value)) {
            $errors[] = "un chiffre";
        }

        if ($constraint->requireSpecialChar && ! preg_match('/[\W_]/', $value)) {
            $errors[] = "un caractère spécial";
        }

        if (! empty($errors)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ rules }}', implode(', ', $errors))
                ->addViolation();
        }
    }
}