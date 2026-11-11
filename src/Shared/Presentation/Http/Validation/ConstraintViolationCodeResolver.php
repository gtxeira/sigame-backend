<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Validation;

use App\Shared\Domain\Error\ValidationErrorCode;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Negative;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Traduz uma violação do Symfony Validator para o catálogo público de forma.
 *
 * O código da constraint (`getCode()`) é a fonte primária, porque identifica a
 * regra exata sem depender da mensagem traduzida.
 */
final class ConstraintViolationCodeResolver
{
    public function resolve(ConstraintViolationInterface $violation): ValidationErrorCode
    {
        $explicitCode = $violation->getParameters()['code'] ?? null;

        if (is_string($explicitCode) && null !== $code = ValidationErrorCode::tryFrom($explicitCode)) {
            return $code;
        }

        $constraint = $violation->getConstraint();

        if (null === $constraint) {
            return $this->resolveWithoutConstraint($violation);
        }

        return match (true) {
            $constraint instanceof NotBlank,
            $constraint instanceof NotNull => ValidationErrorCode::FieldRequired,
            $constraint instanceof Type => ValidationErrorCode::InvalidType,
            $constraint instanceof Email,
            $constraint instanceof Regex,
            $constraint instanceof Uuid => ValidationErrorCode::InvalidFormat,
            $constraint instanceof Length => $this->resolveLength($violation, $constraint),
            $constraint instanceof Range,
            $constraint instanceof Positive,
            $constraint instanceof Negative,
            $constraint instanceof GreaterThan,
            $constraint instanceof GreaterThanOrEqual,
            $constraint instanceof LessThan,
            $constraint instanceof LessThanOrEqual => ValidationErrorCode::NumberOutOfRange,
            $constraint instanceof Choice => $this->resolveChoice($violation),
            $constraint instanceof Count => $this->resolveCount($violation),
            $constraint instanceof Image => $this->resolveImage($violation),
            $constraint instanceof File => $this->resolveFile($violation),
            default => ValidationErrorCode::InvalidFormat,
        };
    }

    private function resolveLength(ConstraintViolationInterface $violation, Length $constraint): ValidationErrorCode
    {
        return match ($violation->getCode()) {
            Length::TOO_SHORT_ERROR => ValidationErrorCode::StringTooShort,
            Length::TOO_LONG_ERROR => ValidationErrorCode::StringTooLong,
            default => $this->resolveLengthByValue($violation, $constraint),
        };
    }

    private function resolveLengthByValue(ConstraintViolationInterface $violation, Length $constraint): ValidationErrorCode
    {
        $value = $violation->getInvalidValue();
        $length = is_string($value) ? mb_strlen($value) : null;

        if (null !== $length && null !== $constraint->min && $length < $constraint->min) {
            return ValidationErrorCode::StringTooShort;
        }

        if (null !== $length && null !== $constraint->max && $length > $constraint->max) {
            return ValidationErrorCode::StringTooLong;
        }

        return ValidationErrorCode::StringTooShort;
    }

    private function resolveChoice(ConstraintViolationInterface $violation): ValidationErrorCode
    {
        return match ($violation->getCode()) {
            Choice::TOO_FEW_ERROR => ValidationErrorCode::TooFewItems,
            Choice::TOO_MANY_ERROR => ValidationErrorCode::TooManyItems,
            default => ValidationErrorCode::InvalidChoice,
        };
    }

    private function resolveCount(ConstraintViolationInterface $violation): ValidationErrorCode
    {
        return match ($violation->getCode()) {
            Count::TOO_FEW_ERROR => ValidationErrorCode::TooFewItems,
            Count::TOO_MANY_ERROR => ValidationErrorCode::TooManyItems,
            default => ValidationErrorCode::TooFewItems,
        };
    }

    private function resolveImage(ConstraintViolationInterface $violation): ValidationErrorCode
    {
        return match ($violation->getCode()) {
            Image::SIZE_NOT_DETECTED_ERROR,
            Image::CORRUPTED_IMAGE_ERROR => ValidationErrorCode::InvalidImage,
            Image::TOO_WIDE_ERROR,
            Image::TOO_NARROW_ERROR,
            Image::TOO_HIGH_ERROR,
            Image::TOO_LOW_ERROR,
            Image::TOO_FEW_PIXEL_ERROR,
            Image::TOO_MANY_PIXEL_ERROR => ValidationErrorCode::InvalidImageDimensions,
            Image::RATIO_TOO_BIG_ERROR,
            Image::RATIO_TOO_SMALL_ERROR,
            Image::SQUARE_NOT_ALLOWED_ERROR,
            Image::LANDSCAPE_NOT_ALLOWED_ERROR,
            Image::PORTRAIT_NOT_ALLOWED_ERROR => ValidationErrorCode::InvalidImageAspectRatio,
            File::TOO_LARGE_ERROR => ValidationErrorCode::FileTooLarge,
            File::INVALID_MIME_TYPE_ERROR,
            File::INVALID_EXTENSION_ERROR => ValidationErrorCode::UnsupportedFileType,
            File::EMPTY_ERROR => ValidationErrorCode::FieldRequired,
            default => ValidationErrorCode::InvalidImage,
        };
    }

    private function resolveFile(ConstraintViolationInterface $violation): ValidationErrorCode
    {
        return match ($violation->getCode()) {
            File::TOO_LARGE_ERROR => ValidationErrorCode::FileTooLarge,
            File::INVALID_MIME_TYPE_ERROR,
            File::INVALID_EXTENSION_ERROR => ValidationErrorCode::UnsupportedFileType,
            File::EMPTY_ERROR => ValidationErrorCode::FieldRequired,
            default => ValidationErrorCode::UnsupportedFileType,
        };
    }

    private function resolveWithoutConstraint(ConstraintViolationInterface $violation): ValidationErrorCode
    {
        $hint = $violation->getParameters()['hint'] ?? null;

        if (is_string($hint) && str_contains($hint, 'misses the')) {
            return ValidationErrorCode::FieldRequired;
        }

        return ValidationErrorCode::InvalidType;
    }
}
