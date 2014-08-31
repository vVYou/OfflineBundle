<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\OfflineBundle\Manager;

use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use Claroline\CoreBundle\Entity\Workspace\Workspace;
use Claroline\CoreBundle\Manager\ResourceManager;
use Claroline\CoreBundle\Pager\PagerFactory;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Claroline\OfflineBundle\Entity\UserSynchronized;
use JMS\DiExtraBundle\Annotation as DI;
//use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManager;

/**
 * @DI\Service("claroline.manager.test_dql_manager")
 */
class TestDQLManager
{
    private $om;
    private $pagerFactory;
    private $translator;
    private $userSynchronizedRepo;
    private $resourceNodeRepo;
    private $revisionRepo;
    private $resourceManager;
    private $workspaceRepo;
    private $roleRepo;
    private $offline;
    private $em;

    /**
     * Constructor.
     *
     * @DI\InjectParams({
     *     "om"             = @DI\Inject("claroline.persistence.object_manager"),
     *     "pagerFactory"   = @DI\Inject("claroline.pager.pager_factory"),
     *     "translator"     = @DI\Inject("translator"),
     *     "resourceManager"= @DI\Inject("claroline.manager.resource_manager"),
     *     "em"              = @DI\Inject("doctrine.orm.entity_manager")
     * })
     */
    public function __construct(
        ObjectManager $om,
        PagerFactory $pagerFactory,
        TranslatorInterface $translator,
        ResourceManager $resourceManager,
        EntityManager $em
    )
    {
        $this->om = $om;
        $this->pagerFactory = $pagerFactory;
        $this->userSynchronizedRepo = $om->getRepository('ClarolineOfflineBundle:UserSynchronized');
        $this->resourceNodeRepo = $om->getRepository('ClarolineCoreBundle:Resource\ResourceNode');
        $this->revisionRepo = $om->getRepository('ClarolineCoreBundle:Resource\Revision');
        $this->workspaceRepo = $om->getRepository('ClarolineCoreBundle:Workspace\Workspace');
        $this->roleRepo = $om->getRepository('ClarolineCoreBundle:Role');
        $this->translator = $translator;
        $this->resourceManager = $resourceManager;
        $this->offline = array('text');
        $this->em = $em;
    }

    /*
	*	Logique de la première requête :
	*	1 - Récuperer les espaces d'activités de l'utilisateurs
	*	2 - Pour chaque workspace récuperer l'intégralité des ressources node à synchroniser et ayant un type supporté
	*	3 - Pour chaque ressource node trouver la ressource correspondant
	*
	*	Temps : +/- 70 ms
	*	Nombre de requêtes : 43
	*/
    public function firstDQL($user, $date)
    {
        ini_set('max_execution_time', 0);

        $userWS = $this->workspaceRepo->findByUser($user);
        $this->firstMethod($userWS, $this->offline, $date);

    }

    public function firstMethod($userWS, $types, $date)
    {
        foreach ($userWS as $element) {
            $ressourcesToSync = $this->first($element, $types, $date);

            if (count($ressourcesToSync) >= 1) {
                echo 'dql_1: '.count($ressourcesToSync).'<br/>';
                foreach ($ressourcesToSync as $resource) {
                    $myRes = $this->resourceManager->getResourceFromNode($resource);
                    $revision = $this->revisionRepo->findOneBy(array('text' => $myRes));

                // $revision = $this->firstX($ressourcesToSync);
                // foreach ($revision as $res) {
                    // $myRes = $this->resourceManager->getResourceFromNode($res);
                    // $revision = $this->revisionRepo->findOneBy(array('text' => $myRes));
                    // echo $res->getContent().'<br/>';
                }
            }
        }
    }

    private function first($workspace, $types, $date)
    {
        $query = $this->resourceNodeRepo->createQueryBuilder('res')
            ->join('res.resourceType', 'type')
            ->where('res.workspace = :workspace')
            ->andWhere('res.modificationDate > :date')
            ->andWhere('type.name IN (:types)')
            ->setParameter('workspace', $workspace)
            ->setParameter('types', $types)
            ->setParameter('date', $date)
            ->getQuery();

        return $query->getResult();

    }

    private function firstX($resouceNodeList)
    {
        $query = $this->om->getRepository('ClarolineCoreBundle:Resource\Text')->createQueryBuilder('res')
            ->where('res.resourceNode IN (:resouceNodeList)')
            ->setParameter('resouceNodeList', $resouceNodeList)
            ->getQuery();

        return $query->getResult();
    }

    /*
	*	Logique de la seconde requête :
	*	1 - Récuperer les espaces d'activités de l'utilisateurs
	*	2 - Pour chaque workspace
	*		2.1 - Pour chaque type supporté, trouver les ressources node ET les ressource de ce type et dans ce workspace
	*	3 - Pour chaque ressource node trouver la ressource correspondant
	*
	*	Temps : x
	*	Nombre de requêtes : x
	*
	*	Impossible de faire une jointure avec les ressources vu qu'elles ne disposent pas de repository.
	*/
    public function secondDQL($user, $date)
    {
        ini_set('max_execution_time', 0);

        $userWS = $this->workspaceRepo->findByUser($user);
        $this->secondMethod($userWS, $this->offline, $date);
    }

    public function secondMethod($userWS, $offline, $date)
    {
        foreach ($userWS as $element) {
            foreach ($offline as $type) {
                $result = $this->second($element, $type, $date);
                // $resultToSync = $this->XXX;
                echo 'dql_2_1: '.count($result[0]).'<br/>';
                // echo 'dql_2_2: '.count($result[1]).'<br/>';
                foreach ($result[0] as $text) {
                    // echo $text->getContent().'<br/>';
                }
            }
        }
    }

    private function second($workspace, $types, $date)
    {
        $query = $this->om->getRepository('ClarolineCoreBundle:Resource\Text')->createQueryBuilder('res')
            ->addSelect('res.resourceNode')
            ->join('res.resourceNode', 'res_node')
            ->join('res_node.resourceType', 'type')
            ->where('res_node.workspace = :workspace')
            ->andWhere('res_node.modificationDate > :date')
            ->andWhere('type.name = :types')
            ->setParameter('workspace', $workspace)
            ->setParameter('types', $types)
            ->setParameter('date', $date)
            ->getQuery();

        return $query->getResult();
    }

    /*
	*	Estimation symfony debug : Ok
	* 	Requetes : 17
	*	Temps (avec print): 37 ms - 15.6 ms - 52.6 ms - 33 ms - 34 ms
	*	Temps (sans print): +/- equivalent
	*
	*	New request with SELECT FOR : 17 request, +/- 30 ms (idem) mais plus élégant. On renvoit juste le hashname de chaque resourceNode, pas la resourceNode ET son hashname (un parcours de tableau en moins donc)
	*	ATTENTION leq hashNames des resourceNode correspondant au WS sont aussi envoyés!
	*/
    public function hash_test($user)
    {
        $results = $this->hash($user);
        echo 'Mon taleau dql : '.count($results).'<br/>';
        foreach ($results as $result) {
            echo $result['hashName'].'<br/>';
        }
    }

    private function hash($user)
    {
        $query = $this->em->createQuery('
			SELECT res.hashName FROM Claroline\CoreBundle\Entity\Resource\ResourceNode res
			JOIN res.workspace w
			JOIN w.roles r
			JOIN r.users u
			WHERE u.id = :user
			');
        $query->setParameter('user', $user);

        return $query->getResult();
    }
}
