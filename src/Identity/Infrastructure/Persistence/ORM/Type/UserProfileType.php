<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\ORM\Type;

use App\Identity\Domain\User\ValueObject\UserProfile;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use JsonException;
use RuntimeException;

/**
 * Doctrine Type para o Value Object UserProfile.
 *
 * Converte entre UserProfile (Domain) e JSON (banco de dados).
 */
final class UserProfileType extends Type
{
    public const string NAME = 'user_profile';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserProfile
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof UserProfile) {
            return $value;
        }

        try {
            $data = is_array($value)
                ? $value
                : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('O perfil de usuário armazenado não é um JSON válido.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException('O perfil de usuário armazenado deve ser um objeto JSON.');
        }

        return UserProfile::create(
            weight: self::nullableFloat($data['weight'] ?? null, 'weight'),
            height: self::nullableFloat($data['height'] ?? null, 'height'),
            photoUrl: self::nullableString($data['photoUrl'] ?? null, 'photoUrl'),
            modalities: self::stringList($data['modalities'] ?? [], 'modalities'),
            primaryModality: self::nullableString($data['primaryModality'] ?? null, 'primaryModality'),
        );
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof UserProfile) {
            throw new RuntimeException(sprintf(
                'O perfil de usuário deve ser um %s, %s recebido.',
                UserProfile::class,
                get_debug_type($value),
            ));
        }

        return json_encode([
            'weight' => $value->weight(),
            'height' => $value->height(),
            'photoUrl' => $value->photoUrl(),
            'modalities' => $value->modalities(),
            'primaryModality' => $value->primaryModality(),
        ], JSON_THROW_ON_ERROR);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    private static function nullableFloat(mixed $value, string $field): ?float
    {
        if (null === $value) {
            return null;
        }

        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new RuntimeException(sprintf('O campo "%s" do perfil de usuário deve ser numérico.', $field));
        }

        return (float) $value;
    }

    private static function nullableString(mixed $value, string $field): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new RuntimeException(sprintf('O campo "%s" do perfil de usuário deve ser textual.', $field));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new RuntimeException(sprintf('O campo "%s" do perfil de usuário deve ser uma lista.', $field));
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new RuntimeException(sprintf('Os itens do campo "%s" do perfil de usuário devem ser textuais.', $field));
            }

            $items[] = $item;
        }

        return $items;
    }
}
