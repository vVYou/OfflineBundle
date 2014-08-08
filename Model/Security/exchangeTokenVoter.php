<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Model\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * This voter grants access to admin users, whenever the attribute or the
 * class is. This means that administrators are seen by the AccessDecisionManager
 * as if they have all the possible roles and permissions on every object or class.
 *
 * @DI\Service
 * @DI\Tag("security.voter")
 */
class exchangeTokenVoter implements VoterInterface
{
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        // return $this->isConnectedByToken($token) ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_ABSTAIN;
    }

    protected function isConnectedByToken(TokenInterface $token)
    {
        // if ($token->getCredentials() == $user->getExchangeToken()) {
            // return true;
        // }

        $vote = $token instanceof UserExchangeToken ? true: false;

        return $vote;
    }

    public function supportsAttribute($attribute)
    {
        return true;
    }

    public function supportsClass($class)
    {
        return true;
    }
}
