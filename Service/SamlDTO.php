<?php

namespace KimaiPlugin\AakSamlBundle\Service;

use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;

class SamlDTO
{
    // Aar SAML Attributes
    private const AZ_IDENT_ATTRIBUTE = 'http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname';
    private const NAME_ATTRIBUTE = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name';
    private const EMAIL_ADDRESS_ATTRIBUTE = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress';
    private const MANAGER_EMAIL_ATTRIBUTE = 'personaleLederUPN';
    private const MANAGER_NAME_ATTRIBUTE = 'personaleLederDisplayName';
    private const COMPANY_NAME_ATTRIBUTE = 'companyname';
    private const DIVISION_ATTRIBUTE = 'division';
    private const DEPARTMENT_ATTRIBUTE = 'department';
    private const SUB_DEPARTMENT_ATTRIBUTE = 'extensionAttribute12';
    private const OFFICE_ATTRIBUTE = 'Office';
    private const ID_ARRAY_ATTRIBUTE = 'extensionAttribute7';

    private const EMPLOYEE_LIST_ATTRIBUTE = 'employeeList';

    public readonly string $azIdent;

    public readonly string $displayName;

    public readonly string $emailAddress;

    public readonly string $managerEmail;
    public readonly string $managerName;

    /**
     * @var ?int "Kommune" id, E.g "1001"
     */
    public readonly ?int $companyId;

    /**
     * @var ?string "Kommune" name, E.g. "Aarhus Kommune"
     */
    public readonly ?string $company;

    /**
     * @var ?int "Magistrat" id, E.g. "1004"
     */
    public readonly ?int $divisionId;

    /**
     * @var ?string "Magistrat" name, E.g. "Kultur og Borgerservice"
     */
    public readonly ?string $division;

    /**
     * @var ?int "Afdelings" id, E.g. "1012"
     */
    public readonly ?int $departmentId;

    /**
     * @var ?string "Afdelings" name, E.g. "Kultur og Borgerservice"
     */
    public readonly ?string $department;

    /**
     * @var ?int "Under afdelings" id, E.g. "1103"
     */
    public readonly ?int $subDepartmentId;

    /**
     * @var ?string "Under afdelings" name, E.g. "ITK"
     */
    public readonly ?string $subDepartment;

    /**
     * @var ?int "Office" id, E.g. "6530"
     */
    public readonly ?int $officeId;

    /**
     * @var ?string "Office" name, E.g. "ITK Development"
     */
    public readonly ?string $office;

    /**
     * @var array<int> of hierarchical organization id's going top to bottom
     */
    public readonly array $departmentIds;

    /**
     * @var array<string> og emails for managers employees. Empty for non-managers.
     */
    public readonly array $employeeList;

    /**
     * SamlDTO constructor.
     *
     * @param array $samlAttributes
     *
     * @throws AakSamlException
     */
    public function __construct(array $samlAttributes)
    {
        $this->azIdent = $this->getAttributeValue(self::AZ_IDENT_ATTRIBUTE, $samlAttributes);
        $this->displayName = $this->getAttributeValue(self::NAME_ATTRIBUTE, $samlAttributes);
        $this->emailAddress = $this->getAttributeValue(self::EMAIL_ADDRESS_ATTRIBUTE, $samlAttributes);

        $this->managerEmail = $this->getAttributeValue(self::MANAGER_EMAIL_ATTRIBUTE, $samlAttributes);
        $this->managerName = $this->getAttributeValue(self::MANAGER_NAME_ATTRIBUTE, $samlAttributes);

        $this->company = $this->getAttributeValue(self::COMPANY_NAME_ATTRIBUTE, $samlAttributes);
        $this->division = $this->getAttributeValue(self::DIVISION_ATTRIBUTE, $samlAttributes);
        $this->department = $this->getAttributeValue(self::DEPARTMENT_ATTRIBUTE, $samlAttributes);
        $this->subDepartment = $this->getAttributeValue(self::SUB_DEPARTMENT_ATTRIBUTE, $samlAttributes);
        $this->office = $this->getAttributeValue(self::OFFICE_ATTRIBUTE, $samlAttributes);

        // Hydrate id values from id array. E.g. [1001;1004;1012;1103;6530].
        $ids = \explode(';', $this->getAttributeValue(self::ID_ARRAY_ATTRIBUTE, $samlAttributes));

        // Convert all array values to integer or throw AakSamlException
        $departmentIds = [];
        foreach ($ids as $id) {
            $value = intval($id);

            if (0 > $value) {
                throw new AakSamlException(sprintf('Invalid id value "%s" - Expected a positive integer.', $value));
            }

            if (0 === $value) {
                throw new AakSamlException(sprintf('Invalid id value "%s" - Cannot convert to integer.', $value));
            }

            $departmentIds[] = $value;
        }

        $this->departmentIds = $departmentIds;

        if (0 === count($this->departmentIds)) {
            throw new AakSamlException(sprintf('No organization ids given in claims: "%s"', $this->getAttributeValue(self::ID_ARRAY_ATTRIBUTE, $samlAttributes)));
        }

        // We can map claims from level 1-5. If we see id's for deeper levels we don't know the corresponding claim for
        // department name.
        $this->companyId = $this->departmentIds[0];
        $this->divisionId = $this->departmentIds[1] ?? null;
        $this->departmentId = $this->departmentIds[2] ?? null;
        $this->subDepartmentId = $this->departmentIds[3] ?? null;
        $this->officeId = $this->departmentIds[4] ?? null;

        $employeeEmailArray = \explode(';', $this->getAttributeValue(self::EMPLOYEE_LIST_ATTRIBUTE, $samlAttributes));
        $this->employeeList = \array_filter($employeeEmailArray, function ($value) {
            return '' !== $value;
        });
    }

