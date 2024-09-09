<?php

namespace KimaiPlugin\AakSamlBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\ResultSetMapping;
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

    public function getLatestUserLog(UserInterface $user, bool $success = true): ?AakSamlClaimsLog
    {
        return $this->findOneBy(['samlUserEmail' => $user->getUserIdentifier(), 'loginSuccess' => $success], ['loggedAt' => 'DESC']);
    }

    /**
     * Get latest claims logs grouped by user.
     *
     * @return iterable
     *
     * @throws QueryException
     */
    public function getAakSamlClaimsLogs(): iterable
    {
        $sql = 'SELECT * FROM kimai2_aak_saml_claims_log logs 
            WHERE logs.logged_at = (
                SELECT MAX(logged_at) 
                FROM kimai2_aak_saml_claims_log temp 
                WHERE logs.saml_user_email = temp.saml_user_email AND temp.login_success = TRUE
            )';

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult(AakSamlClaimsLog::class, 'logs');
        $rsm->addFieldResult('logs', 'id', 'id');
        $rsm->addFieldResult('logs', 'saml_user_email', 'samlUserEmail');
        $rsm->addFieldResult('logs', 'last_saml_login_at', 'lastSamlLoginAt');
        $rsm->addFieldResult('logs', 'login_success', 'loginSuccess');
        $rsm->addFieldResult('logs', 'logged_at', 'loggedAt');
        $rsm->addFieldResult('logs', 'claims_hash', 'claimsHash');
        $rsm->addFieldResult('logs', 'claims', 'claims');
        $rsm->addFieldResult('logs', 'exceptionMessage', 'exceptionMessage');

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);

        return $query->toIterable();
    }
}
