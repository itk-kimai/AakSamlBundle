<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\Service;

use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;
use KimaiPlugin\AakSamlBundle\Service\SamlDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamlDTO::class)]
final class SamlDTOTest extends TestCase
{
    /**
     * Build a valid set of SAML attributes, applying any overrides.
     *
     * Each attribute is a single-element array, as delivered by the SAML response.
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
            'personaleLederUPN' => ['boss@aarhus.dk'],
            'personaleLederDisplayName' => ['The Boss'],
            'companyname' => ['Aarhus Kommune'],
            'division' => ['Kultur og Borgerservice'],
            'department' => ['Borgerservice og Biblioteker'],
            'extensionAttribute12' => ['ITK'],
            'Office' => ['ITK Development'],
            'extensionAttribute7' => ['1001;1004;1012'],
            'employeeList' => [''],
            ...$overrides,
        ];
    }

    public function testParsesIdentityAndManagerClaims(): void
    {
        $dto = new SamlDTO(self::attributes());

        self::assertSame('az12345', $dto->azIdent);
        self::assertSame('Jane Doe', $dto->displayName);
        self::assertSame('jane@aarhus.dk', $dto->emailAddress);
        self::assertSame('boss@aarhus.dk', $dto->managerEmail);
        self::assertSame('The Boss', $dto->managerName);
    }

    public function testParsesOrganizationIdsFromIdArray(): void
    {
        $dto = new SamlDTO(self::attributes(['extensionAttribute7' => ['1001;1004;1012']]));

        self::assertSame([1001, 1004, 1012], $dto->departmentIds);
        // The organization unit id is the last id in the claim.
        self::assertSame(1012, $dto->getOrganizationUnitId());
    }

    /**
     * @param non-empty-string $ids
     */
    #[DataProvider('orgUnitNameByDepthProvider')]
    public function testOrganizationUnitNameMapsToDepth(string $ids, string $expectedName): void
    {
        $dto = new SamlDTO(self::attributes([
            'extensionAttribute7' => [$ids],
            'companyname' => ['Company'],
            'division' => ['Division'],
            'department' => ['Department'],
            'extensionAttribute12' => ['SubDepartment'],
            'Office' => ['Office'],
        ]));

        self::assertSame($expectedName, $dto->getOrganizationUnitName());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function orgUnitNameByDepthProvider(): iterable
    {
        yield 'depth 1 -> company' => ['1001', 'Company'];
        yield 'depth 2 -> division' => ['1001;1004', 'Division'];
        yield 'depth 3 -> department' => ['1001;1004;1012', 'Department'];
        yield 'depth 4 -> sub department' => ['1001;1004;1012;1103', 'SubDepartment'];
        // Depth >= 5 always resolves to office; depths 5-9 make up the bulk of
        // real logins (see the claims-log analysis).
        yield 'depth 5 -> office' => ['1001;1004;1012;1103;6530', 'Office'];
        yield 'depth 6 -> office' => ['1001;1003;9772;9778;2092;2720', 'Office'];
        yield 'depth 7 -> office' => ['1001;1003;9776;9795;2199;10041;10073', 'Office'];
        yield 'depth 8 -> office' => ['1001;1005;1169;15497;15995;16051;1273;16048', 'Office'];
        yield 'depth 9 -> office' => ['1001;1003;9776;9795;2099;9914;10040;3012;4262', 'Office'];
    }

    public function testIsTeamLeadReflectsEmployeeList(): void
    {
        $employee = new SamlDTO(self::attributes(['employeeList' => ['']]));
        self::assertFalse($employee->isTeamLead());

        $lead = new SamlDTO(self::attributes(['employeeList' => ['emp1@aarhus.dk;emp2@aarhus.dk']]));
        self::assertTrue($lead->isTeamLead());
        self::assertTrue($lead->hasEmployee('emp1@aarhus.dk'));
        self::assertFalse($lead->hasEmployee('stranger@aarhus.dk'));
    }

    public function testMemberOrganizationForNonTeamLeadIsLastId(): void
    {
        $dto = new SamlDTO(self::attributes([
            'extensionAttribute7' => ['1001;1004;1012'],
            'employeeList' => [''],
        ]));

        self::assertSame(1012, $dto->getMemberOrganizationUnitId());
        self::assertSame('Borgerservice og Biblioteker', $dto->getMemberOrganizationUnitName());
    }

    public function testMemberOrganizationForTeamLeadIsSecondToLastId(): void
    {
        $dto = new SamlDTO(self::attributes([
            'extensionAttribute7' => ['1001;1004;1012'],
            'employeeList' => ['emp1@aarhus.dk'],
        ]));

        // A team lead is a member of the organization one level above the one they lead.
        self::assertSame(1004, $dto->getMemberOrganizationUnitId());
        self::assertSame('Kultur og Borgerservice', $dto->getMemberOrganizationUnitName());
    }

    public function testTeamLeadOrganizationIsLastId(): void
    {
        $dto = new SamlDTO(self::attributes([
            'extensionAttribute7' => ['1001;1004;1012'],
            'employeeList' => ['emp1@aarhus.dk'],
        ]));

        self::assertSame(1012, $dto->getTeamLeadOrganizationUnitId());
        self::assertSame('Borgerservice og Biblioteker', $dto->getTeamLeadOrganizationUnitName());
    }

    public function testTeamLeadAccessorsThrowForNonTeamLead(): void
    {
        $dto = new SamlDTO(self::attributes(['employeeList' => ['']]));

        $this->expectException(AakSamlException::class);
        $dto->getTeamLeadOrganizationUnitId();
    }

    public function testMissingAttributeThrows(): void
    {
        $attributes = self::attributes();
        unset($attributes['companyname']);

        $this->expectException(AakSamlException::class);
        $this->expectExceptionMessage('Missing SAML attribute: companyname');
        new SamlDTO($attributes);
    }

    public function testMultiValueAttributeThrows(): void
    {
        $this->expectException(AakSamlException::class);
        $this->expectExceptionMessage('Unexpected number of values');
        new SamlDTO(self::attributes(['companyname' => ['a', 'b']]));
    }

    /**
     * @param non-empty-string $ids
     */
    #[DataProvider('invalidIdProvider')]
    public function testInvalidOrganizationIdsThrow(string $ids): void
    {
        $this->expectException(AakSamlException::class);
        new SamlDTO(self::attributes(['extensionAttribute7' => [$ids]]));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIdProvider(): iterable
    {
        yield 'negative id' => ['-1'];
        yield 'non-numeric id' => ['abc'];
        yield 'zero id' => ['0'];
        yield 'empty id' => [''];
    }

    /**
     * A deep-hierarchy team lead is the most common real-world scenario
     * (see the claims-log analysis: depth 6, team lead). The org name resolves
     * via office for any depth >= 5, the led organisation is the last id and
     * the membership is one level up (the second-to-last id).
     */
    public function testDeepTeamLeadScenarioResolvesViaOffice(): void
    {
        $dto = new SamlDTO(self::attributes([
            'extensionAttribute7' => ['1001;1003;9772;9778;2092;2720'],
            'division' => ['Børn og Unge'],
            'department' => ['Dagtilbudsområde'],
            'extensionAttribute12' => ['Dagtilbud'],
            'Office' => ['Børnehuset'],
            'employeeList' => ['emp1@aarhus.dk;emp2@aarhus.dk;emp3@aarhus.dk'],
        ]));

        self::assertTrue($dto->isTeamLead());
        // Depth 6 -> office for every name lookup.
        self::assertSame('Børnehuset', $dto->getOrganizationUnitName());
        self::assertSame('Børnehuset', $dto->getTeamLeadOrganizationUnitName());
        self::assertSame('Børnehuset', $dto->getMemberOrganizationUnitName());
        // Leads the last org; is a member of the one above it.
        self::assertSame(2720, $dto->getTeamLeadOrganizationUnitId());
        self::assertSame(2092, $dto->getMemberOrganizationUnitId());
    }

    /**
     * Empty-string org-level values are common and valid in production (e.g. a
     * user with no `department`). They must be returned as-is, not treated as
     * missing.
     */
    public function testEmptyOrganizationLevelNameIsReturnedVerbatim(): void
    {
        $dto = new SamlDTO(self::attributes([
            'extensionAttribute7' => ['1001;1004;1012'],
            'department' => [''],
            'employeeList' => [''],
        ]));

        // Depth 3 maps to department, which is an empty string here.
        self::assertSame('', $dto->getOrganizationUnitName());
    }
}
