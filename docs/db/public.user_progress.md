# public.user_progress

## Descrição

Progresso agregado do usuário (XP, nível e ofensiva semanal).

## Colunas

| Nome | Tipo | Padrão | Nulo | Filhos | Pais | Comentário |
| ---- | ---- | ------ | ---- | ------ | ---- | ---------- |
| user_id | uuid |  | false |  | [public.users](public.users.md) |  |
| xp_balance | integer | 0 | false |  |  |  |
| xp_lifetime | integer | 0 | false |  |  |  |
| level | smallint | 1 | false |  |  |  |
| current_weekly_streak | integer | 0 | false |  |  |  |
| best_weekly_streak | integer | 0 | false |  |  |  |
| last_completed_week | varchar(10) | NULL::character varying | true |  |  |  |
| last_validated_activity_at | timestamp(6) with time zone | NULL::timestamp with time zone | true |  |  |  |
| last_decay_period_end | varchar(10) | NULL::character varying | true |  |  |  |
| decayed_periods | integer | 0 | false |  |  |  |
| rule_version | varchar(32) |  | false |  |  |  |
| created_at | timestamp(6) with time zone |  | false |  |  |  |
| updated_at | timestamp(6) with time zone |  | false |  |  |  |

## Contextos

| Nome | Definição |
| ---- | ---------- |
| [Gamification](viewpoint-gamification.md) | Progresso, ledger de XP e conquistas. |

## Restrições

| Nome | Tipo | Definição |
| ---- | ---- | ---------- |
| user_progress_best_weekly_streak_not_null | n | NOT NULL best_weekly_streak |
| user_progress_created_at_not_null | n | NOT NULL created_at |
| user_progress_current_weekly_streak_not_null | n | NOT NULL current_weekly_streak |
| user_progress_decayed_periods_not_null | n | NOT NULL decayed_periods |
| user_progress_level_not_null | n | NOT NULL level |
| user_progress_rule_version_not_null | n | NOT NULL rule_version |
| user_progress_updated_at_not_null | n | NOT NULL updated_at |
| user_progress_user_id_not_null | n | NOT NULL user_id |
| user_progress_xp_balance_not_null | n | NOT NULL xp_balance |
| user_progress_xp_lifetime_not_null | n | NOT NULL xp_lifetime |
| user_progress_pkey | PRIMARY KEY | PRIMARY KEY (user_id) |

## Índices

| Nome | Definição |
| ---- | ---------- |
| user_progress_pkey | CREATE UNIQUE INDEX user_progress_pkey ON public.user_progress USING btree (user_id) |
| idx_user_progress_decay | CREATE INDEX idx_user_progress_decay ON public.user_progress USING btree (last_validated_activity_at) |

## Relacionamentos

```mermaid
erDiagram

"public.user_progress" |o--|| "public.users" : "Detected Relation"
"public.user_modalities" }o--|| "public.users" : "FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
"public.user_identities" }o--|| "public.users" : "FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
"public.refresh_tokens" }o--|| "public.users" : "FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
"public.user_suspensions" }o--|| "public.users" : "FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT"
"public.user_suspensions" }o--|| "public.users" : "FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
"public.identity_otp_challenges" }o--|| "public.users" : "FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
"public.identity_password_reset_tickets" }o--|| "public.users" : "FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
"public.activities" }o--|| "public.users" : "created_by"
"public.activities" |o--o| "public.users" : "started_by"
"public.activities" |o--o| "public.users" : "finished_by"
"public.activities" |o--o| "public.users" : "cancelled_by"
"public.activities" |o--o| "public.users" : "invalidated_by"
"public.activity_attendances" |o--o| "public.users" : "validated_by"
"public.activity_attendances" |o--o| "public.users" : "cancelled_by"
"public.activity_reschedules" }o--|| "public.users" : "changed_by"
"public.groups" }o--|| "public.users" : "created_by"
"public.groups" }o--|| "public.users" : "owner_id"
"public.groups" |o--o| "public.users" : "archived_by"
"public.groups" |o--o| "public.users" : "deleted_by"
"public.group_memberships" |o--o| "public.users" : "decided_by"
"public.group_memberships" |o--o| "public.users" : "removed_by"
"public.group_ownership_transfers" }o--|| "public.users" : "previous_owner_id"
"public.group_ownership_transfers" }o--|| "public.users" : "new_owner_id"
"public.group_ownership_transfers" }o--|| "public.users" : "actor_id"
"public.notifications" }o--|| "public.users" : "recipient_id"
"public.admin_audit_entries" }o--|| "public.users" : "actor_id"
"public.group_memberships" }o--|| "public.users" : "Detected Relation"
"public.activity_attendances" }o--|| "public.users" : "Detected Relation"
"public.xp_events" }o--|| "public.users" : "Detected Relation"
"public.user_achievements" }o--|| "public.users" : "Detected Relation"

"public.user_progress" {
  uuid user_id ""
}
"public.users" {
  uuid id ""
}
"public.user_modalities" {
  uuid user_id FK ""
}
"public.user_identities" {
  uuid user_id FK ""
}
"public.refresh_tokens" {
  uuid id ""
  uuid user_id FK ""
  uuid replaced_by_id FK ""
}
"public.user_suspensions" {
  uuid user_id FK ""
  uuid actor_id FK ""
}
"public.identity_otp_challenges" {
  uuid id ""
  uuid user_id FK ""
}
"public.identity_password_reset_tickets" {
  uuid user_id FK ""
  uuid challenge_id FK ""
}
"public.activities" {
  uuid id ""
  uuid group_id ""
  uuid created_by ""
  uuid started_by ""
  uuid finished_by ""
  uuid cancelled_by ""
  uuid invalidated_by ""
}
"public.activity_attendances" {
  uuid user_id ""
  uuid validated_by ""
  uuid cancelled_by ""
  uuid activity_id FK ""
}
"public.activity_reschedules" {
  uuid changed_by ""
  uuid activity_id FK ""
}
"public.groups" {
  uuid id ""
  uuid created_by ""
  uuid archived_by ""
  uuid deleted_by ""
  uuid owner_id ""
}
"public.group_memberships" {
  uuid group_id FK ""
  uuid user_id ""
  uuid decided_by ""
  uuid removed_by ""
}
"public.group_ownership_transfers" {
  uuid previous_owner_id ""
  uuid new_owner_id ""
  uuid actor_id ""
  uuid group_id FK ""
}
"public.notifications" {
  uuid id ""
  uuid recipient_id ""
}
"public.admin_audit_entries" {
  uuid actor_id ""
}
"public.xp_events" {
  uuid user_id ""
  varchar_64_ activity_id ""
}
"public.user_achievements" {
  uuid user_id ""
}
```

---

> Generated by [tbls](https://github.com/k1LoW/tbls)
