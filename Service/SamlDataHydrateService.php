<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    // Kimai entity constraints we must respect when populating users and teams
    // from unbounded SAML claims. Overflowing any of these makes Kimai's
    // validator reject the entity, surfacing as an opaque "Validation Failed"
    // login failure. See App\Entity\User and App\Entity\Team.
    private const USER_TITLE_MAX_LENGTH = 50;     // User::$title,    #[Assert\Length(max: 50)] (not unique)
    private const USER_ALIAS_MAX_LENGTH = 60;     // User::$alias,    #[Assert\Length(max: 60)] (not unique)
    private const USER_ACCOUNT_MAX_LENGTH = 30;   // User::$account,  #[Assert\Length(max: 30)] (not unique)
    private const USER_USERNAME_MAX_LENGTH = 64;  // User::$username, #[Assert\Length(max: 64)] + #[UniqueEntity('username')]
    private const TEAM_NAME_MAX_LENGTH = 100;     // Team::$name,     #[Assert\Length(max: 100)] + #[UniqueEntity('name')]

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
        if (null !== $teamLead) {
            $this->hydrateTeamLeadForTeam($memberTeam, $teamLead);
        }

        // Set user values and team membership
        $this->hydrateUser($user, $samlDto, $teamLead, $memberTeam);

        if ($samlDto->isTeamLead()) {
            $teamUnitId = $samlDto->getTeamLeadOrganizationUnitId();
            $teamUnitName = $samlDto->getTeamLeadOrganizationUnitName();

            $teamLeadTeam = $this->hydrateTeam($teamUnitName, $teamUnitId, $samlDto->emailAddress, $samlDto->displayName, $samlDto);
            $this->hydrateTeamLeadForTeam($teamLeadTeam, $user);

            // Check all users of all teams the user is team lead for to ensure the user is not
            // team lead for any users not in "employeeList" claim
            foreach ($user->getTeams() as $team) {
                if ($team->isTeamlead($user)) {
                    foreach ($team->getUsers() as $teamMember) {
                        if (!$samlDto->hasEmployee($teamMember->getUsername()) && $teamMember->getUsername() !== $user->getUsername()) {
                            $team->removeUser($teamMember);
                        }
                    }
                }
            }
        }
    }

    private function hydrateUser(User $user, SamlDTO $samlDto, ?User $teamLead, Team $memberTeam): void
    {
        // The SAML mapping config should map 'email -> username' and 'email -> email'
        // kimai/config/packages/local.yaml
        // mapping:
        //   - { saml: $http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress, kimai: email }

        // Kimai "Title" field is used as "Org Unit", E.g. "ITK Development".
        // App\Entity\User::$title is #[Assert\Length(max: 50)] and not unique;
        // SAML org-unit names regularly exceed 50 chars, so truncate.
        $user->setTitle($this->truncate($samlDto->getOrganizationUnitName(), self::USER_TITLE_MAX_LENGTH));

        // Kimai "Account" ("Staff number" in the UI) field is used for azIdent.
        // App\Entity\User::$account is #[Assert\Length(max: 30)] and not unique.
        $user->setAccountNumber($this->truncate($samlDto->azIdent, self::USER_ACCOUNT_MAX_LENGTH));

        // Kimai "Alias" is mapped to displayName.
        // App\Entity\User::$alias is #[Assert\Length(max: 60)] and not unique.
        $user->setAlias($this->truncate($samlDto->displayName, self::USER_ALIAS_MAX_LENGTH));

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

        // Kimai has a unique constraint on team names (App\Entity\Team::$name is
        // #[UniqueEntity('name')]) and limits it to 100 chars (#[Assert\Length(max: 100)]).
        // We include the org-id (and manager email) to guarantee uniqueness, so the
        // uniqueness lives in the suffix, not the descriptive org name. To respect
        // the length limit without breaking uniqueness we keep the suffix intact and
        // truncate only the descriptive part.
        if ('' === $managerEmail) {
            $suffix = \sprintf(' (%s)', $orgUnitId);
        } else {
            $suffix = \sprintf(' (%s, %s)', $orgUnitId, $managerEmail);
        }
        $nameBudget = self::TEAM_NAME_MAX_LENGTH - mb_strlen($suffix);
        $teamName = trim($this->truncate($name, max(0, $nameBudget)) . $suffix);

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
    private function hydrateTeamLead(SamlDTO $samlDto): ?User
    {
        if ('' === $samlDto->managerEmail) {
            // 'Magistrats direktører' does not have a 'personaledLeder'
            return null;
        }

        $teamLeadUser = $this->userService->findUserByEmail($samlDto->managerEmail);

        if (null === $teamLeadUser) {
            // App\Entity\User::$username is #[Assert\Length(max: 64)] + #[UniqueEntity('username')]
            // and $email is #[Assert\Length(max: 180)] + #[UniqueEntity('email')]. We use the
            // manager's email for both. Unlike the descriptive fields above, a unique identity
            // must NOT be truncated: a shortened address is invalid and two long addresses could
            // truncate to the same value and collide. The username limit (64) is the binding one
            // (< the 180 email limit), so we fail fast with a clear, logged message instead.
            if (mb_strlen($samlDto->managerEmail) > self::USER_USERNAME_MAX_LENGTH) {
                throw new AakSamlException(\sprintf(
                    'Manager email "%s" (%d chars) exceeds Kimai\'s %d-character username limit; cannot create team lead user.',
                    $samlDto->managerEmail,
                    mb_strlen($samlDto->managerEmail),
                    self::USER_USERNAME_MAX_LENGTH,
                ));
            }

            $teamLeadUser = $this->userService->createNewUser();

            $teamLeadUser->setUsername($samlDto->managerEmail);

            // Set a plain password to satisfy the validator
            // @see Kimai App\Saml\SamlProvider::hydrateUser()
            $teamLeadUser->setPlainPassword(substr(bin2hex(random_bytes(100)), 0, 50));
            $teamLeadUser->setPassword('');

            $teamLeadUser->setEmail($samlDto->managerEmail);
            // App\Entity\User::$alias is #[Assert\Length(max: 60)] and not unique.
            $teamLeadUser->setAlias($this->truncate($samlDto->managerName, self::USER_ALIAS_MAX_LENGTH));
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

    /**
     * Truncate a value to a Kimai column's maximum length.
     *
     * Safe only for fields WITHOUT a unique constraint: truncating a unique
     * value could corrupt identity or cause collisions (see the fail-fast guard
     * on the manager username/email instead).
     */
    private function truncate(string $value, int $maxLength): string
    {
        return mb_substr($value, 0, $maxLength);
    }
}
