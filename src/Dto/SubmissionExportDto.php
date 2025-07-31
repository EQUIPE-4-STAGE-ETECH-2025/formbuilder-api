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
        $this->id = $submission->getId() ?? '';
        $this->formId = $submission->getForm()?->getId() ?? '';
        $this->submittedAt = $submission->getSubmittedAt()?->format('Y-m-d H:i:s') ?? '';
        $this->ipAddress = $submission->getIpAddress() ?? '';
        $this->data = $submission->getData();
    }

    /**
     * Retourne une ligne CSV à partir des champs fixes + dynamiques.
     * L'ordre des colonnes dynamiques est à gérer dans l’export global.
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

        foreach ($fieldOrder as $field) {
            $value = $this->data[$field] ?? '';
            // on nettoie les sauts de ligne, tabulations, etc.
            $row[] = is_scalar($value) ? str_replace(["\n", "\r", "\t"], ' ', (string) $value) : json_encode($value);
        }

        return $row;
    }
}
