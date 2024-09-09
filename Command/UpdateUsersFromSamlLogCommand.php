<?php

namespace KimaiPlugin\AakSamlBundle\Command;

use App\Entity\User;
use App\User\UserService;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use KimaiPlugin\AakSamlBundle\Entity\AakSamlClaimsLog;
use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;
use KimaiPlugin\AakSamlBundle\Repository\AakSamlClaimsLogRepository;
use KimaiPlugin\AakSamlBundle\Service\SamlDataHydrateService;
use KimaiPlugin\AakSamlBundle\Service\SamlDTO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kimai:bundle:aak-saml:update-from-log')]
class UpdateUsersFromSamlLogCommand extends Command
{
    public function __construct(
        private readonly AakSamlClaimsLogRepository $claimsLogRepository,
        private readonly UserService $userService,
        private readonly SamlDataHydrateService $samlDataHydrateService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Updates and sets team associations from SAML log');

        $this->addOption('user', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('debug_nomax');
        $progressBar->start();

        $email = $input->getOption('user');

        try {
            if (is_string($email)) {
                $user = $this->userService->findUserByEmail($email);
                if (null !== $user) {
                    $log = $this->claimsLogRepository->getLatestUserLog($user);

                    $this->updateUserFromClaims($user, $log);

                    $progressBar->advance();
                }
            } else {
                foreach ($this->claimsLogRepository->getAakSamlClaimsLogs() as $log) {
                    $user = $this->userService->findUserByEmail($log->getSamlUserEmail());

                    $this->updateUserFromClaims($user, $log);

                    $progressBar->advance();
                }
            }

            $progressBar->finish();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }
    }

    /**
     * @throws AakSamlException
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function updateUserFromClaims(?User $user, ?AakSamlClaimsLog $claimsLog): void
    {
        if (null !== $claimsLog && null !== $user) {
            $claims = $claimsLog->getClaims();
            if (isset($claims[SamlDTO::MANAGER_EMAIL_ATTRIBUTE])) {
                $samlDto = new SamlDTO($claims);
                $this->samlDataHydrateService->hydrate($user, $samlDto);
            }
        }
    }
}
