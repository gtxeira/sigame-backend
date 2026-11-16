<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Docs\UseCaseCatalog;
use App\Shared\Infrastructure\Docs\UseCaseDocsRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouterInterface;

/**
 * Valida o catálogo de casos de uso e gera os artefatos derivados.
 *
 * `--check` não escreve: compara o conteúdo esperado com o que está no
 * repositório e falha se estiver ausente ou desatualizado. É o modo usado no
 * gate de documentação.
 */
#[AsCommand(
    name: 'app:docs:usecases',
    description: 'Valida o catálogo de casos de uso e gera PlantUML e rastreabilidade.',
)]
final class UseCaseDocsCommand extends Command
{
    public function __construct(
        private readonly RouterInterface $router,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'check',
            null,
            InputOption::VALUE_NONE,
            'Somente valida e compara os artefatos, sem escrever arquivos.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $catalog = UseCaseCatalog::load($this->projectDir, $this->router);

        $errors = $catalog->validate();

        if ([] !== $errors) {
            $io->error('Catálogo de casos de uso inválido:');

            foreach ($errors as $error) {
                $io->writeln('  - '.$error);
            }

            return Command::FAILURE;
        }

        $renderer = new UseCaseDocsRenderer($catalog);
        $files = $this->expectedFiles($renderer);

        if ((bool) $input->getOption('check')) {
            $stale = $this->staleFiles($files);

            if ([] !== $stale) {
                $io->error('Artefatos de casos de uso desatualizados. Rode `just docs-usecases` e commite:');

                foreach ($stale as $path) {
                    $io->writeln('  - '.$path);
                }

                return Command::FAILURE;
            }

            $io->success($this->summary($catalog).' Artefatos atualizados.');

            return Command::SUCCESS;
        }

        $writerError = $this->write($files);

        if (null !== $writerError) {
            $io->error($writerError);

            return Command::FAILURE;
        }

        $io->success($this->summary($catalog).' Arquivos gravados em docs/requisitos/.');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string> caminho absoluto => conteúdo
     */
    private function expectedFiles(UseCaseDocsRenderer $renderer): array
    {
        $dir = $this->outputDir();
        $files = [];

        foreach ($renderer->plantUmlFiles() as $name => $content) {
            $files[$dir.'/'.$name.'.puml'] = $content;
        }

        $files[$this->projectDir.'/docs/requisitos/casos-de-uso.md'] = $renderer->markdown();

        return $files;
    }

    /**
     * @param array<string, string> $files
     *
     * @return list<string>
     */
    private function staleFiles(array $files): array
    {
        $stale = [];

        foreach ($files as $path => $content) {
            if (!is_file($path)) {
                $stale[] = $this->relativePath($path).' (ausente)';

                continue;
            }

            if (file_get_contents($path) !== $content) {
                $stale[] = $this->relativePath($path).' (desatualizado)';
            }
        }

        foreach (glob($this->outputDir().'/*.puml') ?: [] as $path) {
            if (!isset($files[$path])) {
                $stale[] = $this->relativePath($path).' (obsoleto)';
            }
        }

        return $stale;
    }

    /**
     * @param array<string, string> $files
     */
    private function write(array $files): ?string
    {
        $dir = $this->outputDir();

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'Não foi possível criar o diretório '.$this->relativePath($dir).'.';
        }

        foreach (glob($dir.'/*.puml') ?: [] as $path) {
            if (!isset($files[$path])) {
                unlink($path);
            }
        }

        foreach (glob($dir.'/*.svg') ?: [] as $path) {
            if (!isset($files[substr($path, 0, -4).'.puml'])) {
                unlink($path);
            }
        }

        foreach ($files as $path => $content) {
            if (false === file_put_contents($path, $content)) {
                return 'Não foi possível gravar '.$this->relativePath($path).'.';
            }
        }

        return null;
    }

    private function outputDir(): string
    {
        return $this->projectDir.'/docs/requisitos/casos-de-uso';
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace($this->projectDir, '', $path), '/');
    }

    private function summary(UseCaseCatalog $catalog): string
    {
        return sprintf(
            '%d RFs, %d casos de uso e %d rotas cobertas em %d diagramas.',
            count($catalog->rfs),
            count($catalog->cases),
            count($catalog->apiRoutes),
            count($catalog->diagrams) + 1,
        );
    }
}
