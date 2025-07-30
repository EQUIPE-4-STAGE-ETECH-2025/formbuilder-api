<?php

namespace App\Tests\Dto;

use App\Dto\SubmissionResponseDto;
use App\Entity\Form;
use App\Entity\Submission;
use PHPUnit\Framework\TestCase;

class SubmissionResponseDtoTest extends TestCase
{
    public function testToArray(): void
    {
        $form = new Form();
        $formReflection = new \ReflectionClass($form);
        $formIdProperty = $formReflection->getProperty('id');
        $formIdProperty->setAccessible(true);
        $formIdProperty->setValue($form, 'form-uuid-123');

        $submission = new Submission();
        $submissionReflection = new \ReflectionClass($submission);
        $idProperty = $submissionReflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($submission, 'submission-uuid-456');

        $submission->setForm($form);
        $submission->setData(['field1' => 'value1']);
        $submission->setIpAddress('127.0.0.1');
        $now = new \DateTimeImmutable();
        $submission->setSubmittedAt($now);

        $dto = new SubmissionResponseDto($submission);
        $array = $dto->toArray();

        $this->assertSame('submission-uuid-456', $array['id']);
        $this->assertSame('form-uuid-123', $array['form_id']);
        $this->assertSame('127.0.0.1', $array['ip_address']);
        $this->assertSame(['field1' => 'value1'], $array['data']);
        $this->assertSame($now->format(\DateTime::ATOM), $array['submitted_at']);
    }
}
