<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Doctrine\ORM\EntityManagerInterface;
use PDO;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

/**
 * Helper para limpeza do banco de dados de testes.
 *
 * truncateAll() recebe o kernel do teste e limpa as tabelas via sua conexão.
 * Setup e drop do banco são feitos via Makefile (test-db-setup / test-db-drop).
 */
final class DatabaseCleaner
{
    /**
     * Limpa todas as tabelas e reseta sequences do banco de teste.
     *
     * Usa TRUNCATE ... CASCADE via PDO nativo e reseta as sequences do PostgreSQL.
     *
     * @param KernelInterface $kernel O kernel do teste em execução (para usar a mesma conexão)
     */
    public static function truncateAll(KernelInterface $kernel): void
    {
        /** @var EntityManagerInterface $em */
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();
        $nativeConnection = $connection->getNativeConnection();

        // Obtém lista de tabelas via PDO nativo
        $stmt = $nativeConnection->query(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'",
        );
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ([] === $tables) {
            return;
        }

        // Truncate com CASCADE via PDO nativo (bypass Doctrine DBAL)
        foreach ($tables as $row) {
            $tableName = $row['tablename'];

            try {
                $nativeConnection->exec(
                    \sprintf('TRUNCATE TABLE "%s" CASCADE', $tableName),
                );
            } catch (Throwable) {
                // Tabela pode ter problema — ignorar
            }
        }

        // Reseta sequences do PostgreSQL via PDO nativo
        foreach ($tables as $row) {
            $tableName = $row['tablename'];
            $sequenceName = $tableName.'_id_seq';

            try {
                $nativeConnection->exec(
                    \sprintf('ALTER SEQUENCE "%s" RESTART WITH 1', $sequenceName),
                );
            } catch (Throwable) {
                // Sequence não existe para esta tabela — ignorar
            }
        }

        $em->clear();
    }
}
