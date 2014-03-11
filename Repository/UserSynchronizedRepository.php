<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
//use Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\CoreBundle\Entity\User;
//use Claroline\CoreBundle\Entity\Role;

class UserSynchronizedRepository extends EntityRepository
{

    /**
    * Deeper magic goes here.
    * Gets userSynchronize from this pure database
    *
    * @param ResourceInstance $user
    * @return userSynchronized
    */
    public function findUserSynchronized(User $user, $getQuery = false)
    {
        /*
        $dql = "
            SELECT user_sync.lastSynchronization as date
            FROM Claroline\OfflineBundle\Entity\UserSynchronized user_sync
            JOIN user_sync.user user_table
            WHERE user_table.id = :userId
        ";
        $query = $this->_em->createQuery($dql);
        $query->setParameter('userId',  $user->getId());
        
        return ($getQuery) ? $query: $query->getResult();
        */
        
        $qb = $this->createQueryBuilder('userSynchronized');
        $qb->select('userSynchronized')
            ->where('userSynchronized.user = :user_id');       

        return $results = $qb->getQuery()->execute(
            array(
                ':user_id'    => $user->getId()
            )
        );
    }
}