    /**
     * Is the user a team lead.
     *
     * @return bool
     */
    public function isTeamLead(): bool
    {
        return \count($this->employeeList) > 0;
    }

    /**
     * Get the organization id. This will be the last of the ids given in the claims.
     *
     * @return int
     */
    public function getOrganizationUnitId(): int
    {
        $id = array_slice($this->departmentIds, -1, 1);

        return $id[0];
    }

    /**
     * Get the organization name the user is placed in. For levels 1-5 we know the claim to map to. For deeper levels
     * the claims are unknown.
     *
     * @return string
     */
    public function getOrganizationUnitName(): string
    {
        $depth = \count($this->departmentIds);

        return $this->getOrgUnitName($depth);
    }

    /**
     * Get the organization id the user is a member in. This will be the last of the ids given in the claims for
     * employees and the second to last for team leads.
     *
     * @return int
     *
     * @throws AakSamlException
     */
    public function getMemberOrganizationUnitId(): int
    {
        if ($this->isTeamLead()) {
            $id = array_slice($this->departmentIds, -2, 1);
        } else {
            $id = array_slice($this->departmentIds, -1, 1);
        }

        if (1 !== count($id)) {
            throw new AakSamlException('Cannot determine organization id from id (extensionAttribute7) array.');
        }

        return $id[0];
    }

    /**
     * Get the organization name the user is a member in. For team leads this is the second last org claim.
     *
     * @return string
     */
    public function getMemberOrganizationUnitName(): string
    {
        $depth = \count($this->departmentIds);

        if ($this->isTeamLead()) {
            --$depth;
        }

        return $this->getOrgUnitName($depth);
    }

    /**
     * Get the organization id the user is a team lead in. This will be the last of the ids given in the claims.
     *
     * @return int
     *
     * @throws AakSamlException
     */
    public function getTeamLeadOrganizationUnitId(): int
    {
        if (!$this->isTeamLead()) {
            throw new AakSamlException('Cannot get team lead org from none team lead user.');
        }

        $id = array_slice($this->departmentIds, -1, 1);

        if (1 !== count($id)) {
            throw new AakSamlException('Cannot determine organization id from id (extensionAttribute7) array.');
        }

        return $id[0];
    }

    /**
     * Get the organization name the user is team lead for. For levels 1-5 we know the claim to map to. For deeper levels
     * the claims are unknown.
     *
     * @return string
     *
     * @throws AakSamlException
     */
    public function getTeamLeadOrganizationUnitName(): string
    {
        if (!$this->isTeamLead()) {
            throw new AakSamlException('Cannot get team lead org from none team lead user.');
        }

        $depth = \count($this->departmentIds);

        return $this->getOrgUnitName($depth);
    }

    /**
     * Get the organization name the user is placed in. For levels deeper than 5 we fall back to 'office'.
     *
     * @param int $depth
     *
     * @return string
     */
    private function getOrgUnitName(int $depth): string
    {
        $name = match ($depth) {
            1 => $this->company,
            2 => $this->division,
            3 => $this->department,
            4 => $this->subDepartment,
            default => $this->office
        };

        return $name ?? '';
    }

    /**
     * @throws AakSamlException
     */
    private function getAttributeValue(string $attributeName, array $samlAttributes): string
    {
        if (array_key_exists($attributeName, $samlAttributes)) {
            $subArray = $samlAttributes[$attributeName];

            if (1 === count($subArray)) {
                $value = $subArray[0];
            } else {
                throw new AakSamlException(sprintf('Unexpected number of values for SAML attribute: %s Expected 1, %d given.', $attributeName, count($subArray)));
            }
        } else {
            throw new AakSamlException(sprintf('Missing SAML attribute: %s', $attributeName));
        }

        return $value;
    }
}
