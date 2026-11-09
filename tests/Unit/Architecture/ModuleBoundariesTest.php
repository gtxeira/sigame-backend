<?php

declare(strict_types=1);

/**
 * Módulos encontrados em `src/` (tudo que não seja o Kernel).
 *
 * @return list<string>
 */
function sourceModules(): array
{
    $sourceDirectory = dirname(__DIR__, 3).'/src';
    $modules = [];

    foreach (new DirectoryIterator($sourceDirectory) as $entry) {
        if ($entry->isDir() && !$entry->isDot()) {
            $modules[] = $entry->getFilename();
        }
    }

    sort($modules);

    return $modules;
}

/**
 * Extrai o módulo de um FQCN `App\<Módulo>\...`.
 */
function moduleOfClass(string $className): ?string
{
    $parts = explode('\\', $className);

    if ('App' !== $parts[0] || !isset($parts[1])) {
        return null;
    }

    return $parts[1];
}

/**
 * Regra de importação entre módulos:
 * - um módulo pode importar a si próprio;
 * - qualquer módulo pode importar `Shared`;
 * - `Shared` não pode importar módulos funcionais;
 * - um módulo funcional pode importar apenas classes de evento de outro módulo.
 */
function moduleImportIsAllowed(
    string $sourceModule,
    string $targetModule,
    ?string $importedClass = null,
): bool {
    if ($targetModule === $sourceModule) {
        return true;
    }

    if ('Shared' === $targetModule) {
        return 'Shared' !== $sourceModule;
    }

    if ('Shared' === $sourceModule || null === $importedClass) {
        return false;
    }

    return str_contains($importedClass, '\\Domain\\')
        && str_contains($importedClass, '\\Event\\');
}

/**
 * Verifica as regras de fronteira da modularização:
 * - `Shared` não pode importar nenhum módulo funcional;
 * - um módulo funcional só pode importar a si próprio e `Shared`.
 *
 * @return list<array{file: string, module: string, imported: string, target: string}>
 */
function moduleBoundaryViolations(): array
{
    $projectDirectory = dirname(__DIR__, 3);
    $violations = [];

    foreach (sourceModules() as $module) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $projectDirectory.'/src/'.$module,
                FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all(
                '/^use\s+(App\\\\[A-Za-z0-9_\\\\]+)(?:\s+as\s+\w+)?;/m',
                $contents,
                $matches,
            );

            foreach ($matches[1] as $imported) {
                $target = moduleOfClass($imported);

                if (null === $target || $target === $module) {
                    continue;
                }

                if (moduleImportIsAllowed($module, $target, $imported)) {
                    continue;
                }

                $violations[] = [
                    'file' => str_replace($projectDirectory.'/', '', $file->getPathname()),
                    'module' => $module,
                    'imported' => $imported,
                    'target' => $target,
                ];
            }
        }
    }

    return $violations;
}

it('discovers the current source modules', function () {
    expect(sourceModules())->toContain('Shared', 'Identity', 'Groups', 'Activities', 'Monitoring');
});

it('classifies imports according to the module boundary rules', function (
    string $sourceModule,
    string $targetModule,
    ?string $importedClass,
    bool $allowed,
) {
    expect(moduleImportIsAllowed($sourceModule, $targetModule, $importedClass))->toBe($allowed);
})->with([
    'module imports itself' => ['Identity', 'Identity', null, true],
    'module imports Shared' => ['Identity', 'Shared', null, true],
    'Shared imports itself' => ['Shared', 'Shared', null, true],
    'Shared imports feature module' => ['Shared', 'Identity', null, false],
    'feature module imports another feature module' => ['Identity', 'Monitoring', null, false],
    'feature module imports another module event' => ['Identity', 'Activities', 'App\\Activities\\Domain\\Activity\\Event\\ActivityCreated', true],
    'feature module imports another module entity' => ['Identity', 'Activities', 'App\\Activities\\Domain\\Activity\\Entity\\Activity', false],
]);

it('keeps Shared independent from feature modules', function () {
    $violations = array_values(array_filter(
        moduleBoundaryViolations(),
        static fn (array $violation): bool => 'Shared' === $violation['module'],
    ));

    expect($violations)->toBe([]);
});

it('prevents direct imports between feature modules', function () {
    $violations = array_values(array_filter(
        moduleBoundaryViolations(),
        static fn (array $violation): bool => 'Shared' !== $violation['module'],
    ));

    expect($violations)->toBe([]);
});
