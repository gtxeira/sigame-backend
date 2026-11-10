<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provisiona uma conta como administradora global do SIGAME.
 */
#[AsCommand(
    name: 'app:identity:grant-admin',
    description: 'Promove a conta informada a administrador SIGAME.',
)]
final class GrantAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'E-mail da conta.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $emailResult = Email::fromString($email);

        if ($emailResult->isInvalid()) {
            $output->writeln('<error>E-mail inválido.</error>');

            return Command::INVALID;
        }

        $user = $this->userRepository->findByEmail($emailResult->value());

        if (null === $user) {
            $output->writeln('<error>Usuário não encontrado.</error>');

            return Command::FAILURE;
        }

        if (!$user->grantAdminRole()) {
            $output->writeln(sprintf('<comment>%s já é administrador SIGAME.</comment>', $email));

            return Command::SUCCESS;
        }

        $this->userRepository->save($user);
        $output->writeln(sprintf('<info>%s agora é administrador SIGAME.</info>', $email));

        return Command::SUCCESS;
    }
}
