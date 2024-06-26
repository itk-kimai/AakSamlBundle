<?php

namespace KimaiPlugin\AakSamlBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlClaimsLog;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends \Doctrine\ORM\EntityRepository<AakSamlClaimsLog>
 */
class AakSamlClaimsLogRepository extends EntityRepository
{
    /**
     * @param AakSamlClaimsLog $data
     *
     * @throws ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function saveAakSamlClaimsLog(AakSamlClaimsLog $data): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($data);
        $entityManager->flush();
    }

    public function getLatestUserLog(UserInterface $user, bool $success): ?AakSamlClaimsLog
    {
        return $this->findOneBy(['samlUserEmail' => $user->getUserIdentifier(), 'loginSuccess' => $success], ['loggedAt' => 'DESC']);
    }
}
