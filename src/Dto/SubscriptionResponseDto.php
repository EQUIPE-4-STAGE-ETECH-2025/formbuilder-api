<?php

namespace App\Dto;

use App\Entity\Subscription;

class SubscriptionResponseDto
{
    public string $id;
    public string $userId;
    public string $planName;
    public string $stripeSubscriptionId;
    public string $startDate;
    public string $endDate;
    public string $status;
    public bool $isActive;

    public function __construct(Subscription $subscription)
    {
        $this->id = $subscription->getId() ?? '';
        $this->userId = $subscription->getUser()?->getId() ?? '';
        $this->planName = $subscription->getPlan()?->getName() ?? '';
        $this->stripeSubscriptionId = $subscription->getStripeSubscriptionId() ?? '';
        $this->startDate = $subscription->getStartDate()?->format('Y-m-d') ?? '';
        $this->endDate = $subscription->getEndDate()?->format('Y-m-d') ?? '';
        $this->status = $subscription->getStatus();
        $this->isActive = $subscription->isActive();
    }

    /**
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'planName' => $this->planName,
            'stripeSubscriptionId' => $this->stripeSubscriptionId,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'status' => $this->status,
            'isActive' => $this->isActive,
        ];
    }
}
