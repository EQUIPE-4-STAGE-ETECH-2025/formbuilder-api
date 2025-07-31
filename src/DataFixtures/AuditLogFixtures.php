<?php

namespace App\DataFixtures;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AuditLogFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $auditLogs = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655441101',
                'admin' => '550e8400-e29b-41d4-a716-446655440001',
                'targetUser' => '550e8400-e29b-41d4-a716-446655440004',
                'action' => 'user_suspended',
                'reason' => 'Violation des conditions d\'utilisation',
                'createdAt' => '2024-07-14T10:30:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441102',
                'admin' => '550e8400-e29b-41d4-a716-446655440001',
                'targetUser' => '550e8400-e29b-41d4-a716-446655440004',
                'action' => 'user_deleted',
                'reason' => 'Compte spam détecté',
                'createdAt' => '2024-07-13T16:45:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441103',
                'admin' => '550e8400-e29b-41d4-a716-446655440001',
                'targetUser' => '550e8400-e29b-41d4-a716-446655440002',
                'action' => 'user_created',
                'reason' => 'Inscription utilisateur',
                'createdAt' => '2024-01-15T10:00:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655441104',
                'admin' => '550e8400-e29b-41d4-a716-446655440001',
                'targetUser' => '550e8400-e29b-41d4-a716-446655440003',
                'action' => 'user_created',
                'reason' => 'Inscription utilisateur',
                'createdAt' => '2024-03-20T14:00:00Z',
            ],
        ];

        foreach ($auditLogs as $logData) {
            $auditLog = new AuditLog();
            $auditLog->setId($logData['id']);
            $auditLog->setAdmin($this->getReference($logData['admin'], User::class));
            $auditLog->setTargetUser($this->getReference($logData['targetUser'], User::class));
            $auditLog->setAction($logData['action']);
            $auditLog->setReason($logData['reason']);
            $auditLog->setCreatedAt(new \DateTimeImmutable($logData['createdAt']));
            
            $manager->persist($auditLog);
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