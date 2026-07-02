<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta;

/**
 * @extends \Doctrine\ORM\EntityRepository<AakSamlTeamMeta>
 */
class AakSamlTeamMetaRepository extends EntityRepository
{
    /**
     * @param AakSamlTeamMeta $meta
     *
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
