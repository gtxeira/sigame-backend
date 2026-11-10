<?php

declare(strict_types=1);

use App\Activities\Infrastructure\Persistence\ORM\Type\ActivityDescriptionType;
use App\Activities\Infrastructure\Persistence\ORM\Type\ActivityGroupIdType;
use App\Activities\Infrastructure\Persistence\ORM\Type\ActivityIdType;
use App\Activities\Infrastructure\Persistence\ORM\Type\ActivityRescheduleIdType;
use App\Activities\Infrastructure\Persistence\ORM\Type\ActivityRouteType;
use App\Activities\Infrastructure\Persistence\ORM\Type\ActivityTitleType;
use App\Activities\Infrastructure\Persistence\ORM\Type\ActivityUserIdType;
use App\Activities\Infrastructure\Persistence\ORM\Type\AttendanceIdType;
use App\Activities\Infrastructure\Persistence\ORM\Type\MeetingPointType;
use App\Gamification\Infrastructure\Persistence\ORM\Type\PlayerIdType;
use App\Gamification\Infrastructure\Persistence\ORM\Type\XpEventIdType;
use App\Groups\Infrastructure\Persistence\ORM\Type\CommunicationLinkType;
use App\Groups\Infrastructure\Persistence\ORM\Type\GroupDescriptionType;
use App\Groups\Infrastructure\Persistence\ORM\Type\GroupIdType;
use App\Groups\Infrastructure\Persistence\ORM\Type\GroupMemberLimitType;
use App\Groups\Infrastructure\Persistence\ORM\Type\GroupNameType;
use App\Groups\Infrastructure\Persistence\ORM\Type\MembershipIdType;
use App\Groups\Infrastructure\Persistence\ORM\Type\MemberUserIdType;
use App\Groups\Infrastructure\Persistence\ORM\Type\RemovalReasonType;
use App\Identity\Infrastructure\Persistence\ORM\Type\HashedPasswordType;
use App\Identity\Infrastructure\Persistence\ORM\Type\HeightType;
use App\Identity\Infrastructure\Persistence\ORM\Type\IdentityTokenIdType;
use App\Identity\Infrastructure\Persistence\ORM\Type\ProviderSubjectType;
use App\Identity\Infrastructure\Persistence\ORM\Type\RefreshTokenIdType;
use App\Identity\Infrastructure\Persistence\ORM\Type\TokenFamilyIdType;
use App\Identity\Infrastructure\Persistence\ORM\Type\TokenHashType;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserIdentityIdType;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserIdType;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserLevelType;
use App\Identity\Infrastructure\Persistence\ORM\Type\UserSuspensionIdType;
use App\Identity\Infrastructure\Persistence\ORM\Type\WeightType;
use App\Notifications\Infrastructure\Persistence\ORM\Type\NotificationDeliveryIdType;
use App\Notifications\Infrastructure\Persistence\ORM\Type\NotificationIdType;
use App\Notifications\Infrastructure\Persistence\ORM\Type\NotificationRecipientIdType;
use App\Shared\Infrastructure\Persistence\ORM\Type\BaseIdType;
use App\Shared\Infrastructure\Persistence\ORM\Type\CpfType;
use App\Shared\Infrastructure\Persistence\ORM\Type\EmailType;
use App\Shared\Infrastructure\Persistence\ORM\Type\InstantType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine', [
        'dbal' => [
            'url' => '%env(resolve:DATABASE_URL)%',
            'profiling_collect_backtrace' => '%kernel.debug%',
            'mapping_types' => [
                'timestamptz' => 'instant',
            ],
            'types' => [
                'base_id' => BaseIdType::class,
                'user_id' => UserIdType::class,
                'email' => EmailType::class,
                'cpf' => CpfType::class,
                'hashed_password' => HashedPasswordType::class,
                'user_level' => UserLevelType::class,
                'height' => HeightType::class,
                'weight' => WeightType::class,
                'user_suspension_id' => UserSuspensionIdType::class,
                'notification_id' => NotificationIdType::class,
                'notification_delivery_id' => NotificationDeliveryIdType::class,
                'notification_recipient_id' => NotificationRecipientIdType::class,
                'instant' => InstantType::class,
                'user_identity_id' => UserIdentityIdType::class,
                'identity_token_id' => IdentityTokenIdType::class,
                'provider_subject' => ProviderSubjectType::class,
                'refresh_token_id' => RefreshTokenIdType::class,
                'token_family_id' => TokenFamilyIdType::class,
                'token_hash' => TokenHashType::class,
                'group_id' => GroupIdType::class,
                'membership_id' => MembershipIdType::class,
                'member_user_id' => MemberUserIdType::class,
                'group_name' => GroupNameType::class,
                'group_description' => GroupDescriptionType::class,
                'group_member_limit' => GroupMemberLimitType::class,
                'group_communication_link' => CommunicationLinkType::class,
                'removal_reason' => RemovalReasonType::class,
                'activity_id' => ActivityIdType::class,
                'attendance_id' => AttendanceIdType::class,
                'activity_reschedule_id' => ActivityRescheduleIdType::class,
                'activity_group_id' => ActivityGroupIdType::class,
                'activity_user_id' => ActivityUserIdType::class,
                'activity_title' => ActivityTitleType::class,
                'activity_description' => ActivityDescriptionType::class,
                'meeting_point' => MeetingPointType::class,
                'activity_route' => ActivityRouteType::class,
                'player_id' => PlayerIdType::class,
                'xp_event_id' => XpEventIdType::class,
            ],
        ],
        'orm' => [
            'validate_xml_mapping' => true,
            'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
            'identity_generation_preferences' => [
                PostgreSQLPlatform::class => 'identity',
            ],
            'auto_mapping' => false,
            'mappings' => [
                'Identity' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Identity/Domain',
                    'prefix' => 'App\Identity\Domain',
                    'alias' => 'Identity',
                ],
                'Groups' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Groups/Domain',
                    'prefix' => 'App\Groups\Domain',
                    'alias' => 'Groups',
                ],
                'Activities' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Activities/Domain',
                    'prefix' => 'App\Activities\Domain',
                    'alias' => 'Activities',
                ],
                'Gamification' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Gamification/Domain',
                    'prefix' => 'App\Gamification\Domain',
                    'alias' => 'Gamification',
                ],
                'Notifications' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => '%kernel.project_dir%/src/Notifications/Domain',
                    'prefix' => 'App\Notifications\Domain',
                    'alias' => 'Notifications',
                ],
            ],
        ],
    ]);

    if ($containerConfigurator->env() === 'test') {
        $containerConfigurator->extension('doctrine', [
            'dbal' => [
                'dbname_suffix' => '_test%env(default::TEST_TOKEN)%',
            ],
        ]);
    }

    if ($containerConfigurator->env() === 'prod') {
        $containerConfigurator->extension('doctrine', [
            'orm' => [
                'query_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.system_cache_pool',
                ],
                'result_cache_driver' => [
                    'type' => 'pool',
                    'pool' => 'doctrine.result_cache_pool',
                ],
            ],
        ]);

        $containerConfigurator->extension('framework', [
            'cache' => [
                'pools' => [
                    'doctrine.result_cache_pool' => [
                        'adapter' => 'cache.app',
                    ],
                    'doctrine.system_cache_pool' => [
                        'adapter' => 'cache.system',
                    ],
                ],
            ],
        ]);
    }
};
