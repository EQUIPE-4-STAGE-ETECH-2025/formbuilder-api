<?php

namespace App\Dto;

use App\Entity\Submission;

class SubmissionExportDto
{
    private string $id;
    private string $formId;
    private string $submittedAt;
    private string $ipAddress;

    /** @var array<string, mixed> */
    private array $data;

    public function __construct(Submission $submission)
    {
        $this->id = (string) $submission->getId();
        $this->formId = (string) ($submission->getForm()?->getId() ?? '');
        $this->submittedAt = $submission->getSubmittedAt() !== null ? $submission->getSubmittedAt()->format('Y-m-d H:i:s') : '';
        $this->ipAddress = $submission->getIpAddress() ?? '';
        $this->data = $submission->getData();
    }

    /**
     * Retourne une ligne CSV à partir des champs fixes + dynamiques.
     *
     * @param string[] $fieldOrder Ordre des clés dynamiques à afficher
     * @return string[]
     */
    public function toCsvRow(array $fieldOrder): array
    {
        $row = [
            $this->id,
            $this->formId,
            $this->submittedAt,
            $this->ipAddress,
        ];

        foreach ($fieldOrder as $fieldName) {
            $value = $this->data[$fieldName] ?? '';
            $value = str_replace("\n", ' ', $value); // supprime les sauts de ligne
            $row[] = (string) $value;
        }

        return $row;
    }
}
