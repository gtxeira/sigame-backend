<?php

declare(strict_types=1);

use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Tests\FunctionalTestCase;

/**
 * Gera um PNG válido sem depender da extensão GD.
 */
function mePhotoPng(int $width = 512, int $height = 512, bool $withPixels = true): string
{
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    };

    $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
    $raw = '';

    if ($withPixels) {
        $row = "\x00".str_repeat("\x00\x00\x00", $width);
        $raw = str_repeat($row, $height);
    }

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', $header)
        .$chunk('IDAT', gzcompress($raw, 6))
        .$chunk('IEND', '');
}

function mePhotoUpload(string $contents, string $name = 'photo.png', string $mimeType = 'image/png'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'sigame-upload-');
    assert(is_string($path));
    file_put_contents($path, $contents);

    return new UploadedFile($path, $name, $mimeType, null, true);
}

/**
 * @return array<string, mixed>
 */
function mePhotoTestServer(FunctionalTestCase $test, string $email = 'photo@example.com'): array
{
    authTestCreateUser($test, $email);

    return ['HTTP_AUTHORIZATION' => 'Bearer '.authTestAccessToken(authTestLogin($test, $email))];
}

function mePhotoStorage(FunctionalTestCase $test): FilesystemOperator
{
    /** @var FilesystemOperator $storage */
    /* @phpstan-ignore-next-line */
    $storage = $test->api->getKernelBrowser()->getContainer()->get('storage.filesystem');

    return $storage;
}

function mePhotoKeyFromUrl(string $url): string
{
    return substr($url, strlen('http://cdn.test/'));
}

/**
 * Mensagens de detalhe da última resposta de validação.
 *
 * @return list<string>
 */
function mePhotoErrorMessages(Response $response): array
{
    $details = authTestBody($response)['errors'][0]['details'] ?? [];

    if (!is_array($details)) {
        return [];
    }

    return array_values(array_map(
        static fn (mixed $detail): string => is_array($detail) ? (string) ($detail['message'] ?? '') : '',
        $details,
    ));
}

it('uploads a valid png and exposes its public url', function () {
    $server = mePhotoTestServer($this);
    $contents = mePhotoPng();

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload($contents)],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $data = authTestBody($response)['data'];
    $url = (string) $data['photoUrl'];

    expect($url)->toStartWith('http://cdn.test/users/')
        ->and($url)->toEndWith('.png');

    $key = mePhotoKeyFromUrl($url);

    expect(mePhotoStorage($this)->fileExists($key))->toBeTrue()
        ->and(mePhotoStorage($this)->read($key))->toBe($contents);

    $me = $this->api->get('app_me', server: $server);

    expect(authTestBody($me)['data']['photoUrl'])->toBe($url);
});

it('replaces the previous photo and removes the old object', function () {
    $server = mePhotoTestServer($this);

    $first = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng())],
        server: $server,
    );
    $firstKey = mePhotoKeyFromUrl((string) authTestBody($first)['data']['photoUrl']);

    $second = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng(515, 515))],
        server: $server,
    );
    $secondKey = mePhotoKeyFromUrl((string) authTestBody($second)['data']['photoUrl']);

    expect($firstKey)->not->toBe($secondKey)
        ->and(mePhotoStorage($this)->fileExists($firstKey))->toBeFalse()
        ->and(mePhotoStorage($this)->fileExists($secondKey))->toBeTrue();
});

it('removes the photo idempotently', function () {
    $server = mePhotoTestServer($this);

    $upload = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng())],
        server: $server,
    );
    $key = mePhotoKeyFromUrl((string) authTestBody($upload)['data']['photoUrl']);

    $firstRemoval = $this->api->delete('app_me_photo_remove', server: $server);
    $secondRemoval = $this->api->delete('app_me_photo_remove', server: $server);

    expect($firstRemoval->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and($secondRemoval->getStatusCode())->toBe(Response::HTTP_NO_CONTENT)
        ->and(mePhotoStorage($this)->fileExists($key))->toBeFalse();

    $me = $this->api->get('app_me', server: $server);

    expect(authTestBody($me)['data']['photoUrl'])->toBeNull();
});

it('rejects a request without a photo', function () {
    $server = mePhotoTestServer($this);

    $response = $this->api->postMultipart('app_me_photo_update', server: $server);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);

    expect(authTestBody($response)['errors'][0]['details'][0]['field'])->toBe('photo');
});

it('rejects a file that is not an image', function () {
    $server = mePhotoTestServer($this);

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload('plain text', 'photo.png', 'text/plain')],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);
});

it('rejects a corrupted image', function () {
    $server = mePhotoTestServer($this);

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload('not a real png', 'photo.png', 'image/png')],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR']);
});

it('accepts a square image within the 512 pixel tolerance', function (int $side) {
    $server = mePhotoTestServer($this);

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng($side, $side))],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(authTestBody($response)['data']['photoUrl'])->toStartWith('http://cdn.test/users/');
})->with([
    'lower bound' => [507],
    'target size' => [512],
    'upper bound' => [517],
]);

it('rejects an image below the minimum dimensions', function () {
    $server = mePhotoTestServer($this);

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng(506, 506))],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR'])
        ->and(mePhotoErrorMessages($response))
        ->toContain('A foto de perfil deve ter 512×512 pixels (tolerância de 5 pixels por lado).');
});

it('rejects an image above the maximum dimensions', function () {
    $server = mePhotoTestServer($this);

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng(518, 518))],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR'])
        ->and(mePhotoErrorMessages($response))
        ->toContain('A foto de perfil deve ter 512×512 pixels (tolerância de 5 pixels por lado).');
});

it('rejects a non square image even within the size range', function (int $width, int $height) {
    $server = mePhotoTestServer($this);

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng($width, $height))],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR'])
        ->and(mePhotoErrorMessages($response))
        ->toContain('A foto de perfil deve ser quadrada (proporção 1:1).');
})->with([
    'landscape' => [517, 507],
    'portrait' => [507, 517],
]);

it('rejects an image above the maximum file size', function () {
    $server = mePhotoTestServer($this);

    $contents = mePhotoPng().str_repeat('a', 3 * 1024 * 1024);

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload($contents)],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
        ->and($this->api->errorCodes())->toBe(['VALIDATION_ERROR'])
        ->and(mePhotoErrorMessages($response))->toContain('A foto de perfil deve ter no máximo 2 MB.');
});

it('rejects an upload without a bearer token', function () {
    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng())],
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED)
        ->and($this->api->errorCodes())->toBe(['ACCESS_TOKEN_MISSING']);
});

it('rate limits repeated uploads', function () {
    $server = mePhotoTestServer($this);

    for ($attempt = 1; $attempt <= 10; ++$attempt) {
        $response = $this->api->postMultipart(
            'app_me_photo_update',
            ['photo' => mePhotoUpload(mePhotoPng())],
            server: $server,
        );

        expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    }

    $response = $this->api->postMultipart(
        'app_me_photo_update',
        ['photo' => mePhotoUpload(mePhotoPng())],
        server: $server,
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_TOO_MANY_REQUESTS);
});
