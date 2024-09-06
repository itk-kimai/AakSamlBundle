<?php

namespace KimaiPlugin\AakSamlBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlClaimsLogRepository;

#[ORM\Table(name: 'kimai2_aak_saml_claims_log')]
#[ORM\Index(columns: ['saml_user_email'], name: 'email_idx')]
#[ORM\Entity(repositoryClass: AakSamlClaimsLogRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[Serializer\ExclusionPolicy('all')]
class AakSamlClaimsLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private int $id;

    #[ORM\Column(name: 'saml_user_email', type: Types::STRING, length: 255, nullable: false)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private string $samlUserEmail;

    #[ORM\Column(name: 'login_success', type: Types::BOOLEAN)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private bool $loginSuccess;

    #[ORM\Column(name: 'logged_at', type: Types::DATETIME_IMMUTABLE)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private \DateTimeImmutable $loggedAt;

    #[ORM\Column(name: 'last_saml_login_at', type: Types::DATETIME_IMMUTABLE)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private \DateTimeImmutable $lastSamlLoginAt;

    #[ORM\Column(name: 'claims_hash', type: Types::STRING, length: 255)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private string $claimsHash;

    #[ORM\Column(name: 'claims', type: Types::JSON)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private array $claims;

    #[ORM\Column(name: 'exceptionMessage', type: Types::STRING, nullable: true)]
    #[Serializer\Expose]
    #[Serializer\Groups(['Default'])]
    private ?string $exceptionMessage;

    public function __construct(string $samlUserEmail, bool $succes, array $claims, ?\Exception $exception = null)
    {
        $this->samlUserEmail = $samlUserEmail;
        $this->loginSuccess = $succes;
        $this->claims = $claims;
        $this->claimsHash = $this->calculateHash($claims);

        $this->exceptionMessage = null === $exception ? null : \mb_substr($exception->getMessage(), 255);

        $this->loggedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->lastSamlLoginAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSamlUserEmail(): string
    {
        return $this->samlUserEmail;
    }

    public function getLoggedAt(): \DateTimeImmutable
    {
        return $this->loggedAt;
    }

    public function getLastSamlLoginAt(): \DateTimeImmutable
    {
        return $this->lastSamlLoginAt;
    }

    public function setLastSamlLoginAt(): self
    {
        $this->lastSamlLoginAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this;
    }

    public function getClaimsHash(): string
    {
        return $this->claimsHash;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function isLoginSuccess(): bool
    {
        return $this->loginSuccess;
    }

    public function getExceptionMessage(): ?string
    {
        return $this->exceptionMessage;
    }

    /**
     * Calculate hash string based on SAML claims.
     *
     * This hash should be used to see if claims have changed.
     *
     * @param array $data
     *   The claims array to calculate hash value for
     *
     * @return string
     *   The calculated hash string
     */
    private function calculateHash(array $data): string
    {
        return hash('sha256', serialize($data));
    }
}
