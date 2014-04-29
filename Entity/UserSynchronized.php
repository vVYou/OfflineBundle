<?php

namespace Claroline\OfflineBundle\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints as DoctrineAssert;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
//use Doctrine\Common\Collections\ArrayCollection;
use Claroline\CoreBundle\Entity\AbstractRoleSubject;
use Claroline\CoreBundle\Entity\User;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="Claroline\OfflineBundle\Repository\UserSynchronizedRepository")
 * @ORM\Table(name="claro_user_sync")
 * @DoctrineAssert\UniqueEntity("user")
 */
class UserSynchronized extends AbstractRoleSubject
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     *
     * @ORM\OneToOne(
        targetEntity="Claroline\CoreBundle\Entity\User",
        cascade={"persist"}
       )
     * @ORM\JoinColumn(nullable=false, unique=true, onDelete="CASCADE")
     */
    protected $user;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_synchronization", type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    protected $lastSynchronization;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="sent_time", type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    protected $sentTime;


    public function __construct(User $user)
    {
        $this-> user = $user;
        parent::__construct();  //TODO DELETE if remove extends AbstractRoleSubject
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
    public function getUser()
    {
        return $this->user;
    }
    
    /**
     * @return \DateTime
     */
    public function getLastSynchronization()
    {
        return $this->lastSynchronization;
    }
    
    /**
     *
     * @param \DateTime $date
     */
    public function setLastSynchronization(\DateTime $date)
    {
        $this->lastSynchronization = $date;
    }    
    
    /**
     * @return \DateTime
     */
    public function getSentTime()
    {
        return $this->sentTime;
    }
    
    /**
     *
     * @param \DateTime $date
     */
    public function setSentTime(\DateTime $date)
    {
        $this->sentTime = $date;
    }
}
