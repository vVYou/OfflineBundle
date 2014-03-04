<?php


namespace Claroline\OfflineBundle\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints as DoctrineAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
//use Doctrine\Common\Collections\ArrayCollection;
use Claroline\CoreBundle\Entity\AbstractRoleSubject;
use Claroline\CoreBundle\Entity\User;

/**
 * @ORM\Entity(repositoryClass="Claroline\OfflineBundle\Repository\UserSynchronizedRepository")
 * @ORM\Table(name="claro_user_sync")
 * @DoctrineAssert\UniqueEntity("userId")
 */
class UserSynchronized extends AbstractRoleSubject implements Serializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\OneToOne(
     *     targetEntity="Claroline\CoreBundle\Entity\User",
     *     cascade={"persist"},
     *     mappedBy="userSynchronized"
     * )
     */
    protected $userId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="creation_date", type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    protected $lastSynchronization;

    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * @return User
     */
    public function getUserId()
    {
        return $this->userId;
    }
    
    /**
     * @return \DateTime
     */
    public function getLastSynchronization()
    {
        $this->lastSynchronization;
    }
    
    /**
     *
     * @param \DateTime $date
     */
    public function setLastSynchronization(\DateTime $date)
    {
        $this-> lastSynchronization = $date;
    }
}
