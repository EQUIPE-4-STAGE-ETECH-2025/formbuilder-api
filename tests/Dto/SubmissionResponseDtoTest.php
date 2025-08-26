<?php

namespace App\Tests\Dto;

use App\Dto\SubmissionResponseDto;
use App\Entity\Form;
use App\Entity\Submission;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class SubmissionResponseDtoTest extends TestCase
{
    public function testToArray(): void
    {
        $form = new Form();
        $formReflection = new \ReflectionClass($form);
        $formIdProperty = $formReflection->getProperty('id');
        $formIdProperty->setAccessible(true);
        $formIdProperty->setValue($form, Uuid::fromString('550e8400-e29b-41d4-a716-446655440003'));

        $submission = new Submission();
        $submissionReflection = new \ReflectionClass($submission);
        $idProperty = $submissionReflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($submission, Uuid::fromString('550e8400-e29b-41d4-a716-446655440004'));

        $submission->setForm($form);
        $submission->setData(['field1' => 'value1']);
        $submission->setIpAddress('127.0.0.1');
        $now = new \DateTimeImmutable();
        $submission->setSubmittedAt($now);

        $dto = new SubmissionResponseDto($submission);
        $array = $dto->toArray();

        $this->assertSame('550e8400-e29b-41d4-a716-446655440004', $array['id']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440003', $array['form_id']);
        $this->assertSame('127.0.0.1', $array['ip_address']);
        $this->assertSame(['field1' => 'value1'], $array['data']);
        $this->assertSame($now->format(\DateTime::ATOM), $array['submitted_at']);
    }
}
