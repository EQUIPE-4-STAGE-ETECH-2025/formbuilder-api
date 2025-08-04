<?php

namespace App\Service;

use App\Dto\BlackListedTokenDto;
use App\Entity\BlackListedToken;
use App\Repository\BlackListedTokenRepository;

class BlackListedTokenService
{
    public function __construct(

        private readonly BlackListedTokenRepository $repository,
    ) {
    }

    public function blacklist(BlackListedTokenDto $dto): void
    {
        $token = (new BlackListedToken())
            ->setToken($dto->token)
            ->setExpiresAt($dto->expiresAt);

        $this->repository->save($token);
    }

    public function isBlacklisted(string $token): bool
    {
        return (bool) $this->repository->findOneBy(['token' => $token]);
    }
}
