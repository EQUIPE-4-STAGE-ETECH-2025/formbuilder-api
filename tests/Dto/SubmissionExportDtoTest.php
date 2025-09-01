<?php

namespace App\Tests\Dto;

use App\Dto\SubmissionExportDto;
use App\Entity\Form;
use App\Entity\Submission;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class SubmissionExportDtoTest extends TestCase
{
    public function testToCsvRowReturnsCorrectFields(): void
    {
        $form = new Form();
        $formReflection = new \ReflectionClass($form);
        $formIdProp = $formReflection->getProperty('id');
        $formIdProp->setAccessible(true);
        $formIdProp->setValue($form, Uuid::fromString('550e8400-e29b-41d4-a716-446655440001'));

        $submission = new Submission();
        $submissionReflection = new \ReflectionClass($submission);
        $subIdProp = $submissionReflection->getProperty('id');
        $subIdProp->setAccessible(true);
        $subIdProp->setValue($submission, Uuid::fromString('550e8400-e29b-41d4-a716-446655440002'));

        $submission->setForm($form);
        $submission->setIpAddress('192.168.0.1');
        $submission->setSubmittedAt(new \DateTimeImmutable('2025-07-30 12:00:00'));
        $submission->setData([
            'name' => 'Alice',
            'email' => "alice@example.com",
            'message' => "Hello\nNew line"
        ]);

        $dto = new SubmissionExportDto($submission);
        $row = $dto->toCsvRow(['name', 'email', 'message']);

        $expected = [
            '550e8400-e29b-41d4-a716-446655440002', // submission id
            '550e8400-e29b-41d4-a716-446655440001', // form id
            '2025-07-30 12:00:00',
            '192.168.0.1',
            'Alice',
            'alice@example.com',
            'Hello New line' // saut de ligne supprimé
        ];

        $this->assertSame($expected, $row);
    }
}
