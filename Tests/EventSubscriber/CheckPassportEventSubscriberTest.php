<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\EventSubscriber;

use App\Entity\User;
use App\Saml\SamlBadge;
use App\Saml\SamlLoginAttributes;
use KimaiPlugin\AakSamlBundle\EventSubscriber\CheckPassportEventSubscriber;
use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;
use KimaiPlugin\AakSamlBundle\Service\SamlClaimsLogger;
use KimaiPlugin\AakSamlBundle\Service\SamlDataHydrateService;
use KimaiPlugin\AakSamlBundle\Service\SamlDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

#[CoversClass(CheckPassportEventSubscriber::class)]
final class CheckPassportEventSubscriberTest extends TestCase
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
        ];
    }

    /**
     * @param array<string, list<string>> $attributes
     */
    private static function samlBadge(array $attributes): SamlBadge
    {
        $loginAttributes = new SamlLoginAttributes();
        $loginAttributes->setAttributes($attributes);
        $loginAttributes->setUserIdentifier('jane@aarhus.dk');

        return new SamlBadge($loginAttributes);
    }

    /**
     * @param list<BadgeInterface> $badges
     */
    private static function event(UserInterface $user, array $badges): CheckPassportEvent
    {
        $passport = new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn (): UserInterface => $user),
            $badges
        );

        return new CheckPassportEvent(self::createStub(AuthenticatorInterface::class), $passport);
    }

    private static function kimaiUser(): User
    {
        $user = new User();
        $user->setUsername('jane@aarhus.dk');
        $user->setEmail('jane@aarhus.dk');

        return $user;
    }

    public function testItListensToCheckPassportBeforeKimaisOwnListeners(): void
    {
        // Kimai's SAML provider hydrates on the same event; the priority keeps
        // our claim mapping in front of it. A rename breaks every SAML login.
        self::assertSame(
            [CheckPassportEvent::class => ['checkPassport', 200]],
            CheckPassportEventSubscriber::getSubscribedEvents()
        );
    }

    public function testSuccessfulLoginHydratesTheUserAndLogsTheClaims(): void
    {
        $user = self::kimaiUser();
        $attributes = self::attributes();

        $capturedDto = null;
        $hydrateService = self::createMock(SamlDataHydrateService::class);
        $hydrateService->expects(self::once())->method('hydrate')->willReturnCallback(
            function (User $hydratedUser, SamlDTO $samlDto) use ($user, &$capturedDto): void {
                self::assertSame($user, $hydratedUser);
                $capturedDto = $samlDto;
            }
        );

        $claimsLogger = self::createMock(SamlClaimsLogger::class);
        $claimsLogger->expects(self::once())->method('logClaims')->with($user, true, $attributes, null);

        $subscriber = new CheckPassportEventSubscriber($hydrateService, $claimsLogger);
        $subscriber->checkPassport(self::event($user, [self::samlBadge($attributes)]));

        self::assertInstanceOf(SamlDTO::class, $capturedDto);
        self::assertSame('jane@aarhus.dk', $capturedDto->emailAddress);
        self::assertSame('boss@aarhus.dk', $capturedDto->managerEmail);
    }

    public function testInvalidClaimsAreLoggedAsAFailedLoginAndRethrown(): void
    {
        $user = self::kimaiUser();
        // A missing claim makes the SamlDTO constructor throw, so hydration
        // never starts, but the login attempt must still end up in the log.
        $attributes = self::attributes();
        unset($attributes['employeeList']);

        $hydrateService = self::createMock(SamlDataHydrateService::class);
        $hydrateService->expects(self::never())->method('hydrate');

        $claimsLogger = self::createMock(SamlClaimsLogger::class);
        $claimsLogger->expects(self::once())->method('logClaims')
            ->with($user, false, $attributes, self::isInstanceOf(AakSamlException::class));

        $subscriber = new CheckPassportEventSubscriber($hydrateService, $claimsLogger);

        $this->expectException(AakSamlException::class);
        $this->expectExceptionMessageIsOrContains('Missing SAML attribute: employeeList');

        $subscriber->checkPassport(self::event($user, [self::samlBadge($attributes)]));
    }

    public function testFailedHydrationIsLoggedAndRethrown(): void
    {
        $user = self::kimaiUser();
        $attributes = self::attributes();
        $exception = new AakSamlException('Validation Failed');

        $hydrateService = self::createMock(SamlDataHydrateService::class);
        $hydrateService->expects(self::once())->method('hydrate')->willThrowException($exception);

        $claimsLogger = self::createMock(SamlClaimsLogger::class);
        $claimsLogger->expects(self::once())->method('logClaims')->with($user, false, $attributes, $exception);

        $subscriber = new CheckPassportEventSubscriber($hydrateService, $claimsLogger);

        $this->expectException(AakSamlException::class);
        $this->expectExceptionMessageIsOrContains('Validation Failed');

        $subscriber->checkPassport(self::event($user, [self::samlBadge($attributes)]));
    }

    public function testPassportWithoutASamlBadgeIsLeftAlone(): void
    {
        // Local (non-SAML) logins dispatch the same event.
        $hydrateService = self::createMock(SamlDataHydrateService::class);
        $hydrateService->expects(self::never())->method('hydrate');

        $claimsLogger = self::createMock(SamlClaimsLogger::class);
        $claimsLogger->expects(self::never())->method('logClaims');

        $subscriber = new CheckPassportEventSubscriber($hydrateService, $claimsLogger);
        $subscriber->checkPassport(self::event(self::kimaiUser(), []));
    }

    public function testNonKimaiUserIsNotHydrated(): void
    {
        $user = self::createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('jane@aarhus.dk');

        $hydrateService = self::createMock(SamlDataHydrateService::class);
        $hydrateService->expects(self::never())->method('hydrate');

        $claimsLogger = self::createMock(SamlClaimsLogger::class);
        $claimsLogger->expects(self::never())->method('logClaims');

        $subscriber = new CheckPassportEventSubscriber($hydrateService, $claimsLogger);
        $subscriber->checkPassport(self::event($user, [self::samlBadge(self::attributes())]));
    }
}
