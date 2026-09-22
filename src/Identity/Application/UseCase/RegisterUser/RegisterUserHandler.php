<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RegisterUser;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Event\UserRegistered;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Exception\EmailAlreadyExistsException;
use App\Shared\Domain\Exception\UserCpfAlreadyExistsException;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;

/**
 * Handler do caso de uso de registro de usuário.
 *
 * Orquestra: validação de unicidade → hash senha → persiste → publica evento.
 */
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(RegisterUserCommand $command): User
    {
        $cpf = Cpf::fromString($command->cpf());
        $email = Email::fromString($command->email());

        // RN01.1: CPF único
        if (null !== $this->userRepository->findByCpf($cpf)) {
            throw new UserCpfAlreadyExistsException();
        }

        // RN01.2: Email único
        if (null !== $this->userRepository->findByEmail($email)) {
            throw new EmailAlreadyExistsException();
        }

        // RN01.4: Senha nunca em texto puro
        $hashedPassword = $this->passwordHasher->hash($command->password());

        $user = User::register(
            cpf: $cpf,
            email: $email,
            password: $hashedPassword,
            name: $command->name(),
        );

        $this->userRepository->save($user);

        // Publica evento de domínio
        $this->eventDispatcher->dispatch(new UserRegistered(
            userId: $user->id()->value(),
            email: $email->value(),
            cpf: $cpf->value(),
        ));

        return $user;
    }
}
