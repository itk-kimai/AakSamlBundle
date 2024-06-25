<?php

namespace KimaiPlugin\AakSamlBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta;

class AakSamlTeamMetaRepository extends EntityRepository
{
    /**
     * @param AakSamlTeamMeta $meta
     * @throws ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function saveAakSamlTeamMeta(AakSamlTeamMeta $meta): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($meta);
        $entityManager->flush();
    }
}