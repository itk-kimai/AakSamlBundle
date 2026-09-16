<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\Service;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use App\User\UserService;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta;
use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlTeamMetaRepository;
use KimaiPlugin\AakSamlBundle\Service\SamlDataHydrateService;
use KimaiPlugin\AakSamlBundle\Service\SamlDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamlDataHydrateService::class)]
final class SamlDataHydrateServiceTest extends TestCase
{
    /**
     * Claims for a user placed five levels down: 1001 (Aarhus Kommune) /
     * 1004 (Kultur og Borgerservice) / 1012 (Borgerservice og Biblioteker) /
     * 1103 (ITK) / 6530 (ITK Development), with no manager and no employees.
     *
     * @param array<string, list<string>> $overrides
     *
     * @return array<string, list<string>>
     */
    private static function attributes(array $overrides = []): array
    {
        return [
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname' => ['az12345'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => ['Jane Doe'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['jane@aarhus.dk'],
            'personaleLederUPN' => [''],
            'personaleLederDisplayName' => [''],
            'companyname' => ['Aarhus Kommune'],
            'division' => ['Kultur og Borgerservice'],
            'department' => ['Borgerservice og Biblioteker'],
            'extensionAttribute12' => ['ITK'],
            'Office' => ['ITK Development'],
            'extensionAttribute7' => ['1001;1004;1012;1103;6530'],
            'employeeList' => [''],
            ...$overrides,
        ];
    }

    /**
     * @param array<string, User> $usersByEmail existing Kimai users, keyed by email
     * @param list<User>          $savedUsers   users passed to UserService::saveUser()
     * @param list<User>          $updatedUsers users passed to UserService::updateUser()
     */
    private function userService(array $usersByEmail, array &$savedUsers, array &$updatedUsers): UserService
    {
        $userService = self::createStub(UserService::class);
        $userService->method('findUserByEmail')->willReturnCallback(
            static fn (string $email): ?User => $usersByEmail[$email] ?? null
        );
        $userService->method('createNewUser')->willReturnCallback(static fn (): User => new User());
        $userService->method('saveUser')->willReturnCallback(
            function (User $user) use (&$savedUsers): User {
                $savedUsers[] = $user;

                return $user;
            }
        );
        $userService->method('updateUser')->willReturnCallback(
            function (User $user) use (&$updatedUsers): User {
                $updatedUsers[] = $user;

                return $user;
            }
        );

        return $userService;
    }

    /**
     * @param list<Team> $savedTeams teams passed to TeamRepository::saveTeam()
     */
    private function teamRepository(array &$savedTeams): TeamRepository
    {
        $teamRepository = self::createStub(TeamRepository::class);
        $teamRepository->method('saveTeam')->willReturnCallback(
            function (Team $team) use (&$savedTeams): void {
                $savedTeams[] = $team;
            }
        );

        return $teamRepository;
    }

    /**
     * @param list<AakSamlTeamMeta> $existingMeta team meta already in the database
     * @param list<AakSamlTeamMeta> $savedMeta    meta passed to the repository
     */
    private function metaRepository(array $existingMeta, array &$savedMeta): AakSamlTeamMetaRepository
    {
        $metaRepository = self::createStub(AakSamlTeamMetaRepository::class);
        // The org unit alone does not identify a row: the service looks the meta up
        // by org unit *and* manager email, so a new manager means a new team.
        $metaRepository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($existingMeta): ?AakSamlTeamMeta {
                foreach ($existingMeta as $meta) {
                    if ($meta->getOrgUnitId() === ($criteria['orgUnitId'] ?? null)
                        && $meta->getManagerEmail() === ($criteria['managerEmail'] ?? null)) {
                        return $meta;
                    }
                }

                return null;
            }
        );
        $metaRepository->method('saveAakSamlTeamMeta')->willReturnCallback(
            function (AakSamlTeamMeta $meta) use (&$savedMeta): void {
                $savedMeta[] = $meta;
            }
        );

