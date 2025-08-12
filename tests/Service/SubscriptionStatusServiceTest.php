<?php

namespace App\Tests\Service;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionStatusService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubscriptionStatusServiceTest extends TestCase
{
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionStatusService $service;

    protected function setUp(): void
    {
        // Création du mock du repository
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        // Injection du mock dans le service testé
        $this->service = new SubscriptionStatusService($this->subscriptionRepository);
    }

    public function testGetStatusReturnsActive()
    {
        $subscription = (new Subscription())->setStatus(Subscription::STATUS_ACTIVE);

        $this->subscriptionRepository
            ->method('find')
            ->with('123')
            ->willReturn($subscription);

        $status = $this->service->getStatus('123');

        $this->assertSame('ACTIVE', $status);
    }

    public function testGetStatusReturnsCancelled()
    {
        $subscription = (new Subscription())->setStatus(Subscription::STATUS_CANCELLED);

        $this->subscriptionRepository
            ->method('find')
            ->with('123')
            ->willReturn($subscription);

        $status = $this->service->getStatus('123');

        $this->assertSame('CANCELLED', $status);
    }

    public function testGetStatusThrowsNotFound()
    {
        $this->subscriptionRepository
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->service->getStatus('not-found');
    }

    public function testUpdateStatusToActive()
    {
        $subscription = (new Subscription())->setStatus(Subscription::STATUS_CANCELLED);

        $this->subscriptionRepository
            ->method('find')
            ->willReturn($subscription);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($subscription, true);

        $updated = $this->service->updateStatus('123', 'ACTIVE');

        $this->assertTrue($updated->isActive());
    }

    public function testUpdateStatusToCancelled()
    {
        $subscription = (new Subscription())->setStatus(Subscription::STATUS_ACTIVE);

        $this->subscriptionRepository
            ->method('find')
            ->willReturn($subscription);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method('save')
            ->with($subscription, true);

        $updated = $this->service->updateStatus('123', 'CANCELLED');

        $this->assertFalse($updated->isActive());
    }

    public function testUpdateStatusThrowsInvalidArgument()
    {
        $subscription = (new Subscription())->setStatus(Subscription::STATUS_ACTIVE);

        $this->subscriptionRepository
            ->method('find')
            ->willReturn($subscription);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus('123', 'INVALID_STATUS');
    }
}
