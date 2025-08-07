<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserVoter;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class UserVoterTest extends TestCase
{
    private UserVoter $voter;
    private $token;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->voter = new UserVoter();
        $this->token = $this->createMock(TokenInterface::class);
    }

    public function testVoteReturnsGrantedForAdmin(): void
    {
        $adminUser = new User();
        $adminUser->setRole('ADMIN');

        $this->token->method('getUser')->willReturn($adminUser);

        $targetUser = new User();

        $result = $this->voter->vote($this->token, $targetUser, [UserVoter::VIEW_ROLE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);

        $result = $this->voter->vote($this->token, $targetUser, [UserVoter::EDIT_ROLE]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testVoteReturnsDeniedForUser(): void
    {
        $normalUser = new User();
        $normalUser->setRole('USER');

        $this->token->method('getUser')->willReturn($normalUser);

        $targetUser = new User();

        $result = $this->voter->vote($this->token, $targetUser, [UserVoter::VIEW_ROLE]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);

        $result = $this->voter->vote($this->token, $targetUser, [UserVoter::EDIT_ROLE]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testVoteReturnsAbstainForUnsupportedAttribute(): void
    {
        $adminUser = new User();
        $adminUser->setRole('ADMIN');

        $this->token->method('getUser')->willReturn($adminUser);

        $targetUser = new User();

        $result = $this->voter->vote($this->token, $targetUser, ['UNSUPPORTED']);
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testVoteReturnsAbstainForUnsupportedSubject(): void
    {
        $adminUser = new User();
        $adminUser->setRole('ADMIN');

        $this->token->method('getUser')->willReturn($adminUser);

        $notAUser = new stdClass();

        $result = $this->voter->vote($this->token, $notAUser, [UserVoter::VIEW_ROLE]);
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testVoteReturnsDeniedIfUserIsNull(): void
    {
        $this->token->method('getUser')->willReturn(null);

        $targetUser = new User();

        $result = $this->voter->vote($this->token, $targetUser, [UserVoter::VIEW_ROLE]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }
}
