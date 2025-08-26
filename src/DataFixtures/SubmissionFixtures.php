<?php

namespace App\DataFixtures;

use App\Entity\Form;
use App\Entity\Submission;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SubmissionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $submissions = [
            [
                'form' => '550e8400-e29b-41d4-a716-446655440301',
                'data' => [
                    '550e8400-e29b-41d4-a716-446655441003' => 'Jean Dupont',
                    '550e8400-e29b-41d4-a716-446655441004' => 'jean@example.com',
                    '550e8400-e29b-41d4-a716-446655441005' => 'Intéressé par vos services',
                ],
                'submittedAt' => '2024-07-14T10:30:00Z',
                'ipAddress' => '192.168.1.1',
            ],
            [
                'form' => '550e8400-e29b-41d4-a716-446655440301',
                'data' => [
                    '550e8400-e29b-41d4-a716-446655441003' => 'Marie Dubois',
                    '550e8400-e29b-41d4-a716-446655441004' => 'marie@example.com',
                    '550e8400-e29b-41d4-a716-446655441005' => 'Demande de devis',
                ],
                'submittedAt' => '2024-07-13T16:45:00Z',
                'ipAddress' => '192.168.1.2',
            ],
            [
                'form' => '550e8400-e29b-41d4-a716-446655440302',
                'data' => [
                    '550e8400-e29b-41d4-a716-446655441006' => 'Pierre Martin',
                    '550e8400-e29b-41d4-a716-446655441007' => 'pierre@example.com',
                ],
                'submittedAt' => '2024-07-12T14:20:00Z',
                'ipAddress' => '192.168.1.3',
            ],
            [
                'form' => '550e8400-e29b-41d4-a716-446655440302',
                'data' => [
                    '550e8400-e29b-41d4-a716-446655441006' => 'Sophie Bernard',
                    '550e8400-e29b-41d4-a716-446655441007' => 'sophie@example.com',
                ],
                'submittedAt' => '2024-07-11T09:15:00Z',
                'ipAddress' => '192.168.1.4',
            ],
            [
                'form' => '550e8400-e29b-41d4-a716-446655440304',
                'data' => [
                    '550e8400-e29b-41d4-a716-446655441010' => 'TechCorp',
                    '550e8400-e29b-41d4-a716-446655441011' => 'contact@techcorp.com',
                    '550e8400-e29b-41d4-a716-446655441012' => '1000€ - 5000€',
                    '550e8400-e29b-41d4-a716-446655441013' => 'Développement d\'une application web moderne avec React et Node.js',
                ],
                'submittedAt' => '2024-07-10T11:30:00Z',
                'ipAddress' => '192.168.1.5',
            ],
            [
                'form' => '550e8400-e29b-41d4-a716-446655440304',
                'data' => [
                    '550e8400-e29b-41d4-a716-446655441010' => 'StartupXYZ',
                    '550e8400-e29b-41d4-a716-446655441011' => 'hello@startupxyz.com',
                    '550e8400-e29b-41d4-a716-446655441012' => '5000€ - 10000€',
                    '550e8400-e29b-41d4-a716-446655441013' => 'Création d\'une plateforme SaaS complète avec paiements et analytics',
                ],
                'submittedAt' => '2024-07-09T16:45:00Z',
                'ipAddress' => '192.168.1.6',
            ],
        ];

        foreach ($submissions as $submissionData) {
            $submission = new Submission();
            $submission->setForm($this->getReference($submissionData['form'], Form::class));
            $submission->setData($submissionData['data']);
            $submission->setSubmittedAt(new \DateTimeImmutable($submissionData['submittedAt']));
            $submission->setIpAddress($submissionData['ipAddress']);

            $manager->persist($submission);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            FormFixtures::class,
        ];
    }
}
