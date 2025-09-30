<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimitService
{
    public function __construct(
        private RateLimiterFactory $formSubmissionLimiter,
        private RateLimiterFactory $formSubmissionStrictLimiter,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Vérifie si une IP peut soumettre un formulaire
     * Utilise un système à deux niveaux :
     * - Niveau 1 : 10 soumissions par heure (sliding window)
     * - Niveau 2 : 100 soumissions par jour (fixed window)
     */
    public function canSubmitForm(Request $request): bool
    {
        $ip = $this->getClientIp($request);
        
        // Vérifier le rate limit horaire (10/heure)
        $hourlyLimiter = $this->formSubmissionLimiter->create($ip);
        $hourlyLimit = $hourlyLimiter->consume(1);
        
        if (! $hourlyLimit->isAccepted()) {
            $this->logger->warning('Rate limit horaire dépassé', [
                'ip' => $ip,
                'retry_after' => $hourlyLimit->getRetryAfter()?->getTimestamp(),
            ]);
            
            return false;
        }
        
        // Vérifier le rate limit journalier (100/jour)
        $dailyLimiter = $this->formSubmissionStrictLimiter->create($ip);
        $dailyLimit = $dailyLimiter->consume(1);
        
        if (! $dailyLimit->isAccepted()) {
            $this->logger->error('Rate limit journalier dépassé - Possible attaque', [
                'ip' => $ip,
                'retry_after' => $dailyLimit->getRetryAfter()?->getTimestamp(),
            ]);
            
            return false;
        }
        
        return true;
    }

    /**
     * Récupère les informations de rate limit pour une IP
     * @return array<string, mixed>
     */
    public function getRateLimitInfo(Request $request): array
    {
        $ip = $this->getClientIp($request);
        
        $hourlyLimiter = $this->formSubmissionLimiter->create($ip);
        $hourlyLimit = $hourlyLimiter->consume(0); // 0 pour ne pas consommer
        
        $dailyLimiter = $this->formSubmissionStrictLimiter->create($ip);
        $dailyLimit = $dailyLimiter->consume(0);
        
        return [
            'hourly' => [
                'limit' => $hourlyLimit->getLimit(),
                'remaining' => $hourlyLimit->getRemainingTokens(),
                'retry_after' => $hourlyLimit->getRetryAfter()?->getTimestamp(),
            ],
            'daily' => [
                'limit' => $dailyLimit->getLimit(),
                'remaining' => $dailyLimit->getRemainingTokens(),
                'retry_after' => $dailyLimit->getRetryAfter()?->getTimestamp(),
            ],
        ];
    }

    /**
     * Récupère l'IP du client de manière sécurisée
     */
    private function getClientIp(Request $request): string
    {
        $ip = $request->getClientIp();
        
        if ($ip === null) {
            $this->logger->warning('Impossible de récupérer l\'IP du client');
            
            return 'unknown';
        }
        
        return $ip;
    }
}
