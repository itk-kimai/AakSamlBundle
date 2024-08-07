<?php

namespace KimaiPlugin\AakSamlBundle\Entity;

use App\Entity\Team;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlTeamMetaRepository;
use KimaiPlugin\AakSamlBundle\Service\SamlDTO;

#[ORM\Table(name: 'kimai2_aak_saml_team_meta')]
#[ORM\UniqueConstraint(columns: ['org_unit_id', 'manager_email'])]
#[ORM\Entity(repositoryClass: AakSamlTeamMetaRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[Serializer\ExclusionPolicy('all')]
class AakSamlTeamMeta
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private int $id;

    #[ORM\OneToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(name: 'team_id', referencedColumnName: 'id', unique: true, nullable: false)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private Team $team;

    #[ORM\Column(name: 'org_unit_id', type: Types::INTEGER, nullable: false)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private int $orgUnitId;

    #[ORM\Column(name: 'manager_email', type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private string $managerEmail;

    #[ORM\Column(name: 'manager_name', type: Types::STRING, length: 255)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private string $managerName;

    #[ORM\Column(name: 'company_id', type: Types::INTEGER, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?int $companyId;

    #[ORM\Column(name: 'company_name', type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $companyName;

    #[ORM\Column(name: 'division_id', type: Types::INTEGER, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?int $divisionId;

    #[ORM\Column(name: 'division_name', type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $divisionName;

    #[ORM\Column(name: 'dept_id', type: Types::INTEGER, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?int $departmentId;

    #[ORM\Column(name: 'dept_name', type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $departmentName;

    #[ORM\Column(name: 'sub_dept_id', type: Types::INTEGER, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?int $subDepartmentId;

    #[ORM\Column(name: 'sub_dept_name', type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $subDepartmentName;

    #[ORM\Column(name: 'office_id', type: Types::INTEGER, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?int $officeId;

    #[ORM\Column(name: 'office_name', type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $officeName;

    #[ORM\Column(name: 'dept_ids', type: Types::JSON, nullable: false)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private array $departmentIds = [];

    public function __construct(Team $team, SamlDTO $samlDTO, int $orgUnitId, string $managerEmail, string $managerName)
    {
        $this->team = $team;

        $this->setValues($samlDTO, $orgUnitId, $managerEmail, $managerName);
    }

    public function setValues(SamlDTO $samlDTO, int $orgUnitId, string $managerEmail, string $managerName): void
    {
        $this->orgUnitId = $orgUnitId;
        $this->departmentIds = $samlDTO->departmentIds;

        $this->managerEmail = $managerEmail;
        $this->managerName = $managerName;

        // If we are hydrating the "member" team for a team lead we don't hydrate the lowest department level.
        // Get the "depth" to go to by finding the position of "$orgUnitId" in the $departmentIds.
        $depth = array_search($orgUnitId, $this->departmentIds);

        $this->companyId = $samlDTO->companyId;
        $this->companyName = $samlDTO->company;

        if ($depth >= 1) {
            $this->divisionId = $samlDTO->divisionId;
            $this->divisionName = $samlDTO->division;
        }

        if ($depth >= 2) {
            $this->departmentId = $samlDTO->departmentId;
            $this->departmentName = $samlDTO->department;
        }

        if ($depth >= 3) {
            $this->subDepartmentId = $samlDTO->subDepartmentId;
            $this->subDepartmentName = $samlDTO->subDepartment;
        }

        if ($depth >= 4) {
            $this->officeId = $samlDTO->officeId;
            $this->officeName = $samlDTO->office;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): void
    {
        $this->team = $team;
    }

    public function getManagerEmail(): string
    {
        return $this->managerEmail;
    }

    public function setManagerEmail(string $managerEmail): void
    {
        $this->managerEmail = $managerEmail;
    }

    public function getManagerName(): string
    {
        return $this->managerName;
    }

    public function setManagerName(string $managerName): void
    {
        $this->managerName = $managerName;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function setCompanyId(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): void
    {
        $this->companyName = $companyName;
    }

    public function getDivisionId(): ?int
    {
        return $this->divisionId;
    }

    public function setDivisionId(?int $divisionId): void
    {
        $this->divisionId = $divisionId;
    }

    public function getDivisionName(): ?string
    {
        return $this->divisionName;
    }

    public function setDivisionName(?string $divisionName): void
    {
        $this->divisionName = $divisionName;
    }

    public function getDepartmentId(): ?int
    {
        return $this->departmentId;
    }

    public function setDepartmentId(?int $departmentId): void
    {
        $this->departmentId = $departmentId;
    }

    public function getDepartmentName(): ?string
    {
        return $this->departmentName;
    }

    public function setDepartmentName(?string $departmentName): void
    {
        $this->departmentName = $departmentName;
    }

    public function getSubDepartmentId(): ?int
    {
        return $this->subDepartmentId;
    }

    public function setSubDepartmentId(?int $subDepartmentId): void
    {
        $this->subDepartmentId = $subDepartmentId;
    }

    public function getSubDepartmentName(): ?string
    {
        return $this->subDepartmentName;
    }

    public function setSubDepartmentName(?string $subDepartmentName): void
    {
        $this->subDepartmentName = $subDepartmentName;
    }

    public function getOfficeId(): ?int
    {
        return $this->officeId;
    }

    public function setOfficeId(?int $officeId): void
    {
        $this->officeId = $officeId;
    }

    public function getOfficeName(): ?string
    {
        return $this->officeName;
    }

    public function setOfficeName(?string $officeName): void
    {
        $this->officeName = $officeName;
    }

    public function getDepartmentIds(): array
    {
        return $this->departmentIds;
    }

    public function setDepartmentIds(array $departmentIds): void
    {
        $this->departmentIds = $departmentIds;
    }

    public function getOrgUnitId(): int
    {
        return $this->orgUnitId;
    }

    public function setOrgUnitId(int $orgUnitId): void
    {
        $this->orgUnitId = $orgUnitId;
    }
}
