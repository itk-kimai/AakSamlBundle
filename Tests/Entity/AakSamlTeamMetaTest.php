<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\Entity;

use App\Entity\Team;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlTeamMeta;
use KimaiPlugin\AakSamlBundle\Service\SamlDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AakSamlTeamMeta::class)]
final class AakSamlTeamMetaTest extends TestCase
{
    /**
     * Claims for a user placed five levels down: 1001 (Aarhus Kommune) /
     * 1004 (Kultur og Borgerservice) / 1012 (Borgerservice og Biblioteker) /
     * 1103 (ITK) / 6530 (ITK Development).
     *
     * @param array<string, list<string>> $overrides
     */
    private static function samlDto(array $overrides = []): SamlDTO
    {
        return new SamlDTO([
            'http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname' => ['az12345'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => ['Jane Doe'],
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['jane@aarhus.dk'],
            'personaleLederUPN' => ['boss@aarhus.dk'],
            'personaleLederDisplayName' => ['Big Boss'],
            'companyname' => ['Aarhus Kommune'],
            'division' => ['Kultur og Borgerservice'],
            'department' => ['Borgerservice og Biblioteker'],
            'extensionAttribute12' => ['ITK'],
            'Office' => ['ITK Development'],
            'extensionAttribute7' => ['1001;1004;1012;1103;6530'],
            'employeeList' => [''],
            ...$overrides,
        ]);
    }

    private static function meta(int $orgUnitId): AakSamlTeamMeta
    {
        return new AakSamlTeamMeta(new Team(name: 'ITK Development (6530)'), self::samlDto(), $orgUnitId, 'boss@aarhus.dk', 'Big Boss');
    }

    public function testOrgUnitDepthHydratesEveryLevelAboveIt(): void
    {
        // 6530 is the fifth (and lowest) id, so all five levels are known.
        $meta = self::meta(6530);

        self::assertSame(6530, $meta->getOrgUnitId());
        self::assertSame('boss@aarhus.dk', $meta->getManagerEmail());
        self::assertSame('Big Boss', $meta->getManagerName());
        self::assertSame([1001, 1004, 1012, 1103, 6530], $meta->getDepartmentIds());

        self::assertSame(1001, $meta->getCompanyId());
        self::assertSame('Aarhus Kommune', $meta->getCompanyName());
        self::assertSame(1004, $meta->getDivisionId());
        self::assertSame('Kultur og Borgerservice', $meta->getDivisionName());
        self::assertSame(1012, $meta->getDepartmentId());
        self::assertSame('Borgerservice og Biblioteker', $meta->getDepartmentName());
        self::assertSame(1103, $meta->getSubDepartmentId());
        self::assertSame('ITK', $meta->getSubDepartmentName());
        self::assertSame(6530, $meta->getOfficeId());
        self::assertSame('ITK Development', $meta->getOfficeName());
    }

    public function testMemberTeamOfATeamLeadStopsOneLevelAboveTheLowest(): void
    {
        // A team lead is a member of the team one level up (1103, "ITK"), so the
        // lowest level must not be hydrated.
        $meta = self::meta(1103);

        self::assertSame(1103, $meta->getSubDepartmentId());
        self::assertSame('ITK', $meta->getSubDepartmentName());
        self::assertNull($meta->getOfficeId());
        self::assertNull($meta->getOfficeName());
    }

    public function testTopLevelOrgUnitHydratesTheCompanyOnly(): void
    {
        // 1001 is the first id, depth 0: nothing below the company is known.
        $meta = self::meta(1001);

        self::assertSame(1001, $meta->getCompanyId());
        self::assertSame('Aarhus Kommune', $meta->getCompanyName());
        self::assertNull($meta->getDivisionId());
        self::assertNull($meta->getDivisionName());
        self::assertNull($meta->getDepartmentId());
        self::assertNull($meta->getDepartmentName());
        self::assertNull($meta->getSubDepartmentId());
        self::assertNull($meta->getSubDepartmentName());
        self::assertNull($meta->getOfficeId());
        self::assertNull($meta->getOfficeName());
    }

    public function testOrgUnitOutsideTheHierarchyLeavesTheLowerLevelsNull(): void
    {
        // Regression guard: an id that is not in "extensionAttribute7" has no depth.
        // The levels below the company used to be left uninitialised, so the first
        // read threw "must not be accessed before initialization".
        $meta = self::meta(9999);

        self::assertSame(9999, $meta->getOrgUnitId());
        self::assertSame(1001, $meta->getCompanyId());
        self::assertNull($meta->getDivisionId());
        self::assertNull($meta->getDivisionName());
        self::assertNull($meta->getDepartmentId());
        self::assertNull($meta->getDepartmentName());
        self::assertNull($meta->getSubDepartmentId());
        self::assertNull($meta->getSubDepartmentName());
        self::assertNull($meta->getOfficeId());
        self::assertNull($meta->getOfficeName());
    }

    public function testReHydratingWithAShallowerOrgUnitClearsTheDeeperLevels(): void
    {
        // A user who moves up the hierarchy must not keep the old, deeper values.
        $meta = self::meta(6530);
        $meta->setValues(self::samlDto(), 1004, 'boss@aarhus.dk', 'Big Boss');

        self::assertSame(1004, $meta->getDivisionId());
        self::assertSame('Kultur og Borgerservice', $meta->getDivisionName());
        self::assertNull($meta->getDepartmentId());
        self::assertNull($meta->getSubDepartmentId());
        self::assertNull($meta->getOfficeId());
        self::assertNull($meta->getOfficeName());
    }

    public function testTeamAndManagerValuesAreOverwrittenOnReHydration(): void
    {
        $meta = self::meta(6530);
        $team = new Team(name: 'ITK Development (6530, new-boss@aarhus.dk)');

        $meta->setTeam($team);
        $meta->setValues(self::samlDto([
            'personaleLederUPN' => ['new-boss@aarhus.dk'],
            'personaleLederDisplayName' => ['New Boss'],
        ]), 6530, 'new-boss@aarhus.dk', 'New Boss');

        self::assertSame($team, $meta->getTeam());
        self::assertSame('new-boss@aarhus.dk', $meta->getManagerEmail());
        self::assertSame('New Boss', $meta->getManagerName());
    }
}
