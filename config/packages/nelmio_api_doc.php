<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('nelmio_api_doc', [
        'documentation' => [
            'info' => [
                'title' => 'SIGAME API',
                'description' => 'API da plataforma web voltada ao incentivo à prática de atividades físicas em grupo.',
                'version' => '1.0.0',
            ],
            'tags' => [
                [
                    'name' => 'Autenticação',
                    'description' => 'Cadastro e login local com e-mail e senha.',
                ],
                [
                    'name' => 'Autenticação social',
                    'description' => 'Login e vínculo de identidades com provedores sociais.',
                ],
                [
                    'name' => 'Sessão',
                    'description' => 'Renovação e revogação dos tokens da sessão.',
                ],
                [
                    'name' => 'Verificação de e-mail',
                    'description' => 'Confirmação e reenvio da verificação do e-mail canônico.',
                ],
                [
                    'name' => 'Senha',
                    'description' => 'Recuperação, redefinição e troca de senha.',
                ],
                [
                    'name' => 'Minha conta',
                    'description' => 'Dados e recursos do usuário autenticado sobre a própria conta.',
                ],
                [
                    'name' => 'Onboarding',
                    'description' => 'Conclusão do onboarding inicial e definição do perfil esportivo.',
                ],
                [
                    'name' => 'Grupos',
                    'description' => 'Criação, busca e administração de grupos esportivos e seus membros.',
                ],
                [
                    'name' => 'Atividades',
                    'description' => 'Criação, ciclo de vida, inscrição, check-in e presença em atividades.',
                ],
                [
                    'name' => 'Gamificação',
                    'description' => 'Regras, progresso de XP, níveis, ofensiva semanal e histórico.',
                ],
                [
                    'name' => 'Notificações',
                    'description' => 'Inbox do usuário, leitura e assinatura do stream em tempo real.',
                ],
                [
                    'name' => 'Usuários',
                    'description' => 'Administração global de contas, suspensão e reativação.',
                ],
                [
                    'name' => 'Saúde',
                    'description' => 'Endpoints operacionais de saúde e diagnóstico da aplicação.',
                ], // Sempre em último, pois é utilitário do sistema
            ],
            'x-tagGroups' => [
                [
                    'name' => 'Acesso',
                    'tags' => [
                        'Autenticação',
                        'Autenticação social',
                        'Sessão',
                        'Verificação de e-mail',
                        'Senha',
                    ],
                ],
                [
                    'name' => 'Conta',
                    'tags' => [
                        'Minha conta',
                        'Onboarding',
                        'Gamificação',
                        'Notificações',
                    ],
                ],
                [
                    'name' => 'Comunidade',
                    'tags' => [
                        'Grupos',
                        'Atividades',
                    ],
                ],
                [
                    'name' => 'Administração',
                    'tags' => [
                        'Usuários',
                    ],
                ],
                [
                    'name' => 'Sistema',
                    'tags' => [
                        'Saúde',
                    ],
                ], // Sempre em último, pois é utilitário do sistema
            ],
            'components' => [
                'schemas' => [
                    'ApiResponseMeta' => [
                        'type' => 'object',
                        'description' => 'Metadados padrão de todas as respostas da API.',
                        'properties' => [
                            'timestamp' => [
                                'type' => 'string',
                                'example' => '2026-09-21T10:00:00.000+00:00',
                            ],
                        ],
                        'required' => [
                            'timestamp',
                        ],
                    ],
                    'PaginationMeta' => [
                        'type' => 'object',
                        'description' => 'Metadados de paginação.',
                        'properties' => [
                            'page' => [
                                'type' => 'integer',
                                'example' => 1,
                            ],
                            'perPage' => [
                                'type' => 'integer',
                                'example' => 20,
                            ],
                            'total' => [
                                'type' => 'integer',
                                'example' => 100,
                            ],
                            'totalPages' => [
                                'type' => 'integer',
                                'example' => 5,
                            ],
                        ],
                    ],
                    'ApiResponsePaginatedMeta' => [
                        'allOf' => [
                            [
                                '$ref' => '#/components/schemas/ApiResponseMeta',
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'pagination' => [
                                        '$ref' => '#/components/schemas/PaginationMeta',
                                    ],
                                ],
                                'required' => [
                                    'pagination',
                                ],
                            ],
                        ],
                    ],
                    'ApiErrorItem' => [
                        'type' => 'object',
                        'description' => 'Um item de erro retornado pela API. `code` é o contrato público e estável; `field` é opcional; `message` é o texto exibível ao usuário.',
                        'properties' => [
                            'code' => [
                                'type' => 'string',
                                'example' => 'FIELD_REQUIRED',
                            ],
                            'field' => [
                                'type' => 'string',
                                'description' => 'Caminho do campo afetado, relativo ao payload da requisição. Ausente em erros sem campo.',
                                'example' => 'email',
                            ],
                            'message' => [
                                'type' => 'string',
                                'example' => 'O e-mail é obrigatório.',
                            ],
                        ],
                        'required' => [
                            'code',
                            'message',
                        ],
                    ],
                    'ApiResponseSuccess' => [
                        'type' => 'object',
                        'description' => 'Resposta de sucesso padrão.',
                        'properties' => [
                            'data' => [
                                'description' => 'Dados da resposta.',
                            ],
                            'meta' => [
                                '$ref' => '#/components/schemas/ApiResponseMeta',
                            ],
                        ],
                        'required' => [
                            'data',
                            'meta',
                        ],
                    ],
                    'ApiResponseError' => [
                        'type' => 'object',
                        'description' => 'Resposta de erro padrão.',
                        'properties' => [
                            'errors' => [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => '#/components/schemas/ApiErrorItem',
                                ],
                            ],
                            'meta' => [
                                '$ref' => '#/components/schemas/ApiResponseMeta',
                            ],
                        ],
                        'required' => [
                            'errors',
                            'meta',
                        ],
                    ],
                ],
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Access token JWT no header Authorization: Bearer <token>.',
                    ],
                ],
            ],
        ],
        'areas' => [
            'path_patterns' => [
                '^/api/v1',
                '^/api/health',
            ],
        ],
        'type_info' => true,
    ]);
};
