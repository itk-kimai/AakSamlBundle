<?php

namespace KimaiPlugin\AakSamlBundle\Service;

use KimaiPlugin\AakSamlBundle\Entity\AakSamlClaimsLog;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlClaimsLogRepository;
use Symfony\Component\Security\Core\User\UserInterface;

class SamlClaimsLogger
{
    public function __construct(
        private readonly AakSamlClaimsLogRepository $aakSamlClaimsLogRepository
    ) {
    }

    /**
     * @param UserInterface $user
     * @param bool $success
     * @param array<string, array<int, string>> $claims
     * @param \Exception|null $exception
     *
     * @return void
     *
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function logClaims(UserInterface $user, bool $success, array $claims, ?\Exception $exception = null): void
    {
        unset($claims['sessionIndex']);
        $claimsLog = new AakSamlClaimsLog($user->getUserIdentifier(), $success, $claims, $exception);

        /** @var ?AakSamlClaimsLog $latest */
        $latest = $this->aakSamlClaimsLogRepository->getLatestUserLog($user, $success);

        if (!$claimsLog->isLoginSuccess() || $latest?->getClaimsHash() !== $claimsLog->getClaimsHash()) {
            // Claims have changed, or this is the users first login
            $this->aakSamlClaimsLogRepository->saveAakSamlClaimsLog($claimsLog);
        } elseif (null !== $latest) {
            // Claims unchanged. Log latest SAML login datetime.
            $latest->setLastSamlLoginAt();
            $this->aakSamlClaimsLogRepository->saveAakSamlClaimsLog($latest);
        }
    }
}
