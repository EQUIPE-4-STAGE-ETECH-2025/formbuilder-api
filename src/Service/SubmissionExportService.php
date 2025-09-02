<?php

namespace App\Service;

use App\Dto\SubmissionExportDto;
use App\Entity\Form;
use App\Entity\User;
use App\Repository\SubmissionRepository;

class SubmissionExportService
{
    public function __construct(
        private SubmissionRepository $submissionRepository
    ) {
    }

    /**
     * Exporte les soumissions d’un formulaire en CSV.
     */
    public function exportFormSubmissionsToCsv(Form $form, User $currentUser, ?int $limit = null, ?int $offset = null): string
    {
        // Vérifie que l'utilisateur est propriétaire
        $formUser = $form->getUser();
        if ($formUser === null || $formUser->getId() !== $currentUser->getId()) {
            throw new \LogicException("Accès interdit : ce formulaire ne vous appartient pas.");
        }

        $submissions = $this->submissionRepository->findBy(
            ['form' => $form],
            ['submittedAt' => 'DESC'],
            $limit,
            $offset
        );

        // Détermine l'ordre des champs dynamiques
        $fieldOrder = [];
        if (! empty($submissions)) {
            $firstData = $submissions[0]->getData();
            $fieldOrder = array_keys($firstData);
        }

        // En-têtes CSV : champs fixes + dynamiques
        $headers = ['ID', 'Form ID', 'Submitted At', 'IP Address', ...$fieldOrder];

        $rows = [];
        $rows[] = $headers; // <-- la première ligne = en-têtes, pas de ligne vide avant

        foreach ($submissions as $submission) {
            $dto = new SubmissionExportDto($submission);
            $rows[] = $dto->toCsvRow($fieldOrder);
        }

        // Génère le CSV en concaténant correctement les lignes
        $csvContent = implode("\n", array_map(
            fn ($row) => implode(';', array_map(fn ($v) => $this->escapeCsvValue((string)$v), $row)),
            $rows
        ));

        return $csvContent;
    }

    private function escapeCsvValue(string $value): string
    {
        $escaped = str_replace('"', '""', $value);

        return str_contains($escaped, ';') || str_contains($escaped, '"') || str_contains($escaped, "\n")
            ? "\"$escaped\""
            : $escaped;
    }
}
