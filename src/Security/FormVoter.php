<?php

namespace App\Security;

use App\Entity\Form;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FormVoter extends Voter
{
    public const VIEW_SUBMISSIONS = 'FORM_VIEW_SUBMISSIONS';
    public const EXPORT_SUBMISSIONS = 'FORM_EXPORT_SUBMISSIONS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW_SUBMISSIONS, self::EXPORT_SUBMISSIONS])
            && $subject instanceof Form;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Form $form */
        $form = $subject;

        // Seul le propriétaire peut voir ou exporter les soumissions
        return $form->getOwner()?->getId() === $user->getId();
    }
}
