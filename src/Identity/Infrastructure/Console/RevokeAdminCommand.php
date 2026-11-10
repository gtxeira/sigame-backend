<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\UserRole;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Remove o papel de administrador global, preservando ao menos um admin ativo.
 */
#[AsCommand(
    name: 'app:identity:revoke-admin',
    description: 'Rebaixa a conta informada para usuário comum.',
)]
final class RevokeAdminCommand extends Command
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

        if (UserRole::ADMIN_SIGAME !== $user->role()) {
            $output->writeln(sprintf('<comment>%s não é administrador SIGAME.</comment>', $email));

            return Command::SUCCESS;
        }

        if ($this->userRepository->countActiveAdmins() <= 1) {
            $output->writeln('<error>Não é possível rebaixar o último administrador ativo.</error>');

            return Command::FAILURE;
        }

        if (!$user->revokeAdminRole()) {
            $output->writeln(sprintf('<comment>%s não é administrador SIGAME.</comment>', $email));

            return Command::SUCCESS;
        }

        $this->userRepository->save($user);
        $output->writeln(sprintf('<info>%s agora é usuário comum.</info>', $email));

        return Command::SUCCESS;
    }
}
