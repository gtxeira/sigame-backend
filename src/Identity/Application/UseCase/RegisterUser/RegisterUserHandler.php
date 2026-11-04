<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RegisterUser;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Functional\ValidationResult;
use App\Shared\Domain\ValueObject\Cpf;
use App\Shared\Domain\ValueObject\Email;

/**
 * Handler do caso de uso de registro de usuário.
 *
 * Orquestra: validação de entrada → validação de unicidade → hash senha → persiste → publica evento.
 */
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<User>
     */
    public function __invoke(RegisterUserCommand $command): Result
    {
        $emailResult = Email::fromString($command->email());

        $cpfResult = null;
        $cpfInput = $command->cpf();

        if (null !== $cpfInput && '' !== trim($cpfInput)) {
            $cpfResult = Cpf::fromString($cpfInput);
        }

        $validation = null === $cpfResult
            ? $emailResult
            : ValidationResult::combine($cpfResult, $emailResult);

        if ($validation->isInvalid()) {
            return Result::failure($validation->errors());
        }

        $email = $emailResult->value();
        $cpf = $cpfResult?->value();

        // RN01.1: CPF, quando informado, é único.
        if (null !== $cpf && null !== $this->userRepository->findByCpf($cpf)) {
            return Result::failure([
                IdentityErrors::cpfAlreadyExists(),
            ]);
        }

        // RN01.2: Email único.
        if (null !== $this->userRepository->findByEmail($email)) {
            return Result::failure([
                IdentityErrors::emailAlreadyExists(),
            ]);
        }

        // RN01.4: Senha nunca em texto puro.
        $hashedPassword = $this->passwordHasher->hash($command->password());

        $user = User::register(
            cpf: $cpf,
            email: $email,
            password: $hashedPassword,
            name: $command->name(),
        );

        $this->userRepository->save($user);

        // Publica evento de domínio
        $this->eventDispatcher->dispatch(UserRegistered::now(
            userId: $user->id()->value(),
            email: $email->value(),
            cpf: $cpf?->value(),
        ));

        return Result::success($user);
    }
}
