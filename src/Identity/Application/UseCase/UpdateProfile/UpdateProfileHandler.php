<?php

declare(strict_types=1);

namespace App\Identity\Application\UseCase\UpdateProfile;

use App\Identity\Domain\User\Entity\User;
use App\Identity\Domain\User\Repository\UserRepositoryInterface;
use App\Identity\Domain\User\ValueObject\Height;
use App\Identity\Domain\User\ValueObject\ProfileName;
use App\Identity\Domain\User\ValueObject\Weight;
use App\Shared\Domain\Functional\Result;
use App\Shared\Domain\Functional\ValidationResult;
use LogicException;
use RuntimeException;

/**
 * Atualiza os campos presentes no perfil do usuário autenticado.
 *
 * A presença de cada campo é conferida na camada HTTP. Aqui as regras de nome,
 * altura e peso são validadas juntas para devolver todos os erros de uma vez.
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

        $validation = ValidationResult::combine(
            $command->nameProvided()
                ? ProfileName::fromString($command->name() ?? '')
                : ValidationResult::valid(null),
            $command->heightProvided()
                ? $this->heightResult($command->heightCm())
                : ValidationResult::valid(null),
            $command->weightProvided()
                ? $this->weightResult($command->weightKg())
                : ValidationResult::valid(null),
        );

        if ($validation->isInvalid()) {
            return Result::failure($validation->errors());
        }

        [$name, $height, $weight] = $validation->value();

        if ($command->nameProvided()) {
            $profile = $profile->withName($name->value());
        }

        if ($command->heightProvided()) {
            $profile = $profile->withHeight($height);
        }

        if ($command->weightProvided()) {
            $profile = $profile->withWeight($weight);
        }

        $user->changeProfile($profile);
        $this->userRepository->save($user);

        return Result::success($user);
    }

    /**
     * @return ValidationResult<Height|null>
     */
    private function heightResult(?int $heightCm): ValidationResult
    {
        if (null === $heightCm) {
            /** @var ValidationResult<Height|null> $result */
            $result = ValidationResult::valid(null);

            return $result;
        }

        /** @var ValidationResult<Height|null> $result */
        $result = Height::fromCentimeters($heightCm);

        return $result;
    }

    /**
     * @return ValidationResult<Weight|null>
     */
    private function weightResult(?float $weightKg): ValidationResult
    {
        if (null === $weightKg) {
            /** @var ValidationResult<Weight|null> $result */
            $result = ValidationResult::valid(null);

            return $result;
        }

        /** @var ValidationResult<Weight|null> $result */
        $result = Weight::fromKilograms($weightKg);

        return $result;
    }
}
