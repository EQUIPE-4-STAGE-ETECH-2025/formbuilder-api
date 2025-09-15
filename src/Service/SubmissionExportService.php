<?php

namespace App\Service;

use App\Dto\SubmissionExportDto;
use App\Entity\Form;
use App\Entity\User;
use App\Repository\FormVersionRepository;
use App\Repository\SubmissionRepository;

class SubmissionExportService
{
    public function __construct(
        private SubmissionRepository $submissionRepository,
        private FormVersionRepository $formVersionRepository
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

        // Récupère les champs du formulaire avec leurs labels
        $fieldLabelsMap = $this->getFieldLabelsMap($form);

        // Détermine l'ordre des champs dynamiques
        $fieldOrder = [];
        if (! empty($submissions)) {
            $firstData = $submissions[0]->getData();
            $fieldOrder = array_keys($firstData);
        }

        // Convertit les IDs des champs en labels pour les en-têtes
        $fieldLabels = array_map(
            fn ($fieldId) => $fieldLabelsMap[$fieldId] ?? $fieldId,
            $fieldOrder
        );

        // En-têtes CSV : champs fixes + dynamiques avec labels
        $headers = ['ID', 'Form ID', 'Submitted At', 'IP Address', ...$fieldLabels];

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

    /**
     * Crée un mapping entre les IDs des champs et leurs labels
     *
     * @return array<string, string>
     */
    private function getFieldLabelsMap(Form $form): array
    {
        $formVersion = $this->formVersionRepository->findLatestVersionWithFields($form);

        if ($formVersion === null) {
            return [];
        }

        $fieldLabelsMap = [];
        foreach ($formVersion->getFormFields() as $formField) {
            $fieldId = $formField->getId();
            $fieldLabel = $formField->getLabel();

            if ($fieldId !== null && $fieldLabel !== null) {
                $fieldLabelsMap[$fieldId] = $fieldLabel;
            }
        }

        return $fieldLabelsMap;
    }
}
