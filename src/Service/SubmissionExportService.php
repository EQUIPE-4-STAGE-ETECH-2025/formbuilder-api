<?php

namespace App\Service;

use App\Entity\Form;
use App\Entity\User;
use App\Repository\SubmissionRepository;
use App\Dto\SubmissionExportDto;

class SubmissionExportService
{
    public function __construct(
        private SubmissionRepository $submissionRepository
    ) {}

    /**
     * Exporte les soumissions d’un formulaire en CSV.
     *
     * @param Form $form Le formulaire à exporter
     * @param User $currentUser Utilisateur actuellement connecté (sécurité)
     * @param int|null $limit Nombre max de lignes (pagination)
     * @param int|null $offset Décalage des lignes (pagination)
     *
     * @return string CSV prêt à être streamé ou renvoyé
     */
    public function exportFormSubmissionsToCsv(Form $form, User $currentUser, ?int $limit = null, ?int $offset = null): string
    {
        // Sécurisation : l'utilisateur doit être le propriétaire du formulaire
        if ($form->getUser()->getId() !== $currentUser->getId()) {
            throw new \LogicException("Accès interdit : ce formulaire ne vous appartient pas.");
        }

        // Récupération des soumissions avec pagination
        $criteria = ['form' => $form];
        $submissions = $this->submissionRepository->findBy($criteria, ['submittedAt' => 'DESC'], $limit, $offset);

        if (count($submissions) === 0) {
            return ''; // ou on peut renvoyer un CSV vide avec en-têtes
        }

        // Extraction des clés dynamiques (dans l’ordre du 1er enregistrement)
        $firstData = $submissions[0]->getData();
        $fieldOrder = array_keys($firstData);

        // En-têtes CSV : champs fixes + dynamiques
        $headers = ['ID', 'Form ID', 'Submitted At', 'IP Address', ...$fieldOrder];
        $rows = [$headers];

        // Construction des lignes
        foreach ($submissions as $submission) {
            $dto = new SubmissionExportDto($submission);
            $rows[] = $dto->toCsvRow($fieldOrder);
        }

        // Génération du CSV brut avec ; et échappement
        $csvContent = '';
        foreach ($rows as $row) {
            $escaped = array_map([$this, 'escapeCsvValue'], $row);
            $csvContent .= implode(';', $escaped) . "\n";
        }

        return $csvContent;
    }

    /**
     * Échappe les valeurs CSV en gérant les caractères spéciaux.
     */
    private function escapeCsvValue(string $value): string
    {
        $escaped = str_replace('"', '""', $value);
        return str_contains($escaped, ';') || str_contains($escaped, '"') || str_contains($escaped, "\n")
            ? "\"$escaped\""
            : $escaped;
    }
}
