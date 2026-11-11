<?php

declare(strict_types=1);

use App\Shared\Presentation\Http\Validation\ConstraintViolationCodeResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * @param array<string, mixed> $parameters
 */
function violationWithCode(?Constraint $constraint, ?string $code = null, array $parameters = []): ConstraintViolationInterface
{
    $violation = Mockery::mock(ConstraintViolationInterface::class);
    $violation->shouldReceive('getParameters')->andReturn($parameters);
    $violation->shouldReceive('getConstraint')->andReturn($constraint);
    $violation->shouldReceive('getCode')->andReturn($code);
    $violation->shouldReceive('getInvalidValue')->andReturn('ab');
    $violation->shouldReceive('getMessageTemplate')->andReturn('template');

    return $violation;
}

it('maps constraints to the public field codes', function (object $constraint, ?string $code, string $expected) {
    $resolver = new ConstraintViolationCodeResolver();

    expect($resolver->resolve(violationWithCode($constraint, $code))->value)->toBe($expected);
})->with(fn (): array => [
    'not blank' => [new NotBlank(), null, 'FIELD_REQUIRED'],
    'type' => [new Type('string'), null, 'INVALID_TYPE'],
    'email' => [new Email(), Email::INVALID_FORMAT_ERROR, 'INVALID_FORMAT'],
    'length too short' => [new Length(min: 3), Length::TOO_SHORT_ERROR, 'STRING_TOO_SHORT'],
    'length too long' => [new Length(max: 1), Length::TOO_LONG_ERROR, 'STRING_TOO_LONG'],
    'range' => [new Range(min: 1, max: 10), Range::NOT_IN_RANGE_ERROR, 'NUMBER_OUT_OF_RANGE'],
    'choice' => [new Choice(choices: ['a', 'b']), Choice::NO_SUCH_CHOICE_ERROR, 'INVALID_CHOICE'],
    'count too few' => [new Count(min: 2), Count::TOO_FEW_ERROR, 'TOO_FEW_ITEMS'],
    'count too many' => [new Count(max: 1), Count::TOO_MANY_ERROR, 'TOO_MANY_ITEMS'],
    'image too wide' => [new Image(), Image::TOO_WIDE_ERROR, 'INVALID_IMAGE_DIMENSIONS'],
    'image portrait' => [new Image(), Image::PORTRAIT_NOT_ALLOWED_ERROR, 'INVALID_IMAGE_ASPECT_RATIO'],
    'image corrupted' => [new Image(), Image::CORRUPTED_IMAGE_ERROR, 'INVALID_IMAGE'],
    'file too large' => [new File(), File::TOO_LARGE_ERROR, 'FILE_TOO_LARGE'],
    'file mime type' => [new File(), File::INVALID_MIME_TYPE_ERROR, 'UNSUPPORTED_FILE_TYPE'],
]);

it('maps a missing constructor argument to field required', function () {
    $resolver = new ConstraintViolationCodeResolver();

    $violation = violationWithCode(
        null,
        null,
        ['hint' => 'Failed to create object because the class misses the "name" property.'],
    );

    expect($resolver->resolve($violation)->value)->toBe('FIELD_REQUIRED');
});

it('prefers an explicit code from the violation parameters', function () {
    $resolver = new ConstraintViolationCodeResolver();

    $violation = violationWithCode(new NotBlank(), null, ['code' => 'INVALID_CHOICE']);

    expect($resolver->resolve($violation)->value)->toBe('INVALID_CHOICE');
});
