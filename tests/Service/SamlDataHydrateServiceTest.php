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

    private function newService(TeamRepository $teamRepository): SamlDataHydrateService
    {
        $userService = $this->createStub(UserService::class);
        // Return the user passed in so the hydrate flow can continue.
        $userService->method('updateUser')->willReturnArgument(0);
        $userService->method('findUserByEmail')->willReturn(null);

        $metaRepository = $this->createStub(AakSamlTeamMetaRepository::class);
        $metaRepository->method('findOneBy')->willReturn(null);

        return new SamlDataHydrateService($userService, $teamRepository, $metaRepository);
    }

    public function testOverlongValuesAreTruncatedToKimaiLimits(): void
    {
        $savedTeams = [];
        $teamRepository = $this->createStub(TeamRepository::class);
        $teamRepository->method('saveTeam')->willReturnCallback(
            function (Team $team) use (&$savedTeams): void {
                $savedTeams[] = $team;
            }
        );

        $service = $this->newService($teamRepository);
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
        $teamRepository = $this->createStub(TeamRepository::class);
        $service = $this->newService($teamRepository);
        $user = new User();

        // A manager email longer than the 64-char username limit cannot be
        // truncated (username/email are unique), so hydration must fail clearly.
        $longEmail = str_repeat('a', 60) . '@aarhus.dk'; // 70 chars

        $this->expectException(AakSamlException::class);
        $this->expectExceptionMessage('username limit');

        $service->hydrate($user, new SamlDTO(self::attributes([
            'personaleLederUPN' => [$longEmail],
            'personaleLederDisplayName' => ['A Manager'],
        ])));
    }
}
