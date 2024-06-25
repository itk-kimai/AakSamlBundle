<?php

namespace KimaiPlugin\AakSamlBundle\Service;

use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;

class SamlDTO
{
    // Aar SAML Attributes
    private const AZ_IDENT_ATTRIBUTE = 'http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname';
    private const NAME_ATTRIBUTE = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name';
    private const EMAIL_ADDRESS_ATTRIBUTE = 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress';
    private const MANAGER_NAME_ATTRIBUTE = 'managerdisplayname';
    private const COMPANY_NAME_ATTRIBUTE = 'companyname';
    private const DIVISION_ATTRIBUTE = 'division';
    private const DEPARTMENT_ATTRIBUTE = 'department';
    private const SUB_DEPARTMENT_ATTRIBUTE = 'extensionAttribute12';
    private const OFFICE_ATTRIBUTE = 'Office';
    private const ID_ARRAY_ATTRIBUTE = 'extensionAttribute7';

    public readonly string $azIdent;

    public readonly string $name;

    public readonly string $emailAddress;

    public readonly string $managerName;

    /**
     * @var int "Kommune" id, E.g "1001"
     */
    public readonly int $companyId;

    /**
     * @var string "Kommune" name, E.g. "Aarhus Kommune"
     */
    public readonly string $company;

    /**
     * @var int "Magistrat" id, E.g. "1004"
     */
    public readonly int $divisionId;

    /**
     * @var string "Magistrat" name, E.g. "Kultur og Borgerservice"
     */
    public readonly string $division;

    /**
     * @var int "Afdelings" id, E.g. "1012"
     */
    public readonly int $departmentId;

    /**
     * @var string "Afdelings" name, E.g. "Kultur og Borgerservice"
     */
    public readonly string $department;

    /**
     * @var int "Under afdelings" id, E.g. "1103"
     */
    public readonly int $subDepartmentId;

    /**
     * @var string "Under afdelings" name, E.g. "ITK"
     */
    public readonly string $subDepartment;

    /**
     * @var int "Office" id, E.g. "6530"
     */
    public readonly int $officeId;

    /**
     * @var string "Office" name, E.g. "ITK Development"
     */
    public readonly string $office;

    /**
     * @throws AakSamlException
     */
    public function __construct(array $samlAttributes)
    {
        $this->azIdent = $this->getAttributeValue(self::AZ_IDENT_ATTRIBUTE, $samlAttributes);
        $this->name = $this->getAttributeValue(self::NAME_ATTRIBUTE, $samlAttributes);
        $this->emailAddress = $this->getAttributeValue(self::EMAIL_ADDRESS_ATTRIBUTE, $samlAttributes);

        $this->managerName = $this->getAttributeValue(self::MANAGER_NAME_ATTRIBUTE, $samlAttributes);

        $this->company = $this->getAttributeValue(self::COMPANY_NAME_ATTRIBUTE, $samlAttributes);
        $this->division = $this->getAttributeValue(self::DIVISION_ATTRIBUTE, $samlAttributes);
        $this->department = $this->getAttributeValue(self::DEPARTMENT_ATTRIBUTE, $samlAttributes);
        $this->subDepartment = $this->getAttributeValue(self::SUB_DEPARTMENT_ATTRIBUTE, $samlAttributes);
        $this->office = $this->getAttributeValue(self::OFFICE_ATTRIBUTE, $samlAttributes);

        $ids = $this->getAttributeValue(self::ID_ARRAY_ATTRIBUTE, $samlAttributes);
        $this->hydrateIdValues($ids);
    }

    /**
     * Hydrate id values from id array. E.g. [1001;1004;1012;1103;6530].
     *
     * @param string $idValues
     *
     * @return void
     *
     * @throws AakSamlException
     */
    private function hydrateIdValues(string $idValues): void
    {
        $ids = \explode(';', $idValues);

        if (\count($ids) < 5) {
            throw new AakSamlException('Unexpected number of values for SAML ids. Expected 5 or more, '.count($ids).' given.');
        }

        \array_walk($ids, function (&$value) {
            $value = intval($value);

            if (0 > $value) {
                throw new AakSamlException('Invalid id value:'.$value.' Expected a positive integer.');
            }

            if (0 === $value) {
                throw new AakSamlException('Invalid id value:'.$value.' Cannot convert to integer.');
            }
        });

        $this->companyId = $ids[0];
        $this->divisionId = $ids[1];
        $this->departmentId = $ids[2];
        $this->subDepartmentId = $ids[3];
        $this->officeId = $ids[4];
    }

    /**
     * @throws AakSamlException
     */
    private function getAttributeValue(string $attributeName, array $samlAttributes): mixed
    {
        if (array_key_exists($attributeName, $samlAttributes)) {
            $subArray = $samlAttributes[$attributeName];

            if (1 === count($subArray)) {
                return $subArray[0];
            } else {
                throw new AakSamlException('Unexpected number of values for SAML attribute: '.$attributeName.' Expected 1, '.count($subArray).' given.');
            }
        } else {
            throw new AakSamlException('Missing SAML attribute: '.$attributeName);
        }
    }
}
