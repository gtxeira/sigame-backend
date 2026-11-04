<?php

declare(strict_types=1);

namespace Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Caso de teste base para integração com adaptadores reais (sem HTTP).
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public function service(string $id): object
    {
        return self::getContainer()->get($id);
    }
}
