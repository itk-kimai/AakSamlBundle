<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\Service;

use KimaiPlugin\AakSamlBundle\Entity\AakSamlClaimsLog;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlClaimsLogRepository;
use KimaiPlugin\AakSamlBundle\Service\SamlClaimsLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(SamlClaimsLogger::class)]
final class SamlClaimsLoggerTest extends TestCase
{
    private const USER_IDENTIFIER = 'jane@aarhus.dk';

    /**
     * @return array<string, list<string>>
     */
    private static function claims(): array
    {
        return [
            'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => [self::USER_IDENTIFIER],
            'extensionAttribute7' => ['1001;1004;1012;1103;6530'],
        ];
    }

    private function user(): UserInterface
    {
        $user = self::createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn(self::USER_IDENTIFIER);

        return $user;
    }

    /**
     * @param list<AakSamlClaimsLog> $savedLogs
     */
    private function logger(?AakSamlClaimsLog $latest, array &$savedLogs): SamlClaimsLogger
    {
        $repository = self::createStub(AakSamlClaimsLogRepository::class);
        $repository->method('getLatestUserLog')->willReturn($latest);
        $repository->method('saveAakSamlClaimsLog')->willReturnCallback(
            function (AakSamlClaimsLog $log) use (&$savedLogs): void {
                $savedLogs[] = $log;
            }
        );

        return new SamlClaimsLogger($repository);
    }

    /**
     * Backdate a log so a bumped "last SAML login" is measurable.
     */
    private static function backdate(AakSamlClaimsLog $log): \DateTimeImmutable
    {
        $backdated = new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC'));
        (new \ReflectionProperty(AakSamlClaimsLog::class, 'lastSamlLoginAt'))->setValue($log, $backdated);

        return $backdated;
    }

    public function testFirstLoginIsLogged(): void
    {
        $savedLogs = [];
        $logger = $this->logger(null, $savedLogs);

        $logger->logClaims($this->user(), true, self::claims());

        self::assertCount(1, $savedLogs);
        self::assertSame(self::USER_IDENTIFIER, $savedLogs[0]->getSamlUserEmail());
        self::assertTrue($savedLogs[0]->isLoginSuccess());
        self::assertSame(self::claims(), $savedLogs[0]->getClaims());
        self::assertNull($savedLogs[0]->getExceptionMessage());
    }

    public function testUnchangedClaimsBumpTheLastLoginInsteadOfAddingARow(): void
    {
        $latest = new AakSamlClaimsLog(self::USER_IDENTIFIER, true, self::claims());
        $backdated = self::backdate($latest);

        $savedLogs = [];
        $logger = $this->logger($latest, $savedLogs);

        $logger->logClaims($this->user(), true, self::claims());

        // The existing row is re-saved with a fresh login datetime, no new row.
        self::assertCount(1, $savedLogs);
        self::assertSame($latest, $savedLogs[0]);
        self::assertGreaterThan($backdated, $latest->getLastSamlLoginAt());
    }

    public function testChangedClaimsAddANewRow(): void
    {
        $latest = new AakSamlClaimsLog(self::USER_IDENTIFIER, true, ['division' => ['Kultur og Borgerservice']]);

        $savedLogs = [];
        $logger = $this->logger($latest, $savedLogs);

        $logger->logClaims($this->user(), true, self::claims());

        self::assertCount(1, $savedLogs);
        self::assertNotSame($latest, $savedLogs[0]);
        self::assertNotSame($latest->getClaimsHash(), $savedLogs[0]->getClaimsHash());
    }

    public function testFailedLoginIsAlwaysLoggedEvenWhenTheClaimsAreUnchanged(): void
    {
        $exception = new \Exception('Missing SAML attribute: employeeList');
        $latest = new AakSamlClaimsLog(self::USER_IDENTIFIER, false, self::claims(), $exception);
        $backdated = self::backdate($latest);

        $savedLogs = [];
        $logger = $this->logger($latest, $savedLogs);

        $logger->logClaims($this->user(), false, self::claims(), $exception);

        // Every failure is worth its own row, so the dedupe must not apply.
        self::assertCount(1, $savedLogs);
        self::assertNotSame($latest, $savedLogs[0]);
        self::assertFalse($savedLogs[0]->isLoginSuccess());
        self::assertSame('Missing SAML attribute: employeeList', $savedLogs[0]->getExceptionMessage());
        self::assertSame($backdated, $latest->getLastSamlLoginAt());
    }

    public function testSessionIndexIsStrippedBeforeLogging(): void
    {
        // The session index changes on every login and would defeat the dedupe.
        $latest = new AakSamlClaimsLog(self::USER_IDENTIFIER, true, self::claims());
        $backdated = self::backdate($latest);

        $savedLogs = [];
        $logger = $this->logger($latest, $savedLogs);

        $logger->logClaims($this->user(), true, [...self::claims(), 'sessionIndex' => ['_a1b2c3']]);

        self::assertCount(1, $savedLogs);
        self::assertArrayNotHasKey('sessionIndex', $savedLogs[0]->getClaims());
        self::assertSame($latest, $savedLogs[0]);
        self::assertGreaterThan($backdated, $latest->getLastSamlLoginAt());
    }
}
