<?php

namespace App\DataFixtures;

use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class SubscriptionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $subscriptions = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440501',
                'user' => '550e8400-e29b-41d4-a716-446655440002',
                'plan' => '550e8400-e29b-41d4-a716-446655440202',
                'stripeSubscriptionId' => 'sub_premium_123',
                'startDate' => '2024-01-15',
                'endDate' => '2025-01-15',
                'isActive' => true,
                'createdAt' => '2024-01-15T10:00:00Z',
                'updatedAt' => '2024-01-15T10:00:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440502',
                'user' => '550e8400-e29b-41d4-a716-446655440003',
                'plan' => '550e8400-e29b-41d4-a716-446655440201',
                'stripeSubscriptionId' => 'sub_free_456',
                'startDate' => '2024-03-20',
                'endDate' => '2025-03-20',
                'isActive' => true,
                'createdAt' => '2024-03-20T14:00:00Z',
                'updatedAt' => '2024-03-20T14:00:00Z',
            ],
            [
                'id' => '550e8400-e29b-41d4-a716-446655440503',
                'user' => '550e8400-e29b-41d4-a716-446655440004',
                'plan' => '550e8400-e29b-41d4-a716-446655440203',
                'stripeSubscriptionId' => 'sub_pro_789',
                'startDate' => '2024-02-10',
                'endDate' => '2025-02-10',
                'isActive' => false,
                'createdAt' => '2024-02-10T11:30:00Z',
                'updatedAt' => '2024-07-14T10:30:00Z',
            ],
        ];

        foreach ($subscriptions as $subscriptionData) {
            $subscription = new Subscription();
            $subscription->setId($subscriptionData['id']);
            $subscription->setUser($this->getReference($subscriptionData['user'], User::class));
            $subscription->setPlan($this->getReference($subscriptionData['plan'], Plan::class));
            $subscription->setStripeSubscriptionId($subscriptionData['stripeSubscriptionId']);
            $subscription->setStartDate(new \DateTime($subscriptionData['startDate']));
            $subscription->setEndDate(new \DateTime($subscriptionData['endDate']));
            $subscription->setIsActive($subscriptionData['isActive']);
            $subscription->setCreatedAt(new \DateTimeImmutable($subscriptionData['createdAt']));
            $subscription->setUpdatedAt(new \DateTimeImmutable($subscriptionData['updatedAt']));

            $manager->persist($subscription);
            $this->addReference($subscriptionData['id'], $subscription);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            PlanFixtures::class,
        ];
    }
}
