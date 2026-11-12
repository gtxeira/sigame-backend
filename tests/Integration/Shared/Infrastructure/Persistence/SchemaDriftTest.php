<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;

/*
 * Trava o schema no estado conhecido.
 *
 * Os índices parciais são um falso positivo do DBAL: o PostgreSQL
 * normaliza o predicado (`IN` vira `= ANY (ARRAY[...])`, `TRUE` vira `true`) e
 * o comparador de índices compara o texto do `WHERE` literalmente. Os índices
 * `idx_groups_forming_idle` e `uniq_groups_owner_name_modality` são
 * hand-written (índice parcial e índice de expressão `lower(name)`), que o
 * mapeamento ORM não expressa. Qualquer outra divergência é regressão e deve
 * falhar aqui.
 */
it('keeps the database schema in sync except for known partial index predicates', function () {
    $entityManager = $this->service(EntityManagerInterface::class);

    // O listener do DoctrineMigrationsBundle só esconde a tabela de controle em
    // comandos de console; aqui ela aparece e é ruído de infraestrutura.
    $diff = array_values(array_filter(
        (new SchemaValidator($entityManager))->getUpdateSchemaList(),
        static fn (string $sql): bool => !str_contains($sql, 'doctrine_migration_versions'),
    ));

    expect($diff)->toEqualCanonicalizing([
        'DROP INDEX uniq_group_memberships_active',
        'DROP INDEX uniq_group_memberships_supervisor',
        'CREATE UNIQUE INDEX uniq_group_memberships_active ON group_memberships (group_id, user_id) WHERE (status IN (\'pending\', \'approved\'))',
        'CREATE UNIQUE INDEX uniq_group_memberships_supervisor ON group_memberships (group_id) WHERE (is_supervisor = TRUE AND status = \'approved\')',
        'DROP INDEX uniq_activity_attendances_active',
        'CREATE UNIQUE INDEX uniq_activity_attendances_active ON activity_attendances (activity_id, user_id) WHERE (status IN (\'enrolled\', \'checked_in\', \'validated\'))',
        'DROP INDEX idx_groups_forming_idle',
        'DROP INDEX uniq_groups_owner_name_modality',
    ]);
});
