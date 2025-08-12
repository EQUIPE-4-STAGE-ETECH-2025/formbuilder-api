<?php

namespace App\Tests\Validator;

use App\Validator\PasswordStrength;
use App\Validator\PasswordStrengthValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class PasswordStrengthValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new PasswordStrengthValidator();
    }

    public function testValidPassword(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate('Aa1$aaaa', $constraint);

        $this->assertNoViolation();
    }

    public function testPasswordTooShort(): void
    {
        $constraint = new PasswordStrength(minLength: 10);

        $this->validator->validate('Aa1$aaa', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ rules }}', '10 caractères')
            ->assertRaised();
    }

    public function testPasswordMissingUppercase(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate('aa1$aaaa', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ rules }}', 'une majuscule')
            ->assertRaised();
    }

    public function testPasswordMissingLowercase(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate('AA1$AAAA', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ rules }}', 'une minuscule')
            ->assertRaised();
    }

    public function testPasswordMissingDigit(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate('Aa$aaaaa', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ rules }}', 'un chiffre')
            ->assertRaised();
    }

    public function testPasswordMissingSpecialChar(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate('Aa1aaaaa', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ rules }}', 'un caractère spécial')
            ->assertRaised();
    }

    public function testPasswordMultipleErrors(): void
    {
        $constraint = new PasswordStrength(minLength: 10);

        $this->validator->validate('aaaaaaa', $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ rules }}', '10 caractères, une majuscule, un chiffre, un caractère spécial')
            ->assertRaised();
    }

    public function testEmptyValueDoesNotRaiseViolation(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testNonStringValueDoesNotRaiseViolation(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate(12345678, $constraint);

        $this->assertNoViolation();
    }
}
