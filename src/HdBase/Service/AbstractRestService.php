<?php
/**
 * V5
 * LICENSE
 *
 * Insert License here
 *
 * @package Service
 */

namespace HdBase\Service;

use Zend\ServiceManager\ServiceManagerAwareInterface;
use Zend\ServiceManager\ServiceManager;

use Doctrine\ORM\QueryBuilder;

use HdBase\Entity\EntityInterface;

abstract class AbstractRestService implements ServiceManagerAwareInterface
{

    /**
     * Service Manager
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * EntityManager
     * @var Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * Mapper
     * @var DtoInterface
     */
    protected $mapper;

    /**
     * Result Count
     * @var int
     */
    protected $count;

    /**
     * Return the queryBuilder of the entity
     *
     * @param int $start
     * @param int $count
     * @return Doctrine\ORM\QueryBuilder
     */
    protected function getQueryBuilder($start = 0, $count = 100)
    {
        $entityManager = $this->getEntityManager();
        $queryBuilder = $entityManager->createQueryBuilder();

        $queryBuilder->select('entity')->from($this->getEntity(), 'entity');

        if ($count != null) {
            $queryBuilder->setFirstResult($start);
            $queryBuilder->setMaxResults($count);
        }

        return $queryBuilder;
    }

    /**
     * Used to specify the sorting/filter criteria to the QueryBuilder
     * from the criteria array (aka parse the array of criteria into DQL stuff)
     *
     * By default, it does only handle basic criteria
     * (meaning entity properties, but not the associations)
     * and only with equal operator
     *
     * in order to specify more complex behaviour,
     * feel free to override this function
     *
     * @param QueryBuilder $qb
     * @param array $criteria
     * @return QueryBuilder
     */
    protected function setCriteria(QueryBuilder $qb, $criteria)
    {
        // critères
        $and = $qb->expr()->andx();
        foreach ($criteria as $name => $value) {
            $and->add(
                $qb->expr()->eq(
                    'entity._' . $name, "'" . $value . "'"
                )
            );
        }
        if ($and->count() > 0) {
            $qb->where($and);
        }
        return $qb;
    }

    /**
     * Set Count
     * @param int $count
     */
    public function setCount($count)
    {
        $this->count = $count;
    }

    /**
     * Renvoie toutes les entités répondant aux critères
     *
     * @param int $start
     * @param int $count
     * @param array $criteres Tableau clé valeur de critères de recherche.
     * @param array $orderBy Tableau contenant les infos de tri de la requete
     *                       sous la forme: $orderBy["sort"]= "nomDuChamp",
     *                                      $orderBy["order"]= "asc" ou "desc"
     * @return array Pwb_ModelInterface[]
     * @throws Pwb_Service_Exception_DomainException
     *         pour une erreur de mapper file
     */
    public function getAll(
    $start = 0, $count = 100, $criteres = array(), $orderBy = array()
    )
    {
        $queryBuilder = $this->getQueryBuilder($start, $count);
        $this->setCriteria($queryBuilder, $criteres);

        if (count($orderBy)) {
            $queryBuilder->addOrderBy(
                "entity._" . $orderBy["sort"], $orderBy["order"]
            );
        }

        $query = $queryBuilder->getQuery();
        $results = $query->getResult();
        $this->setCount(count($results));
        return $results;
    }

    /**
     * Get Model by Id
     * @param int $idModel
     * @return DysBase\Entity\EntityInterface
     * @throws Pwb_Service_Exception_NotFound
     */
    public function getById($idModel)
    {
        $em = $this->getEntityManager();
        // Doctrine
        $model = $em->find($this->getEntity(), $idModel);
        if (!$model) {
            throw new \HdBase\Entity\Exception\NotFound(
                $this->_entity . ' ' . $idModel . ' cannot be retrieved'
            );
        }

        return $model;
    }

    /**
     * Get Entity
     * @return DysBase\Entity\EntityInterface
     */
    public function getEntity()
    {
        return $this->mapper->getEntity();
    }

    /**
     * Convert Model to Dto
     * @param  Pwb_ModelInterface
     * @return \DysBase\Dto\AbstractDto
     * @throws Pwb_Service_Exception_DomainException
     *         pour une erreur de mapper dto
     */
    public function toDto($model)
    {
        try {
            return $this->mapperDto->toDto($model);
        } catch (DYS\Model\Mapper\Dto\Exception $e) {
            throw new \DYS\Service\Exception\DomainException(
                $e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * Convert Dto to Model
     * @param AbstractDto $dto
     * @return Pwb_ModelInterface
     * @throws Pwb_Model_Mapper_Dto_Exception_NotFound
     *         pour une entité introuvable
     * @throws Pwb_Service_Exception_DomainException
     *         pour une erreur de mapper dto
     */
    public function fromDto(AbstractDto $dto)
    {
        try {
            $entity = $this->mapperDto->fromDto($dto);
        } catch (\DYS\Model\Mapper\Dto\Exception\NotFound $e) {
            throw new DYS\Service\Exception\NotFound(
                $e->getMessage(),
                404,
                $e
            );
        } catch (\DYS\Model\Mapper\Dto\Exception\ExceptionInterface $e) {
            throw new DYS\Service\Exception\DomainException(
                $e->getMessage(),
                500,
                $e
            );
        }

        // évite les persist en cascade, lors d'imbrications d'entity
        // ex: attribut::fromDto() appelle option::fromDto()
        // le serviceAttribut::save() ne fait pas de save en cascade,
        // pour des raisons de perf. Donc on persist ici systématiquement
        // toutes les éventuelles associations
        $this->getEntityManager()->persist($entity);

        return $entity;
    }

    /**
     * Enregistre l'entité
     *
     * @param EntityInterface $entity
     * @return Pwb_ModelInterface
     * @throws DysBase\Service\Exception\DomainException
     */
    public function save(EntityInterface $entity)
    {

        $entityManager = $this->getEntityManager();
        try {
            $entityManager->persist($entity);
            $entityManager->flush();
        } catch (\Doctrine\ORM\ORMException $e) {
            throw new \DysBase\Service\Exception\DomainException(
                $e->getMessage(),
                500,
                $e
            );
        }

        return $entity;
    }

    /**
     * Get Dto
     * @return \DysBase\Dto\AbstractDto
     */
    protected function getDto()
    {
        return $this->mapperDto->getDto();
    }

    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
    }


    /**
     * Get EntityManager
     * @return Doctrine\ORM\EntityManager
     */
    private function getEntityManager()
    {
        if (null == $this->entityManager) {
            $sm = $this->getServiceManager();
            $this->entityManager = $sm->get(
                'doctrine.entitymanager.orm_default'
            );
        }
        return $this->entityManager;
    }

    /**
     * Retrieve service manager instance
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
            return $this->serviceManager;
    }

    /**
     * Set service manager instance
     *
     * @param ServiceManager $serviceManager
     * @return void
     */
    public function setServiceManager(ServiceManager $serviceManager)
    {
        $this->serviceManager = $serviceManager;
    }

}
