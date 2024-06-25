<?php

namespace KimaiPlugin\AakSamlBundle\Service;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlTeamMetaRepository;

class SamlDataHydrateService
{

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly AakSamlTeamMetaRepository $aakSamlTeamMetaRepository,
    )
    {
    }

    public function hydrate(User $user, SamlDTO $samlDto): void
    {
        $this->hydrateUser($user, $samlDto);
        $this->hydrateTeam($user, $samlDto);
    }

    private function hydrateUser(User $user, SamlDTO $samlDto): void
    {
        // The SAML mapping config should map 'az -> username' and 'email'
        // kimai/config/packages/local.yaml
        // mapping:
        //   - { saml: $http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname, kimai: username }
        //   - { saml: $http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress, kimai: email }

        // Kimai "Title" field is used as "Office", E.g. "ITK Development"
        $user->setTitle($samlDto->office);

        // Kimai "Account" ("Staff number" in the UI) field is used for azIdent
        $user->setAccountNumber($samlDto->azIdent);
    }

    private function hydrateTeam(User $user, SamlDTO $samlDto): void
    {
        /** @var AakSamlTeamMeta $aakSamlTeamMeta */
        $aakSamlTeamMeta = $this->aakSamlTeamMetaRepository->findOneBy(['officeId' => $samlDto->officeId]);

        // Kimai has a unique constraint on team names. We include the id to ensure uniqueness.
        $teamName = sprintf('%s (%d)', $samlDto->office, $samlDto->officeId);

        if (null === $aakSamlTeamMeta) {
            $team = new Team(name: $teamName);
            $this->teamRepository->saveTeam($team);

            $aakSamlTeamMeta = new AakSamlTeamMeta($team, $samlDto);
            $aakSamlTeamMeta->setTeam($team);
        } else {
            $team = $aakSamlTeamMeta->getTeam();
            $team->setName($teamName);
        }

        $team->addUser($user);

        // @todo handle add/remove team-lead

        $aakSamlTeamMeta->setValues($samlDto);

        $this->teamRepository->saveTeam($team);
        $this->aakSamlTeamMetaRepository->saveAakSamlTeamMeta($aakSamlTeamMeta);
    }
}