        return $metaRepository;
    }

    private static function user(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username);

        return $user;
    }

    /**
     * Teams get their id from the database, and hydration prunes memberships by id.
     */
    private static function team(string $name, int $id): Team
    {
        $team = new Team(name: $name);
        (new \ReflectionProperty(Team::class, 'id'))->setValue($team, $id);

        return $team;
    }

    /**
     * @param array<string, list<string>> $overrides claims as they were on the previous login
     */
    private static function meta(Team $team, int $orgUnitId, string $managerEmail = '', string $managerName = '', array $overrides = []): AakSamlTeamMeta
    {
        return new AakSamlTeamMeta($team, new SamlDTO(self::attributes($overrides)), $orgUnitId, $managerEmail, $managerName);
    }

    public function testOverlongValuesAreTruncatedToKimaiLimits(): void
    {
        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService([], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository([], $savedMeta),
        );
        $user = new User();

        // Depth 5 resolves the org name via office; make the office name and the
        // identity fields longer than their Kimai limits. No manager email -> the
        // user is not a team lead, keeping the flow to a single member team.
        $dto = new SamlDTO(self::attributes([
            'Office' => [str_repeat('O', 120)],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => [str_repeat('D', 70)],
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname' => [str_repeat('a', 40)],
            'extensionAttribute7' => ['1001;1004;1012;1103;6530'],
        ]));

        $service->hydrate($user, $dto);

        // User::$title max 50, $alias max 60, $account max 30 (all non-unique).
        self::assertSame(50, mb_strlen((string) $user->getTitle()));
        self::assertSame(60, mb_strlen((string) $user->getAlias()));
        self::assertSame(30, mb_strlen((string) $user->getAccountNumber()));

        // Team::$name max 100 and unique. The name must be truncated to fit but
        // keep the uniqueness suffix (the org-unit id) intact.
        self::assertNotEmpty($savedTeams);
        $teamName = (string) $savedTeams[0]->getName();
        self::assertLessThanOrEqual(100, mb_strlen($teamName));
        self::assertStringEndsWith(' (6530)', $teamName);
    }

    public function testOverlongManagerEmailIsRejectedRatherThanTruncated(): void
    {
        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService([], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository([], $savedMeta),
        );
        $user = new User();

        // A manager email longer than the 64-char username limit cannot be
        // truncated (username/email are unique), so hydration must fail clearly.
        $longEmail = str_repeat('a', 60).'@aarhus.dk'; // 70 chars

        $this->expectException(AakSamlException::class);
        $this->expectExceptionMessageIsOrContains('username limit');

        $service->hydrate($user, new SamlDTO(self::attributes([
            'personaleLederUPN' => [$longEmail],
            'personaleLederDisplayName' => ['A Manager'],
        ])));
    }

    public function testUnknownTeamIsCreatedWithMetaAndTheUserAddedToIt(): void
    {
        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService([], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository([], $savedMeta),
        );
        $user = self::user('jane@aarhus.dk');

        $service->hydrate($user, new SamlDTO(self::attributes()));

        // The org-unit id is what makes the team name unique. Without a manager
        // the suffix holds the id alone.
        self::assertNotEmpty($savedTeams);
        self::assertSame('ITK Development (6530)', $savedTeams[0]->getName());

        self::assertCount(1, $savedMeta);
        self::assertSame(6530, $savedMeta[0]->getOrgUnitId());
        self::assertSame('', $savedMeta[0]->getManagerEmail());
        self::assertSame($savedTeams[0], $savedMeta[0]->getTeam());

        self::assertSame([$savedTeams[0]], $user->getTeams());
        self::assertSame('ITK Development', $user->getTitle());
        self::assertSame('Jane Doe', $user->getAlias());
        self::assertSame('az12345', $user->getAccountNumber());
        self::assertNull($user->getSupervisor());
        self::assertFalse($user->hasRole(User::ROLE_TEAMLEAD));
        self::assertSame([$user], $updatedUsers);
    }

    public function testKnownTeamIsRenamedInsteadOfRecreated(): void
    {
        $existingTeam = self::team('Old Name (6530)', 7);
        // The org unit was named differently at the previous login.
        $existingMeta = self::meta($existingTeam, 6530, '', '', ['Office' => ['Old Name']]);

        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService([], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository([$existingMeta], $savedMeta),
        );
        $user = self::user('jane@aarhus.dk');

        $service->hydrate($user, new SamlDTO(self::attributes()));

        // An org unit that is already mapped keeps its team (and its projects and
        // customers); only the name follows the claims.
        self::assertSame('ITK Development (6530)', $existingTeam->getName());
        foreach ($savedTeams as $savedTeam) {
            self::assertSame($existingTeam, $savedTeam);
        }
        self::assertSame([$existingMeta], $savedMeta);
        self::assertSame([$existingTeam], $user->getTeams());
        // The stored org names follow the claims too, they are not left as they were.
        self::assertSame('ITK Development', $existingMeta->getOfficeName());
    }

    public function testUnknownManagerIsCreatedAsTeamLeadAndSupervisor(): void
    {
        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService([], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository([], $savedMeta),
        );
        $user = self::user('jane@aarhus.dk');

        $service->hydrate($user, new SamlDTO(self::attributes([
            'personaleLederUPN' => ['boss@aarhus.dk'],
            'personaleLederDisplayName' => [str_repeat('B', 70)],
        ])));

        self::assertCount(1, $savedUsers);
        $manager = $savedUsers[0];
        self::assertSame('boss@aarhus.dk', $manager->getUsername());
        self::assertSame('boss@aarhus.dk', $manager->getEmail());
        self::assertSame('aak_saml', $manager->getAuth());
        self::assertTrue($manager->hasRole(User::ROLE_TEAMLEAD));
        // User::$alias is max 60 and not unique, so a long manager name is truncated.
        self::assertSame(60, mb_strlen((string) $manager->getAlias()));
        // A plain password is needed to pass Kimai's validator.
        self::assertNotEmpty($manager->getPlainPassword());

        // The manager becomes both supervisor and team lead of the user's team.
        self::assertSame($manager, $user->getSupervisor());
        self::assertNotEmpty($savedTeams);
        self::assertSame('ITK Development (6530, boss@aarhus.dk)', $savedTeams[0]->getName());
        self::assertSame([$manager], $savedTeams[0]->getTeamleads());
    }

    public function testKnownManagerIsReusedWithoutCreatingAUser(): void
    {
        $manager = self::user('boss@aarhus.dk');

        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService(['boss@aarhus.dk' => $manager], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository([], $savedMeta),
        );
        $user = self::user('jane@aarhus.dk');

        $service->hydrate($user, new SamlDTO(self::attributes([
            'personaleLederUPN' => ['boss@aarhus.dk'],
            'personaleLederDisplayName' => ['Big Boss'],
        ])));

        self::assertSame([], $savedUsers);
        self::assertSame($manager, $user->getSupervisor());
        self::assertNotEmpty($savedTeams);
        self::assertSame([$manager], $savedTeams[0]->getTeamleads());
    }

    public function testFormerTeamLeadIsReplacedAndLosesTheRole(): void
    {
        $formerLead = self::user('old-boss@aarhus.dk');
        $formerLead->addRole(User::ROLE_TEAMLEAD);

        // A team can carry a lead the claims no longer name: an admin added one, or
        // the row predates the manager email becoming part of the team lookup.
        $team = self::team('ITK Development (6530, boss@aarhus.dk)', 7);
        $team->addTeamlead($formerLead);

        $manager = self::user('boss@aarhus.dk');

        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService(['boss@aarhus.dk' => $manager], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository([self::meta($team, 6530, 'boss@aarhus.dk', 'Big Boss')], $savedMeta),
        );
        $user = self::user('jane@aarhus.dk');

        $service->hydrate($user, new SamlDTO(self::attributes([
            'personaleLederUPN' => ['boss@aarhus.dk'],
            'personaleLederDisplayName' => ['Big Boss'],
        ])));

        // The manager from the claims takes over and the stale lead leaves the team
        // entirely.
        self::assertSame([$manager], $team->getTeamleads());
        self::assertNotContains($formerLead, $team->getUsers());
        // No team is left for the former lead, so the role goes too - and the
        // demoted user is the one that must be saved.
        self::assertFalse($formerLead->hasRole(User::ROLE_TEAMLEAD));
        self::assertContains($formerLead, $updatedUsers);
    }

    public function testMembershipsOutsideTheCurrentTeamArePrunedButLeadershipIsKept(): void
    {
        $memberTeam = self::team('ITK Development (6530)', 7);
        $staleTeam = self::team('Some Other Team (4711)', 42);
        $leadTeam = self::team('ITK (1103)', 99);

        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService([], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository([self::meta($memberTeam, 6530)], $savedMeta),
        );

        $user = self::user('jane@aarhus.dk');
        $user->addTeam($staleTeam);
        $leadTeam->addTeamlead($user);

        $service->hydrate($user, new SamlDTO(self::attributes()));

        // A user is a plain member of exactly one team - the one the claims name -
        // but team leadership of other teams is not ours to remove.
        self::assertContains($memberTeam, $user->getTeams());
        self::assertContains($leadTeam, $user->getTeams());
        self::assertNotContains($staleTeam, $user->getTeams());
        self::assertNotContains($user, $staleTeam->getUsers());
    }

    public function testTeamLeadGetsOwnTeamAndUsersOutsideTheEmployeeListAreRemoved(): void
    {
        $employee = self::user('kept@aarhus.dk');
        $formerEmployee = self::user('gone@aarhus.dk');

        // The team the user is team lead for is the lowest org unit (6530); the
        // team the user is a member of is the one above it (1103).
        $leadTeam = self::team('ITK Development (6530)', 9);
        $leadTeam->addUser($employee);
        $leadTeam->addUser($formerEmployee);
        $memberTeam = self::team('ITK (1103)', 3);

        $savedTeams = [];
        $savedUsers = [];
        $updatedUsers = [];
        $savedMeta = [];

        $service = new SamlDataHydrateService(
            $this->userService([], $savedUsers, $updatedUsers),
            $this->teamRepository($savedTeams),
            $this->metaRepository(
                // A team lead's own team is keyed by their own email, the team they are
                // a member of by their manager's - which the claims leave empty here.
                [self::meta($leadTeam, 6530, 'jane@aarhus.dk', 'Jane Doe'), self::meta($memberTeam, 1103)],
                $savedMeta,
            ),
        );

        $user = self::user('jane@aarhus.dk');

        $service->hydrate($user, new SamlDTO(self::attributes([
            'employeeList' => ['kept@aarhus.dk'],
        ])));

        self::assertTrue($user->hasRole(User::ROLE_TEAMLEAD));
        self::assertSame([$user], $leadTeam->getTeamleads());
        self::assertContains($memberTeam, $user->getTeams());

        // Employees that left the manager must lose the team, the manager keeps it.
        self::assertContains($employee, $leadTeam->getUsers());
        self::assertContains($user, $leadTeam->getUsers());
        self::assertNotContains($formerEmployee, $leadTeam->getUsers());
    }
}
