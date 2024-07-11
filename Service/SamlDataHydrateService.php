<?php

namespace KimaiPlugin\AakSamlBundle\Service;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\User\UserService;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta;
use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlTeamMetaRepository;

class SamlDataHydrateService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly TeamRepository $teamRepository,
        private readonly AakSamlTeamMetaRepository $aakSamlTeamMetaRepository,
    ) {
    }

    /**
     * @throws AakSamlException
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function hydrate(User $user, SamlDTO $samlDto): void
    {
        $teamLead = $this->hydrateTeamLead($samlDto);

        // Setup team user is member of
        $memberUnitId = $samlDto->getMemberOrganizationUnitId();
        $memberUnitName = $samlDto->getMemberOrganizationUnitName();
        $memberTeam = $this->hydrateTeam($memberUnitName, $memberUnitId, $samlDto->managerEmail, $samlDto->managerName, $samlDto);

        // Setup manager (personaleleder) as team lead for the team
        $this->hydrateTeamLeadForTeam($memberTeam, $teamLead);

        // Set user values and team membership
        $this->hydrateUser($user, $samlDto, $teamLead, $memberTeam);

        if ($samlDto->isTeamLead()) {
            $teamUnitId = $samlDto->getTeamLeadOrganizationUnitId();
            $teamUnitName = $samlDto->getTeamLeadOrganizationUnitName();

            $teamLeadTeam = $this->hydrateTeam($teamUnitName, $teamUnitId, $samlDto->emailAddress, $samlDto->displayName, $samlDto);
            $this->hydrateTeamLeadForTeam($teamLeadTeam, $user);

            foreach ($user->getTeams() as $team) {
                if ($team->isTeamlead($user)) {
                    foreach ($team->getUsers() as $teamMember) {
                        if (!in_array($teamMember->getUsername(), $samlDto->employeeList) && $teamMember->getUsername() !== $user->getUsername()) {
                            $team->removeUser($teamMember);
                        }
                    }
                }
            }
        }
    }

    private function hydrateUser(User $user, SamlDTO $samlDto, User $teamLead, Team $memberTeam): void
    {
        // The SAML mapping config should map 'email -> username' and 'email -> email'
        // kimai/config/packages/local.yaml
        // mapping:
        //   - { saml: $http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress, kimai: email }

        // Kimai "Title" field is used as "Org Unit", E.g. "ITK Development"
        $user->setTitle($samlDto->getOrganizationUnitName());

        // Kimai "Account" ("Staff number" in the UI) field is used for azIdent
        $user->setAccountNumber($samlDto->azIdent);

        // Kimai "Alias" is mapped to displayName
        $user->setAlias($samlDto->displayName);

        // Kimai "Supervisor" is mapped to manager
        $user->setSupervisor($teamLead);

        if ($samlDto->isTeamLead()) {
            $user->addRole(User::ROLE_TEAMLEAD);
        }

        // Clean up any past team memberships. A user should only be a private member of one team.
        foreach ($user->getMemberships() as $membership) {
            if (!$membership->isTeamlead() && $membership->getTeam()?->getId() !== $memberTeam->getId()) {
                $user->removeMembership($membership);
            }
        }

        // Add current team to user (method handles the user already member case)
        $user->addTeam($memberTeam);

        $this->userService->updateUser($user);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function hydrateTeam(string $name, int $orgUnitId, string $managerEmail, string $managerName, SamlDTO $samlDto): Team
    {
        /** @var AakSamlTeamMeta $aakSamlTeamMeta */
        $aakSamlTeamMeta = $this->aakSamlTeamMetaRepository->findOneBy(['orgUnitId' => $orgUnitId, 'managerEmail' => $managerEmail]);

        if (null === $aakSamlTeamMeta) {
            // Fallback to look for teams created with old claims. Prior to correct "personaleleder" claim.
            $aakSamlTeamMeta = $this->aakSamlTeamMetaRepository->findOneBy(['orgUnitId' => $orgUnitId]);
        }

        // Kimai has a unique constraint on team names. We include the manager email to ensure uniqueness.
        $teamName = \sprintf('%s (%s)', $name, $managerEmail);
        $teamName = \trim($teamName);

        if (null === $aakSamlTeamMeta) {
            $team = new Team(name: $teamName);
            $this->teamRepository->saveTeam($team);

            $aakSamlTeamMeta = new AakSamlTeamMeta($team, $samlDto, $orgUnitId, $managerEmail, $managerName);
            $aakSamlTeamMeta->setTeam($team);
        } else {
            $team = $aakSamlTeamMeta->getTeam();
            $team->setName($teamName);
        }

        $aakSamlTeamMeta->setValues($samlDto, $orgUnitId, $managerEmail, $managerName);

        $this->teamRepository->saveTeam($team);
        $this->aakSamlTeamMetaRepository->saveAakSamlTeamMeta($aakSamlTeamMeta);

        return $team;
    }

    /**
     * @throws AakSamlException
     */
    private function hydrateTeamLead(SamlDTO $samlDto): User
    {
        $teamLeadUser = $this->userService->findUserByEmail($samlDto->managerEmail);

        if (null === $teamLeadUser) {
            $teamLeadUser = $this->userService->createNewUser();

            $teamLeadUser->setUsername($samlDto->managerEmail);

            // Set a plain password to satisfy the validator
            // @see Kimai App\Saml\SamlProvider::hydrateUser()
            $teamLeadUser->setPlainPassword(substr(bin2hex(random_bytes(100)), 0, 50));
            $teamLeadUser->setPassword('');

            $teamLeadUser->setEmail($samlDto->managerEmail);
            $teamLeadUser->setAlias($samlDto->managerName);
            $teamLeadUser->addRole(User::ROLE_TEAMLEAD);

            $teamLeadUser->setAuth('aak_saml');

            $this->userService->saveNewUser($teamLeadUser);
        }

        return $teamLeadUser;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function hydrateTeamLeadForTeam(Team $team, User $teamLeadUser): void
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

        $this->teamRepository->saveTeam($team);
    }
}
