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
//use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
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
  
    /**
     * Returns the workspace with the given guid
     * @return Workspace
     */  
    public function findByGuid($guid)
    {
        $dql = '
            SELECT w
            FROM Claroline\CoreBundle\Entity\Workspace\Workspace w
            WHERE w.guid = :guid
        ';

        $query = $this->_em->createQuery($dql);
        $query->setParameter('guid', $guid);

        return $query->getResult();
    }

    /**
     * Returns the workspace with the given code
     * @return Workspace
     */  
    public function findByCode($code)
    {
        $dql = '
            SELECT w
            FROM Claroline\CoreBundle\Entity\Workspace\Workspace w
            WHERE w.code = :code
        ';

        $query = $this->_em->createQuery($dql);
        $query->setParameter('code', $code);

        return $query->getResult();
    }
    
    /**
     * Returns the user with the given id
     * @return User
     */    
    public function findById($id)
    {
        $dql = '
            SELECT u
            FROM Claroline\CoreBundle\Entity\User u
            WHERE u.id = :id
        ';

        $query = $this->_em->createQuery($dql);
        $query->setParameter('id', $id);

        return $query->getResult();
    }
    
    /**
     * Returns the resourceNode that corresponds to the workspace
     * @return ResourceNode
     */   
    public function findResourceNodeByWorkspace($workspace)
    {
        $dql = '
            SELECT r
            FROM Claroline\CoreBundle\Entity\Resource\ResourceNode r
            WHERE r.workspace = :workspace
        ';

        $query = $this->_em->createQuery($dql);
        $query->setParameter('workspace', $workspace);

        return $query->getResult();
    }
    
     /**
     * Returns the resourceNode that corresponds to the hashname
     * @return ResourceNode
     */   
    public function findResourceNodeByHashname($hashname)
    {
        $dql = '
            SELECT r
            FROM Claroline\CoreBundle\Entity\Resource\ResourceNode r
            WHERE r.hashName = :hashname
        ';

        $query = $this->_em->createQuery($dql);
        $query->setParameter('hashname', $hashname);

        return $query->getResult();
    }
}
