<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Docs;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo de casos de uso do SIGAME.
 *
 * Lê `docs/requisitos/casos-de-uso.yaml`, os requisitos funcionais do
 * levantamento e as rotas `/api/v1` do Router. Valida a rastreabilidade sem
 * escrever arquivos; a renderização fica em {@see UseCaseDocsRenderer}.
 */
final class UseCaseCatalog
{
    public const STATUS_IMPLEMENTED = 'implementado';
    public const STATUS_ROADMAP = 'roadmap';
    public const STATUS_DEFERRED = 'adiado';

    /** Ordem usada nos diagramas e nas mensagens de erro. */
    public const MODULES = [
        'Identity',
        'Groups',
        'Activities',
        'Gamification',
        'Notifications',
        'Administration',
        'Sponsorship',
        'Reports',
    ];

    /** Atores na ordem em que aparecem nos diagramas. */
    public const ACTOR_ORDER = [
        'usuario',
        'admin_grupo',
        'supervisor',
        'admin_sigame',
        'patrocinador',
        'sistema',
    ];

    /**
     * @param array<string, string>               $rfs
     * @param array<string, array<string, mixed>> $actors
     * @param array<string, string>               $diagrams
     * @param list<array<string, mixed>>          $cases
     * @param array<string, string>               $apiRoutes
     * @param list<string>                        $ignoredRoutes
     * @param list<string>                        $documentedActors
     * @param list<string>                        $parseErrors
     */
    private function __construct(
        public readonly int $version,
        public readonly string $systemName,
        public readonly array $rfs,
        public readonly array $actors,
        public readonly array $diagrams,
        public readonly array $cases,
        public readonly array $apiRoutes,
        public readonly array $ignoredRoutes,
        public readonly array $documentedActors,
        private readonly array $parseErrors,
    ) {
    }

    public static function load(string $projectDir, RouterInterface $router): self
    {
        $parseErrors = [];
        $data = self::loadYaml($projectDir, $parseErrors);
        $rfs = self::parseRfs($projectDir, $parseErrors);
        $documentedActors = self::parseDocumentedActors($projectDir, $parseErrors);

        $apiRoutes = [];

        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $path = $route->getPath();

            if (str_starts_with($path, '/api/v1')) {
                $apiRoutes[$name] = $path;
            }
        }

        $system = is_array($data['sistema'] ?? null) ? $data['sistema'] : [];
        $actors = is_array($data['atores'] ?? null) ? $data['atores'] : [];
        $diagrams = is_array($data['diagramas'] ?? null) ? $data['diagramas'] : [];
        $cases = is_array($data['casos'] ?? null) ? array_values($data['casos']) : [];
        $ignored = is_array($data['ignorados'] ?? null) ? array_values($data['ignorados']) : [];

