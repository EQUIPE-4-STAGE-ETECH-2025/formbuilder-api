<?php

namespace App\Security;

use App\Entity\Form;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Form>
 */
class FormVoter extends Voter
{
    public const OWNER = 'OWNER';
    public const VIEW_SUBMISSIONS = 'VIEW_SUBMISSIONS';
    public const EXPORT_SUBMISSIONS = 'EXPORT_SUBMISSIONS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::OWNER,
            self::VIEW_SUBMISSIONS,
            self::EXPORT_SUBMISSIONS,
        ]) && $subject instanceof Form;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (! $user instanceof User) {
            return false;
        }

        /** @var Form $subject */
        return $user === $subject->getUser();
    }
}
