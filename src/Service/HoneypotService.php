<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class HoneypotService
{
    private const HONEYPOT_FIELD_NAME = '_website_url';
    private const HONEYPOT_HEADER = 'X-Honeypot-Field';
    private const MIN_SUBMISSION_TIME = 2; // secondes minimum pour remplir un formulaire

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Vérifie si la soumission semble provenir d'un bot
     */
    public function isBot(Request $request): bool
    {
        // Vérification 1 : Champ honeypot (piège invisible pour les bots)
        if ($this->hasHoneypotFieldFilled($request)) {
            $this->logger->warning('Soumission bloquée - Champ honeypot rempli (bot détecté)', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            return true;
        }

        // Vérification 2 : Temps de soumission trop rapide
        if ($this->isSubmissionTooFast($request)) {
            $this->logger->warning('Soumission bloquée - Soumission trop rapide (bot suspect)', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            return true;
        }

        // Vérification 3 : User-Agent suspect ou absent
        if ($this->hasSuspiciousUserAgent($request)) {
            $this->logger->warning('Soumission bloquée - User-Agent suspect', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Vérifie si le champ honeypot a été rempli (signe de bot)
     */
    private function hasHoneypotFieldFilled(Request $request): bool
    {
        $data = json_decode($request->getContent(), true);

        if (! is_array($data)) {
            return false;
        }

        // Le champ honeypot ne doit PAS être rempli
        return isset($data[self::HONEYPOT_FIELD_NAME]) && ! empty($data[self::HONEYPOT_FIELD_NAME]);
    }

    /**
     * Vérifie si la soumission est trop rapide (moins de 2 secondes)
     */
    private function isSubmissionTooFast(Request $request): bool
    {
        $submissionTime = $request->headers->get(self::HONEYPOT_HEADER);

        if ($submissionTime === null || ! is_numeric($submissionTime)) {
            // Si le header n'est pas présent, on ne bloque pas
            return false;
        }

        $clientTime = (int) $submissionTime;
        $serverTime = time();

        // Tolérance de 30 secondes pour la désynchronisation d'horloge
        $timeDifference = abs($serverTime - $clientTime);
        if ($timeDifference > 30) {
            // Log pour monitoring en production
            $this->logger->warning('Désynchronisation d\'horloge détectée', [
                'client_time' => $clientTime,
                'server_time' => $serverTime,
                'difference' => $timeDifference,
                'ip' => $request->getClientIp(),
            ]);

            // Si la différence est trop importante, utiliser le timestamp serveur
            // et considérer que c'est une soumission légitime
            return false;
        }

        $elapsedTime = $serverTime - $clientTime;

        // Si le temps écoulé est inférieur au minimum requis, c'est suspect
        return $elapsedTime < self::MIN_SUBMISSION_TIME;
    }

    /**
     * Vérifie si le User-Agent est suspect
     */
    private function hasSuspiciousUserAgent(Request $request): bool
    {
        $userAgent = $request->headers->get('User-Agent');

        if ($userAgent === null || trim($userAgent) === '') {
            return true; // Pas de User-Agent = suspect
        }

        // Liste de patterns suspects (bots connus)
        $suspiciousPatterns = [
            'bot',
            'crawl',
            'spider',
            'scraper',
            'curl',
            'wget',
            'python',
            'ruby',
            'java',
            'perl',
            'phantom',
            'headless',
        ];

        $userAgentLower = strtolower($userAgent);

        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retourne le nom du champ honeypot pour le frontend
     */
    public function getHoneypotFieldName(): string
    {
        return self::HONEYPOT_FIELD_NAME;
    }

    /**
     * Retourne le nom du header pour le timestamp
     */
    public function getHoneypotHeader(): string
    {
        return self::HONEYPOT_HEADER;
    }
}
