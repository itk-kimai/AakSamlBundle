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

    public function logClaims(UserInterface $user, bool $success, array $claims, ?\Exception $exception = null): void
    {
        unset($claims['sessionIndex']);
        $claimsLog = new AakSamlClaimsLog($user->getUserIdentifier(), $success, $claims, $exception);

        /** @var ?AakSamlClaimsLog $latest */
        $latest = $this->aakSamlClaimsLogRepository->getLatestUserLog($user, $success);

        if (!$claimsLog->isLoginSuccess() || $latest?->getClaimsHash() !== $claimsLog->getClaimsHash()) {
            $this->aakSamlClaimsLogRepository->saveAakSamlClaimsLog($claimsLog);
        }
    }
}
