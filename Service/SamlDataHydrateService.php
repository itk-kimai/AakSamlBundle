<?php

namespace KimaiPlugin\AakSamlBundle\Service;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\User\UserService;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlTeamMetaRepository;

class SamlDataHydrateService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly TeamRepository $teamRepository,
        private readonly AakSamlTeamMetaRepository $aakSamlTeamMetaRepository,
    ) {
    }

    public function hydrate(User $user, SamlDTO $samlDto): void
    {
        $team = $this->hydrateTeam($user, $samlDto);
        $teamLead = $this->hydrateTeamLead($team, $samlDto);
        $this->hydrateUser($user, $samlDto, $teamLead, $team);
    }

    private function hydrateUser(User $user, SamlDTO $samlDto, User $teamLead, Team $team): void
    {
        // The SAML mapping config should map 'email -> username' and 'email -> email'
        // kimai/config/packages/local.yaml
        // mapping:
        //   - { saml: $http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress, kimai: email }

        // Kimai "Title" field is used as "Office", E.g. "ITK Development"
        $user->setTitle($samlDto->office);

        // Kimai "Account" ("Staff number" in the UI) field is used for azIdent
        $user->setAccountNumber($samlDto->azIdent);

        // Kimai "Alias" is mapped to displayName
        $user->setAlias($samlDto->displayName);

        // Kimai "Supervisor" is mapped to manager
        $user->setSupervisor($teamLead);

        // Clean up any past team memberships. A user should only be a private member of one team.
        foreach ($user->getMemberships() as $membership) {
            if (!$membership->isTeamlead() && $membership->getTeam()?->getId() !== $team->getId()) {
                $user->removeMembership($membership);
            }
        }

        // Add current team to user (method handles the user already member case)
        $user->addTeam($team);

        $this->userService->updateUser($user);
    }

    private function hydrateTeam(User $user, SamlDTO $samlDto): Team
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

        $aakSamlTeamMeta->setValues($samlDto);

        $this->teamRepository->saveTeam($team);
        $this->aakSamlTeamMetaRepository->saveAakSamlTeamMeta($aakSamlTeamMeta);

        return $team;
    }

    private function hydrateTeamLead(Team $team, SamlDTO $samlDto): User
    {
        $teamLeadUser = $this->userService->findUserByEmail($samlDto->managerEmail);

        if (null === $teamLeadUser) {
            $teamLeadUser = $this->userService->createNewUser();
            $this->setTeamLeadValues($teamLeadUser, $samlDto);

            $this->userService->saveNewUser($teamLeadUser);
        } else {
            $this->setTeamLeadValues($teamLeadUser, $samlDto);

            $this->userService->updateUser($teamLeadUser);
        }

        $this->hydrateTeamLeadsForTeam($team, $teamLeadUser);
        $this->teamRepository->saveTeam($team);

        return $teamLeadUser;
    }

    private function hydrateTeamLeadsForTeam(Team $team, User $teamLeadUser): void
    {
        // Remove past team leads (if any)
        foreach ($team->getTeamleads() as $teamLead) {
            if ($teamLead->getUsername() !== $teamLeadUser->getUsername()) {
                $team->removeUser($teamLead);
                // If the user is no longer team lead for any team then ROLE_TEAMLEAD should be removed
                if (!$teamLead->isTeamlead()) {
                    $teamLead->removeRole(User::ROLE_TEAMLEAD);
                    $this->userService->updateUser($teamLeadUser);
                }
            }
        }

        // Add current team lead (method handles the user already team lead case)
        $team->addTeamlead($teamLeadUser);
    }

    private function setTeamLeadValues(User $teamLeadUser, SamlDTO $samlDto): void
    {
        $teamLeadUser->setUsername($samlDto->managerEmail);

        // Set a plain password to satisfy the validator
        // @see Kimai App\Saml\SamlProvider::hydrateUser()
        $teamLeadUser->setPlainPassword(substr(bin2hex(random_bytes(100)), 0, 50));
        $teamLeadUser->setPassword('');

        $teamLeadUser->setEmail($samlDto->managerEmail);
        $teamLeadUser->setAlias($samlDto->managerName);
        $teamLeadUser->addRole(User::ROLE_TEAMLEAD);

        $teamLeadUser->setAuth('aak_saml');
    }
}
