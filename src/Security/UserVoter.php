<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, User>
 */
class UserVoter extends Voter
{
    public const VIEW_ROLE = 'USER_VIEW_ROLE';
    public const EDIT_ROLE = 'USER_EDIT_ROLE';
    public const VIEW_PROFILE = 'USER_VIEW_PROFILE';
    public const EDIT_PROFILE = 'USER_EDIT_PROFILE';
    public const DELETE = 'USER_DELETE';
    public const VIEW_ALL = 'USER_VIEW_ALL';
    protected function supports(string $attribute, mixed $subject): bool
    {
        $permissions = [
            self::VIEW_ROLE,
            self::EDIT_ROLE,
            self::VIEW_PROFILE,
            self::EDIT_PROFILE,
            self::DELETE,
            self::VIEW_ALL,
        ];
        if (! in_array($attribute, $permissions, true)) {
            return false;
        }

        if ($attribute === self::VIEW_ALL) {
            return true;
        }

        return $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (! $currentUser instanceof User) {
            return false;
        }

        if ('ADMIN' === $currentUser->getRole()) {
            return true;
        }

        return match ($attribute) {
            self::VIEW_PROFILE, self::EDIT_PROFILE, self::DELETE => $currentUser->getId() === $subject->getId(),
            self::VIEW_ALL => false,
            default => false,
        };
    }
}