        /* @var array<string, array<string, mixed>> $actors */
        /* @var array<string, string> $diagrams */
        /* @var list<array<string, mixed>> $cases */
        /* @var list<string> $ignored */
        return new self(
            version: is_int($data['versao'] ?? null) ? $data['versao'] : 0,
            systemName: is_string($system['nome'] ?? null) ? $system['nome'] : '',
            rfs: $rfs,
            actors: $actors,
            diagrams: $diagrams,
            cases: $cases,
            apiRoutes: $apiRoutes,
            ignoredRoutes: $ignored,
            documentedActors: $documentedActors,
            parseErrors: $parseErrors,
        );
    }

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $errors = $this->parseErrors;

        if (1 !== $this->version) {
            $errors[] = 'versao deve ser 1.';
        }

        if ('' === trim($this->systemName)) {
            $errors[] = 'sistema.nome é obrigatório.';
        }

        $errors = array_merge($errors, $this->validateActors(), $this->validateDiagrams());

        $ids = [];
        $byId = [];
        $routeOwners = [];
        $coveredRfs = [];
        $relations = [];

        foreach ($this->cases as $index => $case) {
            $location = 'casos['.$index.']';

            $id = is_string($case['id'] ?? null) ? $case['id'] : '';

            if (1 !== preg_match('/^UC-\d{2}\.\d+$/', $id)) {
                $errors[] = $location.'.id inválido: "'.$id.'" (esperado UC-XX.Y).';
            } elseif (isset($ids[$id])) {
                $errors[] = 'Caso de uso duplicado: '.$id.'.';
            }
            $ids[$id] = true;
            $byId[$id] = $case;

            $name = is_string($case['nome'] ?? null) ? trim($case['nome']) : '';

            if ('' === $name) {
                $errors[] = $location.'.nome é obrigatório.';
            }

            $rf = is_string($case['rf'] ?? null) ? $case['rf'] : '';

            if (!isset($this->rfs[$rf])) {
                $errors[] = $location.'.rf inexistente: "'.$rf.'".';
            } else {
                $coveredRfs[$rf] = true;
            }

            $module = is_string($case['modulo'] ?? null) ? $case['modulo'] : '';

            if (!in_array($module, self::MODULES, true)) {
                $errors[] = $location.'.modulo inválido: "'.$module.'".';
            }

            $diagram = is_string($case['diagrama'] ?? null) ? $case['diagrama'] : '';

            if (!isset($this->diagrams[$diagram])) {
                $errors[] = $location.'.diagrama inexistente: "'.$diagram.'".';
            }

            $actors = $case['atores'] ?? null;

            if (!is_array($actors) || [] === $actors) {
                $errors[] = $location.'.atores precisa listar pelo menos um ator.';
            } else {
                foreach ($actors as $actorKey) {
                    if (!is_string($actorKey) || !isset($this->actors[$actorKey])) {
                        $errors[] = $location.'.atores referencia ator inexistente: "'.(is_scalar($actorKey) ? (string) $actorKey : '?').'".';
                    }
                }
            }

            $status = is_string($case['status'] ?? null) ? $case['status'] : '';

            if (!in_array($status, [self::STATUS_IMPLEMENTED, self::STATUS_ROADMAP, self::STATUS_DEFERRED], true)) {
                $errors[] = $location.'.status inválido: "'.$status.'".';
            }

            $api = $case['api'] ?? null;

            if (!is_bool($api)) {
                $errors[] = $location.'.api precisa ser booleano.';
                $api = false;
            }

            $objective = is_string($case['objetivo'] ?? null) ? trim($case['objetivo']) : '';

            if ('' === $objective) {
                $errors[] = $location.'.objetivo é obrigatório.';
            }

            $routes = $case['rotas'] ?? null;

            if (!is_array($routes)) {
                $errors[] = $location.'.rotas precisa ser uma lista.';
                $routes = [];
            }

            $seenRoutes = [];

            foreach ($routes as $route) {
                if (!is_string($route) || !isset($this->apiRoutes[$route])) {
                    $errors[] = $location.'.rotas referencia rota inexistente: "'.(is_scalar($route) ? (string) $route : '?').'".';

                    continue;
                }

                if (isset($seenRoutes[$route])) {
                    $errors[] = $location.'.rotas repete a rota '.$route.'.';
                }
                $seenRoutes[$route] = true;

                if (self::STATUS_IMPLEMENTED === $status) {
                    $routeOwners[$route] = true;
                }
            }

            if (self::STATUS_IMPLEMENTED !== $status && [] !== $routes) {
                $errors[] = $location.': caso '.$status.' não pode ter rotas.';
            }

            if (self::STATUS_IMPLEMENTED !== $status && true === $api) {
                $errors[] = $location.': caso '.$status.' não pode ter api: true.';
            }

            if (self::STATUS_IMPLEMENTED === $status && true === $api && [] === $routes) {
                $errors[] = $location.': caso implementado com api: true precisa de ao menos uma rota.';
            }

            if (self::STATUS_IMPLEMENTED === $status && false === $api) {
                $reason = is_string($case['motivo_sem_api'] ?? null) ? trim($case['motivo_sem_api']) : '';

                if ('' === $reason) {
                    $errors[] = $location.': caso com api: false precisa de motivo_sem_api.';
                }
            }

            foreach (['inclui', 'estende'] as $kind) {
                $targets = $case[$kind] ?? [];

                if (!is_array($targets)) {
                    $errors[] = $location.'.'.$kind.' precisa ser uma lista.';

                    continue;
                }

                foreach ($targets as $target) {
                    if (!is_string($target)) {
                        $errors[] = $location.'.'.$kind.' precisa conter ids.';

                        continue;
                    }
                    $relations[] = [$id, $kind, $target, $diagram];
                }
            }
        }

        $graph = [];

        foreach ($relations as [$id, $kind, $target, $diagram]) {
            if (!isset($byId[$target])) {
                $errors[] = $id.'.'.$kind.' referencia caso inexistente: '.$target.'.';

                continue;
            }

            if ($target === $id) {
                $errors[] = $id.' não pode referenciar a si mesmo em '.$kind.'.';

                continue;
            }
            $targetDiagram = is_string($byId[$target]['diagrama'] ?? null) ? $byId[$target]['diagrama'] : '';

            if ($diagram !== $targetDiagram) {
                $errors[] = $id.'.'.$kind.' -> '.$target.' precisa ficar no mesmo diagrama.';

                continue;
            }
            $graph[$id][] = $target;
        }

        foreach (self::findCycles($graph) as $cycle) {
            $errors[] = $cycle;
        }

        $ignored = [];

        foreach ($this->ignoredRoutes as $route) {
            if (!isset($this->apiRoutes[$route])) {
                $errors[] = 'Rota ignorada inexistente: "'.$route.'".';

                continue;
            }
            $ignored[$route] = true;
        }

        foreach ($this->apiRoutes as $route => $path) {
            if (!isset($routeOwners[$route]) && !isset($ignored[$route])) {
                $errors[] = 'Rota /api/v1 sem caso de uso: '.$route.' ('.$path.').';
            }
        }

        foreach ($this->rfs as $rf => $title) {
            if (!isset($coveredRfs[$rf])) {
                $errors[] = 'RF sem caso de uso: '.$rf.' — '.$title.'.';
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function casesForDiagram(string $diagram): array
    {
        return array_values(array_filter(
            $this->cases,
            static fn (array $case): bool => ($case['diagrama'] ?? null) === $diagram,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function casesForRf(string $rf): array
    {
        return array_values(array_filter(
            $this->cases,
            static fn (array $case): bool => ($case['rf'] ?? null) === $rf,
        ));
    }

    public function actorName(string $key): string
    {
        $name = $this->actors[$key]['nome'] ?? null;

        return is_string($name) ? $name : $key;
    }

    public function diagramTitle(string $diagram): string
    {
        return $this->diagrams[$diagram] ?? $diagram;
    }

    public function rfTitle(string $rf): string
    {
        return $this->rfs[$rf] ?? $rf;
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_IMPLEMENTED => 'Implementado',
            self::STATUS_ROADMAP => 'Roadmap',
            self::STATUS_DEFERRED => 'Adiado',
            default => $status,
        };
    }

    /**
     * Atores usados por um conjunto de casos, em ordem estável.
     *
     * @param list<array<string, mixed>> $cases
     *
     * @return list<string>
     */
    public function usedActorKeys(array $cases): array
    {
        $keys = [];

        foreach ($cases as $case) {
            $actors = $case['atores'] ?? [];

            if (!is_array($actors)) {
                continue;
            }

            foreach ($actors as $key) {
                if (is_string($key)) {
                    $keys[$key] = true;
                }
            }
        }

        return $this->orderActorKeys(array_keys($keys));
    }

    /**
     * Ordena chaves de ator na ordem canônica dos diagramas.
     *
     * @param list<string> $keys
     *
     * @return list<string>
     */
    public function orderActorKeys(array $keys): array
    {
        $present = array_fill_keys($keys, true);
        $ordered = [];

        foreach (self::ACTOR_ORDER as $key) {
            if (isset($present[$key])) {
                $ordered[] = $key;
                unset($present[$key]);
            }
        }

        foreach (array_keys($present) as $key) {
            $ordered[] = $key;
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private function validateActors(): array
    {
        $errors = [];
        $configured = [];

        foreach ($this->actors as $key => $actor) {
            if (1 !== preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                $errors[] = 'Chave de ator inválida: "'.$key.'".';

                continue;
            }

            if (!is_string($actor['nome'] ?? null) || '' === trim($actor['nome'])) {
                $errors[] = 'Ator "'.$key.'" precisa de nome.';

                continue;
            }

            if (true !== ($actor['sintetico'] ?? false)) {
                $configured[] = trim($actor['nome']);
            }
        }

        sort($configured);
        $documented = $this->documentedActors;
        sort($documented);

        $missingInYaml = array_values(array_diff($documented, $configured));

        if ([] !== $missingInYaml) {
            $errors[] = 'Atores do documento ausentes em casos-de-uso.yaml: '.implode(', ', $missingInYaml).'.';
        }

        $missingInDoc = array_values(array_diff($configured, $documented));

        if ([] !== $missingInDoc) {
            $errors[] = 'Atores do YAML ausentes na seção 3.1 do levantamento: '.implode(', ', $missingInDoc).'.';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateDiagrams(): array
    {
        $errors = [];

        if ([] === $this->diagrams) {
            $errors[] = 'diagramas precisa conter pelo menos um item.';
        }

        foreach ($this->diagrams as $id => $title) {
            if (1 !== preg_match('/^[a-z][a-z0-9-]*$/', $id)) {
                $errors[] = 'Chave de diagrama inválida: "'.$id.'".';
            }

            if ('' === trim($title)) {
                $errors[] = 'Diagrama "'.$id.'" precisa de título.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, list<string>> $graph
     *
     * @return list<string>
     */
    private static function findCycles(array $graph): array
    {
        $state = [];
        $errors = [];

        $visit = function (string $vertex) use (&$visit, &$state, $graph, &$errors): void {
            $state[$vertex] = 1;

            foreach ($graph[$vertex] ?? [] as $next) {
                $nextState = $state[$next] ?? 0;

                if (1 === $nextState) {
                    $errors[] = 'Ciclo em inclui/estende envolvendo '.$vertex.' e '.$next.'.';

                    continue;
                }

                if (0 === $nextState) {
                    $visit($next);
                }
            }
            $state[$vertex] = 2;
        };

        foreach (array_keys($graph) as $vertex) {
            if (0 === ($state[$vertex] ?? 0)) {
                $visit($vertex);
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param list<string> $errors
     *
     * @return array<string, mixed>
     */
    private static function loadYaml(string $projectDir, array &$errors): array
    {
        $path = $projectDir.'/docs/requisitos/casos-de-uso.yaml';

        if (!is_file($path)) {
            $errors[] = 'Arquivo não encontrado: docs/requisitos/casos-de-uso.yaml.';

            return [];
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $exception) {
            $errors[] = 'YAML inválido: '.$exception->getMessage();

            return [];
        }

        if (!is_array($data)) {
            $errors[] = 'casos-de-uso.yaml precisa ser um mapa.';

            return [];
        }

        /* @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param list<string> $errors
     *
     * @return array<string, string>
     */
    private static function parseRfs(string $projectDir, array &$errors): array
    {
        $path = $projectDir.'/docs/requisitos/levantamento-de-requisitos.md';

        if (!is_file($path)) {
            $errors[] = 'Arquivo não encontrado: docs/requisitos/levantamento-de-requisitos.md.';

            return [];
        }

        $markdown = (string) file_get_contents($path);
        $rfs = [];

        if (false === preg_match_all('/^### (RF\d+) — (.+)$/mu', $markdown, $matches, PREG_SET_ORDER)) {
            $errors[] = 'Não foi possível ler os requisitos funcionais do levantamento.';

            return [];
        }

        foreach ($matches as $match) {
            $rfs[$match[1]] = trim($match[2]);
        }

        if ([] === $rfs) {
            $errors[] = 'Nenhum requisito funcional (RFxx) encontrado no levantamento.';
        }

        return $rfs;
    }

    /**
     * @param list<string> $errors
     *
     * @return list<string>
     */
    private static function parseDocumentedActors(string $projectDir, array &$errors): array
    {
        $path = $projectDir.'/docs/requisitos/levantamento-de-requisitos.md';

        if (!is_file($path)) {
            return [];
        }

        $markdown = (string) file_get_contents($path);

        if (1 !== preg_match('/^### 3\.1 Atores\s*$(.*?)(?=^### |^## )/msu', $markdown, $section)) {
            $errors[] = 'Seção "3.1 Atores" não encontrada no levantamento.';

            return [];
        }

        if (false === preg_match_all('/^\|\s*([^|]+?)\s*\|/mu', $section[1], $rows)) {
            return [];
        }

        $actors = [];

        foreach ($rows[1] as $cell) {
            $name = trim($cell);

            if ('' === $name || 'Ator' === $name || 1 === preg_match('/^-+$/', $name)) {
                continue;
            }
            $actors[] = $name;
        }

        if ([] === $actors) {
            $errors[] = 'Nenhum ator encontrado na seção 3.1.';
        }

        return $actors;
    }
}
