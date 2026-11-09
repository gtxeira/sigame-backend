<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\AssignCpf;

use App\Identity\Domain\Error\IdentityErrors;
use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Exception\CpfAlreadyExistsException;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\ValueObject\Cpf;
use RuntimeException;

/**
 * Define o CPF de um usuário autenticado.
 *
 * O CPF é write-once: depois de definido, o próprio usuário não pode alterá-lo.
 */
final readonly class AssignCpfHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return Result<User>
     */
    public function __invoke(AssignCpfCommand $command): Result
    {
        $cpfResult = Cpf::fromString($command->cpf());

        if ($cpfResult->isInvalid()) {
            return Result::failure($cpfResult->errors());
        }

        $cpf = $cpfResult->value();
        $user = $this->userRepository->findById($command->userId());

        if (null === $user) {
            throw new RuntimeException('Usuário autenticado não encontrado.');
        }

        $currentCpf = $user->cpf();

        if (null !== $currentCpf) {
            if ($currentCpf->equals($cpf)) {
                return Result::success($user);
            }

            return Result::failure([IdentityErrors::cpfAlreadySet()]);
        }

        $existing = $this->userRepository->findByCpf($cpf);

        if (null !== $existing && !$existing->id()->equals($user->id())) {
            return Result::failure([IdentityErrors::cpfAlreadyExists()]);
        }

        $user->assignCpf($cpf);

        try {
            $this->userRepository->save($user);
        } catch (CpfAlreadyExistsException) {
            return Result::failure([IdentityErrors::cpfAlreadyExists()]);
        }

        return Result::success($user);
    }
}
