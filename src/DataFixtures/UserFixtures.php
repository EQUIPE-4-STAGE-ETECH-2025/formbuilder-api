<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Admin
        $admin = new User();
        $admin->setId('550e8400-e29b-41d4-a716-446655440001');
        $admin->setFirstName('Admin');
        $admin->setLastName('System');
        $admin->setEmail('admin@formbuilder.com');
        $admin->setRole('ADMIN');
        $admin->setIsEmailVerified(true);
        $admin->setCreatedAt(new \DateTimeImmutable('2024-01-01T00:00:00Z'));
        $admin->setUpdatedAt(new \DateTimeImmutable('2024-01-01T00:00:00Z'));
        
        // Hash du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'password');
        $admin->setPasswordHash($hashedPassword);
        
        $manager->persist($admin);
        $this->addReference('550e8400-e29b-41d4-a716-446655440001', $admin);

        // User 1
        $user1 = new User();
        $user1->setId('550e8400-e29b-41d4-a716-446655440002');
        $user1->setFirstName('Anna');
        $user1->setLastName('Martin');
        $user1->setEmail('anna@example.com');
        $user1->setRole('USER');
        $user1->setIsEmailVerified(true);
        $user1->setCreatedAt(new \DateTimeImmutable('2024-01-15T10:00:00Z'));
        $user1->setUpdatedAt(new \DateTimeImmutable('2024-01-15T10:00:00Z'));
        
        $hashedPassword = $this->passwordHasher->hashPassword($user1, 'password');
        $user1->setPasswordHash($hashedPassword);
        
        $manager->persist($user1);
        $this->addReference('550e8400-e29b-41d4-a716-446655440002', $user1);

        // User 2
        $user2 = new User();
        $user2->setId('550e8400-e29b-41d4-a716-446655440003');
        $user2->setFirstName('Lucas');
        $user2->setLastName('Dubois');
        $user2->setEmail('lucas@example.com');
        $user2->setRole('USER');
        $user2->setIsEmailVerified(true);
        $user2->setCreatedAt(new \DateTimeImmutable('2024-03-20T14:00:00Z'));
        $user2->setUpdatedAt(new \DateTimeImmutable('2024-03-20T14:00:00Z'));
        
        $hashedPassword = $this->passwordHasher->hashPassword($user2, 'password');
        $user2->setPasswordHash($hashedPassword);
        
        $manager->persist($user2);
        $this->addReference('550e8400-e29b-41d4-a716-446655440003', $user2);

        // User 3
        $user3 = new User();
        $user3->setId('550e8400-e29b-41d4-a716-446655440004');
        $user3->setFirstName('Élodie');
        $user3->setLastName('Rousseau');
        $user3->setEmail('elodie@example.com');
        $user3->setRole('USER');
        $user3->setIsEmailVerified(true);
        $user3->setCreatedAt(new \DateTimeImmutable('2024-02-10T11:30:00Z'));
        $user3->setUpdatedAt(new \DateTimeImmutable('2024-02-10T11:30:00Z'));
        
        $hashedPassword = $this->passwordHasher->hashPassword($user3, 'password');
        $user3->setPasswordHash($hashedPassword);
        
        $manager->persist($user3);
        $this->addReference('550e8400-e29b-41d4-a716-446655440004', $user3);

        $manager->flush();
    }
} 