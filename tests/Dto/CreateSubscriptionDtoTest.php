<?php

namespace App\Tests\Dto;

use App\Dto\CreateSubscriptionDto;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateSubscriptionDtoTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testValidDto(): void
    {
        $dto = new CreateSubscriptionDto([
            'planId' => '550e8400-e29b-41d4-a716-446655440201',
            'userEmail' => 'user@example.com'
        ]);

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testInvalidEmail(): void
    {
        $dto = new CreateSubscriptionDto([
            'planId' => '550e8400-e29b-41d4-a716-446655440201',
            'userEmail' => 'invalid-email'
        ]);

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($errors));
    }

    public function testBlankPlanId(): void
    {
        $dto = new CreateSubscriptionDto([
            'planId' => '',
            'userEmail' => 'user@example.com'
        ]);

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, count($errors));
    }
}
