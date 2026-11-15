<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\RegisterUser;

use App\Identity\Application\Service\AccountExistsNoticeThrottleInterface;
use App\Identity\Application\Service\IdentityMailDispatcherInterface;
use App\Identity\Application\Service\PasswordHasherInterface;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Event\UserRegistered;
use App\Identity\Domain\User\Exception\EmailAlreadyExistsException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\PlainPassword;
use App\Identity\Domain\User\ValueObject\ProfileName;
use App\Shared\Application\Event\EventDispatcherInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Functional\ValidationResult;
use App\Shared\Domain\ValueObject\Email;

/**
 * Handler da solicitação de cadastro.
 *
 * O resultado observável é o mesmo exista ou não conta: o e-mail novo cria a
 * conta e envia o código; o existente recebe apenas um aviso com instruções.
 * A senha é sempre hasheada, para o tempo não revelar qual caminho foi seguido.
 */
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
        private IdentityMailDispatcherInterface $mailDispatcher,
        private AccountExistsNoticeThrottleInterface $accountExistsNoticeThrottle,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return Result<bool> true quando uma conta nova foi criada
     */
    public function __invoke(RegisterUserCommand $command): Result
    {
        $validation = ValidationResult::combine(
            Email::fromString($command->email()),
            ProfileName::fromString($command->name()),
            PlainPassword::fromString($command->password()),
        );

        if ($validation->isInvalid()) {
            return Result::failure($validation->errors());
        }

        /** @var array{0: Email, 1: ProfileName, 2: PlainPassword} $values */
        $values = $validation->value();
        [$email, $name, $password] = $values;

        // A KDF roda antes da busca: e-mail existente e novo têm o mesmo custo.
        $hashedPassword = $this->passwordHasher->hash($password->value());

        if (null !== $this->userRepository->findByEmail($email)) {
            $this->notifyAccountExists($email);

            return Result::success(false);
        }

        $user = User::register(
            cpf: null,
            email: $email,
            password: $hashedPassword,
            name: $name->value(),
        );

        try {
            $this->userRepository->save($user);
        } catch (EmailAlreadyExistsException) {
            // Corrida entre a busca e o INSERT: o e-mail passou a existir.
            $this->notifyAccountExists($email);

            return Result::success(false);
        }

        $this->eventDispatcher->dispatch(UserRegistered::now(
            userId: $user->id()->value(),
            email: $email->value(),
            cpf: null,
        ));

        return Result::success(true);
    }

    private function notifyAccountExists(Email $email): void
    {
        if (!$this->accountExistsNoticeThrottle->reserve($email)) {
            return;
        }

        $this->mailDispatcher->dispatchAccountExists($email);
    }
}
