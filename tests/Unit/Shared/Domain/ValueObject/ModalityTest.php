<?php

declare(strict_types=1);

use App\Shared\Domain\ValueObject\Modality;

it('exposes all modality values', function () {
    expect(Modality::values())->toBe(['caminhada', 'corrida', 'ciclismo']);
});
