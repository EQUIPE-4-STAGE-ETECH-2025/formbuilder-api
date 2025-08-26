<?php

namespace App\DataFixtures;

use App\Entity\QuotaStatus;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class QuotaStatusFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $quotaStatuses = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440701',
                'user' => '550e8400-e29b-41d4-a716-446655440002',
                'month' => '2024-07',
                'formCount' => 8,
                'submissionCount' => 50,
                'storageUsedMb' => 25,
                'notified80' => false,
                'notified100' => false,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440702',
                'user' => '550e8400-e29b-41d4-a716-446655440003',
                'month' => '2024-07',
                'formCount' => 2,
                'submissionCount' => 45,
                'storageUsedMb' => 5,
                'notified80' => false,
                'notified100' => false,
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440703',
                'user' => '550e8400-e29b-41d4-a716-446655440004',
                'month' => '2024-07',
                'formCount' => 25,
                'submissionCount' => 8900,
                'storageUsedMb' => 150,
                'notified80' => true,
                'notified100' => false,
            ],
        ];

        foreach ($quotaStatuses as $quotaData) {
            $quotaStatus = new QuotaStatus();
            $quotaStatus->setId($quotaData['id']);
            $quotaStatus->setUser($this->getReference($quotaData['user'], User::class));
            $quotaStatus->setMonth(new \DateTime($quotaData['month'].'-01'));
            $quotaStatus->setFormCount($quotaData['formCount']);
            $quotaStatus->setSubmissionCount($quotaData['submissionCount']);
            $quotaStatus->setStorageUsedMb($quotaData['storageUsedMb']);
            $quotaStatus->setNotified80($quotaData['notified80']);
            $quotaStatus->setNotified100($quotaData['notified100']);

            $manager->persist($quotaStatus);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
