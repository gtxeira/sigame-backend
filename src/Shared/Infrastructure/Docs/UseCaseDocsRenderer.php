<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Docs;

/**
 * Renderiza o catálogo de casos de uso em PlantUML e em Markdown.
 *
 * O renderer é puro: recebe um {@see UseCaseCatalog} já validado e devolve o
 * conteúdo dos arquivos. Quem grava é o comando `app:docs:usecases`.
 */
final class UseCaseDocsRenderer
{
    public function __construct(private readonly UseCaseCatalog $catalog)
    {
    }

    /**
     * @return array<string, string> nome do arquivo (sem `.puml`) => conteúdo
     */
    public function plantUmlFiles(): array
    {
        $files = ['geral' => $this->generalDiagram()];

        foreach ($this->catalog->diagrams as $id => $title) {
            $files[$id] = $this->detailedDiagram($id, $title);
        }

        return $files;
    }

    public function markdown(): string
    {
        $lines = [];
        $lines[] = '<!-- GERADO por `just docs-usecases` a partir de casos-de-uso.yaml. Não edite. -->';
        $lines[] = '';
        $lines[] = '# Casos de uso — '.$this->catalog->systemName;
        $lines[] = '';
        $lines[] = 'Catálogo derivado de [`casos-de-uso.yaml`](casos-de-uso.yaml), validado contra o';
        $lines[] = '[levantamento de requisitos](levantamento-de-requisitos.md) e contra as rotas `/api/v1`.';
        $lines[] = '';
        $lines[] = '## Cobertura';
        $lines[] = '';
        $lines[] = '| Métrica | Valor |';
        $lines[] = '| ------- | ----- |';
        $lines[] = '| Requisitos funcionais | '.count($this->catalog->rfs).' |';
        $lines[] = '| Casos de uso | '.count($this->catalog->cases).' |';
        $lines[] = '| Casos implementados | '.$this->countStatus(UseCaseCatalog::STATUS_IMPLEMENTED).' |';
        $lines[] = '| Casos em roadmap | '.$this->countStatus(UseCaseCatalog::STATUS_ROADMAP).' |';
        $lines[] = '| Casos adiados | '.$this->countStatus(UseCaseCatalog::STATUS_DEFERRED).' |';
        $lines[] = '| Rotas da API mapeadas | '.count($this->catalog->apiRoutes).' |';
        $lines[] = '| Diagramas | '.(count($this->catalog->diagrams) + 1).' |';
        $lines[] = '';
        $lines[] = '## Visão geral';
        $lines[] = '';
        $lines[] = 'Um caso de uso por requisito funcional, agrupado por módulo e associado ao ator principal.';
        $lines[] = 'Os diagramas detalhados abaixo mostram todos os atores e permissões de cada caso.';
        $lines[] = '';
        $lines[] = '![Visão geral dos requisitos](casos-de-uso/geral.svg)';
        $lines[] = '';
        $lines[] = '[Fonte PlantUML](casos-de-uso/geral.puml)';
        $lines[] = '';

        foreach ($this->catalog->diagrams as $id => $title) {
            $lines[] = '## '.$title;
            $lines[] = '';
            $lines[] = '![Casos de uso — '.$title.'](casos-de-uso/'.$id.'.svg)';
            $lines[] = '';
            $lines[] = '[Fonte PlantUML](casos-de-uso/'.$id.'.puml)';
            $lines[] = '';
            $lines[] = '| Caso de uso | Nome | RF | Atores | Objetivo | Rotas | Status |';
            $lines[] = '| ----------- | ---- | -- | ------ | -------- | ------ | ------ |';

            foreach ($this->catalog->casesForDiagram($id) as $case) {
                $lines[] = $this->caseRow($case);
            }

            $lines[] = '';
        }

        $lines[] = '## Como atualizar';
        $lines[] = '';
        $lines[] = '1. Edite `casos-de-uso.yaml`.';
        $lines[] = '2. Rode `just docs-usecases` para regenerar o Markdown e os diagramas.';
        $lines[] = '3. Commite a alteração. `just docs-usecases-check` valida a cobertura sem escrever.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function generalDiagram(): string
    {
        $lines = [];
        $lines[] = $this->header('Visão geral dos requisitos — '.$this->catalog->systemName);
        $lines[] = '';

        $primaryByRf = [];
        $primaryKeys = [];

        foreach (array_keys($this->catalog->rfs) as $rf) {
            $primary = $this->primaryActor($this->catalog->casesForRf($rf));
            $primaryByRf[$rf] = $primary;

            if (null !== $primary) {
                $primaryKeys[$primary] = true;
            }
        }

        foreach ($this->catalog->orderActorKeys(array_keys($primaryKeys)) as $key) {
            $lines[] = $this->actor($key);
        }

        $lines[] = '';
        $lines[] = 'rectangle "SIGAME" {';

        $byModule = [];

        foreach (array_keys($this->catalog->rfs) as $rf) {
            $module = 'Outros';

            foreach ($this->catalog->casesForRf($rf) as $case) {
                $candidate = $case['modulo'] ?? null;

                if (is_string($candidate)) {
                    $module = $candidate;

                    break;
                }
            }
            $byModule[$module][] = $rf;
        }

        $modules = array_values(array_unique([
            ...UseCaseCatalog::MODULES,
            ...array_keys($byModule),
        ]));

        foreach ($modules as $module) {
            if (!isset($byModule[$module])) {
                continue;
            }
            $lines[] = '  package "'.$module.'" {';

            foreach ($byModule[$module] as $rf) {
                $cases = $this->catalog->casesForRf($rf);
                $status = $this->aggregateStatus($cases);
                $label = $rf.' — '.$this->catalog->rfTitle($rf).$this->statusSuffix($status);
                $lines[] = sprintf(
                    '    usecase "%s" as %s%s',
                    $this->escape($label),
                    $this->alias($rf),
                    $this->statusColor($status),
                );
            }
            $lines[] = '  }';
        }

        $lines[] = '}';
        $lines[] = '';

        foreach ($primaryByRf as $rf => $primary) {
            if (null !== $primary) {
                $lines[] = 'Ator_'.$primary.' -- '.$this->alias($rf);
            }
        }

        $lines[] = '';
        $lines[] = $this->legend();
        $lines[] = '@enduml';

        return implode("\n", $lines)."\n";
    }

    private function detailedDiagram(string $id, string $title): string
    {
        $cases = $this->catalog->casesForDiagram($id);

        $lines = [];
        $lines[] = $this->header($title.' — '.$this->catalog->systemName);
        $lines[] = '';

        foreach ($this->catalog->usedActorKeys($cases) as $key) {
            $lines[] = $this->actor($key);
        }

        $lines[] = '';
        $lines[] = 'rectangle "SIGAME" {';

        foreach ($cases as $case) {
            $caseId = is_string($case['id'] ?? null) ? $case['id'] : '';
            $status = is_string($case['status'] ?? null) ? $case['status'] : '';
            $name = is_string($case['nome'] ?? null) ? $case['nome'] : $caseId;
            $lines[] = sprintf(
                '  usecase "%s" as %s%s',
                $this->escape($name.$this->statusSuffix($status)),
                $this->alias($caseId),
                $this->statusColor($status),
            );
        }

        $lines[] = '}';
        $lines[] = '';

        foreach ($cases as $case) {
            $caseId = is_string($case['id'] ?? null) ? $case['id'] : '';
            $actors = $case['atores'] ?? [];

            if (!is_array($actors)) {
                continue;
            }

            foreach ($actors as $actor) {
                if (is_string($actor)) {
                    $lines[] = 'Ator_'.$actor.' -- '.$this->alias($caseId);
                }
            }
        }

        $lines[] = '';

        foreach ($cases as $case) {
            $caseId = is_string($case['id'] ?? null) ? $case['id'] : '';

            foreach (['inclui' => 'include', 'estende' => 'extend'] as $field => $stereotype) {
                $targets = $case[$field] ?? [];

                if (!is_array($targets)) {
                    continue;
                }

                foreach ($targets as $target) {
                    if (!is_string($target)) {
                        continue;
                    }
                    $lines[] = sprintf(
                        '%s ..> %s : <<%s>>',
                        $this->alias($caseId),
                        $this->alias($target),
                        $stereotype,
                    );
                }
            }
        }

        $lines[] = '';
        $lines[] = $this->legend();
        $lines[] = '@enduml';

        return implode("\n", $lines)."\n";
    }

    private function header(string $title): string
    {
        return implode("\n", [
            '@startuml',
            "' GERADO por `just docs-usecases` a partir de casos-de-uso.yaml. Não edite.",
            'left to right direction',
            'skinparam backgroundColor #FFFFFF',
            'skinparam shadowing false',
            'skinparam defaultFontName "Inter"',
            'skinparam ArrowColor #6B7280',
            'skinparam actor {',
            '  BackgroundColor #FFFFFF',
            '  BorderColor #111827',
            '}',
            'skinparam usecase {',
            '  BackgroundColor #EEF2FF',
            '  BorderColor #4F46E5',
            '  FontColor #111827',
            '}',
            'skinparam rectangle {',
            '  BackgroundColor #F9FAFB',
            '  BorderColor #D1D5DB',
            '}',
            'title '.$this->escape($title),
        ]);
    }

    private function actor(string $key): string
    {
        return sprintf(
            'actor "%s" as Ator_%s',
            $this->escape($this->catalog->actorName($key)),
            $key,
        );
    }

    private function legend(): string
    {
        return implode("\n", [
            'legend right',
            '  Casos sem marca estão implementados.',
            '  «roadmap»: planejado, sem endpoint na V1.',
            '  «adiado»: fora do escopo da V1.',
            'endlegend',
        ]);
    }

    /**
     * @param array<string, mixed> $case
     */
    private function caseRow(array $case): string
    {
        $actors = $case['atores'] ?? [];
        $actorNames = [];

        if (is_array($actors)) {
            foreach ($actors as $actor) {
                if (is_string($actor)) {
                    $actorNames[] = $this->catalog->actorName($actor);
                }
            }
        }

        $routes = $case['rotas'] ?? [];
        $routeNames = [];

        if (is_array($routes)) {
            foreach ($routes as $route) {
                if (is_string($route)) {
                    $routeNames[] = '`'.$route.'`';
                }
            }
        }

        $status = is_string($case['status'] ?? null) ? $case['status'] : '';

        return '| '.implode(' | ', [
            $this->cell(is_string($case['id'] ?? null) ? $case['id'] : ''),
            $this->cell(is_string($case['nome'] ?? null) ? $case['nome'] : ''),
            $this->cell(is_string($case['rf'] ?? null) ? $case['rf'] : ''),
            $this->cell(implode(', ', $actorNames)),
            $this->cell(is_string($case['objetivo'] ?? null) ? $case['objetivo'] : ''),
            $this->cell(implode('<br>', $routeNames)),
            $this->cell($this->catalog->statusLabel($status)),
        ]).' |';
    }

    /**
     * @param list<array<string, mixed>> $cases
     */
    private function aggregateStatus(array $cases): string
    {
        $statuses = [];

        foreach ($cases as $case) {
            if (is_string($case['status'] ?? null)) {
                $statuses[] = $case['status'];
            }
        }

        if (in_array(UseCaseCatalog::STATUS_IMPLEMENTED, $statuses, true)) {
            return UseCaseCatalog::STATUS_IMPLEMENTED;
        }

        if (in_array(UseCaseCatalog::STATUS_ROADMAP, $statuses, true)) {
            return UseCaseCatalog::STATUS_ROADMAP;
        }

        return UseCaseCatalog::STATUS_DEFERRED;
    }

    /**
     * Ator principal do RF: o primeiro ator do primeiro caso de uso.
     *
     * A visão geral usa só o ator principal para não virar uma teia; os
     * diagramas detalhados mostram todas as permissões.
     *
     * @param list<array<string, mixed>> $cases
     */
    private function primaryActor(array $cases): ?string
    {
        foreach ($cases as $case) {
            $actors = $case['atores'] ?? [];

            if (!is_array($actors)) {
                continue;
            }

            foreach ($actors as $actor) {
                if (is_string($actor)) {
                    return $actor;
                }
            }
        }

        return null;
    }

    private function countStatus(string $status): int
    {
        $count = 0;

        foreach ($this->catalog->cases as $case) {
            if (($case['status'] ?? null) === $status) {
                ++$count;
            }
        }

        return $count;
    }

    private function statusSuffix(string $status): string
    {
        return match ($status) {
            UseCaseCatalog::STATUS_ROADMAP => '\\n«roadmap»',
            UseCaseCatalog::STATUS_DEFERRED => '\\n«adiado»',
            default => '',
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            UseCaseCatalog::STATUS_ROADMAP => ' #F3F4F6',
            UseCaseCatalog::STATUS_DEFERRED => ' #FEE2E2',
            default => '',
        };
    }

    private function alias(string $id): string
    {
        return str_replace(['.', '-'], '_', $id);
    }

    private function escape(string $value): string
    {
        return str_replace(['"', "\n", "\r"], ['\'', ' ', ' '], $value);
    }

    private function cell(string $value): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], $value);
    }
}
