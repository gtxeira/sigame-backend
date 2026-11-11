<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RegisterUser;

use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Identity\Domain\User\Exception\CpfAlreadyExistsException;
use App\Identity\Domain\User\Exception\EmailAlreadyExistsException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\PlainPassword;
use App\Identity\Domain\User\ValueObject\ProfileName;
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
        $cpfInput = $command->cpf();
        $cpfResult = null === $cpfInput || '' === trim($cpfInput)
            ? ValidationResult::valid(null)
            : Cpf::fromString($cpfInput);

        $validation = ValidationResult::combine(
            Email::fromString($command->email()),
            ProfileName::fromString($command->name()),
            PlainPassword::fromString($command->password()),
            $cpfResult,
        );

        if ($validation->isInvalid()) {
            return Result::failure($validation->errors());
        }

        [$email, $name, $password, $cpf] = $validation->value();

        $conflicts = [];

        // RN01.1: CPF, quando informado, é único.
        if (null !== $cpf && null !== $this->userRepository->findByCpf($cpf)) {
            $conflicts[] = IdentityErrors::cpfAlreadyExists();
        }

        // RN01.2: Email único.
        if (null !== $this->userRepository->findByEmail($email)) {
            $conflicts[] = IdentityErrors::emailAlreadyExists();
        }

        if ([] !== $conflicts) {
            return Result::failure($conflicts);
        }

        // RN01.4: Senha nunca em texto puro.
        $hashedPassword = $this->passwordHasher->hash($password->value());

        $user = User::register(
            cpf: $cpf,
            email: $email,
            password: $hashedPassword,
            name: $name->value(),
        );

        try {
            $this->userRepository->save($user);
        } catch (EmailAlreadyExistsException) {
            return Result::failure([
                IdentityErrors::emailAlreadyExists(),
            ]);
        } catch (CpfAlreadyExistsException) {
            return Result::failure([
                IdentityErrors::cpfAlreadyExists(),
            ]);
        }

        // Publica evento de domínio
        $this->eventDispatcher->dispatch(UserRegistered::now(
            userId: $user->id()->value(),
            email: $email->value(),
            cpf: $cpf?->value(),
        ));

        return Result::success($user);
    }
}
