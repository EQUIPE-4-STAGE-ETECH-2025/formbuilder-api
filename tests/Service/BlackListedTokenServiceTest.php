<?php

namespace App\Tests\Service;

use App\Dto\BlackListedTokenDto;
use App\Entity\BlackListedToken;
use App\Repository\BlackListedTokenRepository;
use App\Service\BlackListedTokenService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class BlackListedTokenServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testBlacklistPersistsToken(): void
    {
        $dto = new BlackListedTokenDto('token123', new DateTimeImmutable('+1 hour'));
        $repository = $this->createMock(BlackListedTokenRepository::class);
        $repository->expects($this->once())->method('save')->with($this->isInstanceOf(BlackListedToken::class));

        $service = new BlackListedTokenService($repository);
        $service->blacklist($dto);
    }

    /**
     * @throws Exception
     */
    public function testIsBlacklistedReturnsTrueIfFound(): void
    {
        $repository = $this->createMock(BlackListedTokenRepository::class);
        $repository->method('findOneBy')->with(['token' => 'abc'])->willReturn(new BlackListedToken());

        $service = new BlackListedTokenService($repository);
        $this->assertTrue($service->isBlacklisted('abc'));
    }

    /**
     * @throws Exception
     */
    public function testIsBlacklistedReturnsFalseIfNotFound(): void
    {
        $repository = $this->createMock(BlackListedTokenRepository::class);
        $repository->method('findOneBy')->with(['token' => 'xyz'])->willReturn(null);

        $service = new BlackListedTokenService($repository);
        $this->assertFalse($service->isBlacklisted('xyz'));
    }
}
