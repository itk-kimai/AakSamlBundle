<?php

namespace KimaiPlugin\AakSamlBundle\EventSubscriber;

use App\Entity\User;
use App\Saml\SamlBadge;
use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;
use KimaiPlugin\AakSamlBundle\Service\SamlDataHydrateService;
use KimaiPlugin\AakSamlBundle\Service\SamlDTO;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

class CheckPassportEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SamlDataHydrateService $samlDataHydrateService
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['checkPassport', 200],
        ];
    }

    /**
     * @throws AakSamlException
     */
    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();

        foreach ($passport->getBadges() as $badge) {
            if ($badge instanceof SamlBadge) {
                $samlAttributes = $badge->getSamlLoginAttributes()->getAttributes();
                $user = $passport->getUser();

                // Throws AakSamlException on invalid data
                $samlDto = new SamlDTO($samlAttributes);

                if ($user instanceof User) {
                    $this->samlDataHydrateService->hydrate($user, $samlDto);
                }
            }
        }


    }
}