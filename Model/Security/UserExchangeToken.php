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


class UserExchangeToken extends UsernamePasswordToken
{

    public function __construct($user, $exchangeToken)
    {
        $providerKey = 'main';
        parent::__construct($user, $exchangeToken, $providerKey, $user->getRoles());
    }

// $token = new UsernamePasswordToken($user, $exchangeToken, $providerKey, $user->getRoles());
}