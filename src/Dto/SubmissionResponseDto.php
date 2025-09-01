<?php

namespace App\Dto;

use App\Entity\Submission;

class SubmissionResponseDto
{
    private string $id;
    private string $formId;
    private \DateTimeImmutable $submittedAt;
    private string $ipAddress;

    /** @var array<string, mixed> */
    private array $data;

    public function __construct(Submission $submission)
    {
        $this->id = $submission->getId() ?? '';
        $this->formId = $submission->getForm()?->getId() ?? '';
        $this->submittedAt = $submission->getSubmittedAt() ?? new \DateTimeImmutable();
        $this->ipAddress = $submission->getIpAddress() ?? '';
        $this->data = $submission->getData();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFormId(): string
    {
        return $this->formId;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->formId,
            'submitted_at' => $this->submittedAt->format(\DateTime::ATOM),
            'ip_address' => $this->ipAddress,
            'data' => $this->data,
        ];
    }
}
