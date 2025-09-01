<?php

namespace App\Tests\Dto;

use App\Dto\SubmitFormDto;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SubmitFormDtoTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    public function testValidData(): void
    {
        $dto = new SubmitFormDto(['field1' => 'value1']);
        $errors = $this->validator->validate($dto);

        $this->assertCount(0, $errors, 'Aucune erreur de validation attendue pour des données valides.');
    }

    public function testEmptyData(): void
    {
        $dto = new SubmitFormDto([]);
        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors), 'Des erreurs de validation sont attendues pour des données vides.');
    }
}
