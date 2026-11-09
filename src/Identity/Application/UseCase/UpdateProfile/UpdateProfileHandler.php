<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\UpdateProfile;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\Profile;
use App\Identity\Domain\User\ValueObject\Weight;
use App\Shared\Domain\Functional\Result;
use LogicException;
use RuntimeException;

/**
 * Atualiza os campos presentes no perfil do usuário autenticado.
 *
 * A validação de presença, nome e tipo é feita na camada HTTP. Aqui ficam as
 * regras de negócio de altura e peso, que devolvem `Result` de falha sem
 * alterar o agregado.
 */
final readonly class UpdateProfileHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return Result<User>
     */
    public function __invoke(UpdateProfileCommand $command): Result
    {
        if (!$command->hasAnyChange()) {
            throw new LogicException('Nenhum campo de perfil foi informado para atualização.');
        }

        $user = $this->userRepository->findById($command->userId());

        if (null === $user) {
            throw new RuntimeException('Usuário autenticado não encontrado.');
        }

        $profile = $user->profile();
        $errors = [];

        if ($command->nameProvided()) {
            $name = trim($command->name() ?? '');

            if ('' === $name) {
                throw new LogicException('O nome do perfil não pode ser vazio.');
            }

            $profile = $profile->withName($name);
        }

        if ($command->heightProvided()) {
            $profile = $this->withHeight($profile, $command->heightCm(), $errors);
        }

        if ($command->weightProvided()) {
            $profile = $this->withWeight($profile, $command->weightKg(), $errors);
        }

        if ([] !== $errors) {
            return Result::failure($errors);
        }

        $user->changeProfile($profile);
        $this->userRepository->save($user);

        return Result::success($user);
    }

    /**
     * @param list<\App\Shared\Domain\Error\DomainError> $errors
     */
    private function withHeight(Profile $profile, ?int $heightCm, array &$errors): Profile
    {
        if (null === $heightCm) {
            return $profile->withHeight(null);
        }

        $heightResult = Height::fromCentimeters($heightCm);

        if ($heightResult->isInvalid()) {
            $errors = array_merge($errors, $heightResult->errors());

            return $profile;
        }

        return $profile->withHeight($heightResult->value());
    }

    /**
     * @param list<\App\Shared\Domain\Error\DomainError> $errors
     */
    private function withWeight(Profile $profile, ?float $weightKg, array &$errors): Profile
    {
        if (null === $weightKg) {
            return $profile->withWeight(null);
        }

        $weightResult = Weight::fromKilograms($weightKg);

        if ($weightResult->isInvalid()) {
            $errors = array_merge($errors, $weightResult->errors());

            return $profile;
        }

        return $profile->withWeight($weightResult->value());
    }
}
