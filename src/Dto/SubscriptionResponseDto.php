<?php

namespace App\Dto;

use App\Entity\Subscription;

class SubscriptionResponseDto
{
    public string $id;
    public string $planName;
    public string $stripeSubscriptionId;
    public string $startDate;
    public string $endDate;
    public bool $isActive;

    public function __construct(Subscription $subscription)
    {
        $this->id = $subscription->getId();
        $this->planName = $subscription->getPlan()->getName();
        $this->stripeSubscriptionId = $subscription->getStripeSubscriptionId();
        $this->startDate = $subscription->getStartDate()->format('Y-m-d');
        $this->endDate = $subscription->getEndDate()->format('Y-m-d');
        $this->isActive = $subscription->isActive();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'planName' => $this->planName,
            'stripeSubscriptionId' => $this->stripeSubscriptionId,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'isActive' => $this->isActive,
        ];
    }
}
