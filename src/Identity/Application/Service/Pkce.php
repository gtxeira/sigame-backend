<?php

declare(strict_types=1);

namespace App\Identity\Application\Service;

use Random\RandomException;

/**
 * Geração de PKCE (S256) para o fluxo OAuth do backend.
 */
final class Pkce
{
    /**
     * @throws RandomException
     */
    public static function verifier(): string
    {
        return self::base64Url(random_bytes(32));
    }

    public static function challenge(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    /**
     * @throws RandomException
     */
    public static function state(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @throws RandomException
     */
    public static function flowId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @throws RandomException
     */
    public static function linkToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
