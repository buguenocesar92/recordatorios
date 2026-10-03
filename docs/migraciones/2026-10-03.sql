-- ============================================================================
-- SQL de migraciones para REVISAR y aplicar a mano. Generado el 2026-10-03
-- con tools/migraciones-sql.sh (artisan migrate --pretend, base desechable vacía).
-- Contiene el esquema COMPLETO. Contra una base que ya tiene migraciones
-- aplicadas, ejecutar solo los bloques de las migraciones NUEVAS (ver README.md).
-- Las sentencias select son sondeos de migraciones que dependen de datos ya
-- existentes (renombrar permisos Shield, olvidar tokens de sesión): en una base
-- nueva no hacen nada; en una con datos, leer la migración PHP correspondiente.
-- Nada de este archivo se ejecuta solo.
-- ============================================================================

-- Tabla de control de Laravel (omitir si la base ya la tiene).
CREATE TABLE IF NOT EXISTS `migrations` (`id` int unsigned NOT NULL AUTO_INCREMENT, `migration` varchar(255) NOT NULL, `batch` int NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- Migración: 0001_01_01_000000_create_users_table
create table `users` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `email` varchar(255) not null, `email_verified_at` timestamp null, `password` varchar(255) not null, `two_factor_code` varchar(255) null, `two_factor_expires_at` datetime null, `direccion` varchar(255) null, `latitud` varchar(255) null, `longitud` varchar(255) null, `remember_token` varchar(100) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `users` add unique `users_email_unique`(`email`);
create table `password_reset_tokens` (`email` varchar(255) not null, `token` varchar(255) not null, `created_at` timestamp null, primary key (`email`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `sessions` add index `sessions_user_id_index`(`user_id`);
alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);

-- Migración: 0001_01_01_000001_create_cache_table
create table `cache` (`key` varchar(255) not null, `value` mediumtext not null, `expiration` int not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `cache_locks` (`key` varchar(255) not null, `owner` varchar(255) not null, `expiration` int not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

-- Migración: 0001_01_01_000002_create_jobs_table
create table `jobs` (`id` bigint unsigned not null auto_increment primary key, `queue` varchar(255) not null, `payload` longtext not null, `attempts` tinyint unsigned not null, `reserved_at` int unsigned null, `available_at` int unsigned not null, `created_at` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `jobs` add index `jobs_queue_index`(`queue`);
create table `job_batches` (`id` varchar(255) not null, `name` varchar(255) not null, `total_jobs` int not null, `pending_jobs` int not null, `failed_jobs` int not null, `failed_job_ids` longtext not null, `options` mediumtext null, `cancelled_at` int null, `created_at` int not null, `finished_at` int null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(255) not null, `connection` text not null, `queue` text not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`);

-- Migración: 2026_06_01_164859_create_permission_tables
create table `permissions` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `guard_name` varchar(255) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `permissions` add unique `permissions_name_guard_name_unique`(`name`, `guard_name`);
create table `roles` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `guard_name` varchar(255) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `roles` add unique `roles_name_guard_name_unique`(`name`, `guard_name`);
create table `model_has_permissions` (`permission_id` bigint unsigned not null, `model_type` varchar(255) not null, `model_id` bigint unsigned not null, primary key (`permission_id`, `model_id`, `model_type`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `model_has_permissions` add index `model_has_permissions_model_id_model_type_index`(`model_id`, `model_type`);
alter table `model_has_permissions` add constraint `model_has_permissions_permission_id_foreign` foreign key (`permission_id`) references `permissions` (`id`) on delete cascade;
create table `model_has_roles` (`role_id` bigint unsigned not null, `model_type` varchar(255) not null, `model_id` bigint unsigned not null, primary key (`role_id`, `model_id`, `model_type`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `model_has_roles` add index `model_has_roles_model_id_model_type_index`(`model_id`, `model_type`);
alter table `model_has_roles` add constraint `model_has_roles_role_id_foreign` foreign key (`role_id`) references `roles` (`id`) on delete cascade;
create table `role_has_permissions` (`permission_id` bigint unsigned not null, `role_id` bigint unsigned not null, primary key (`permission_id`, `role_id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `role_has_permissions` add constraint `role_has_permissions_permission_id_foreign` foreign key (`permission_id`) references `permissions` (`id`) on delete cascade;
alter table `role_has_permissions` add constraint `role_has_permissions_role_id_foreign` foreign key (`role_id`) references `roles` (`id`) on delete cascade;

-- Migración: 2026_06_02_124830_create_activity_log_table
create table `activity_log` (`id` bigint unsigned not null auto_increment primary key, `log_name` varchar(255) null, `description` text not null, `subject_type` varchar(255) null, `subject_id` bigint unsigned null, `event` varchar(255) null, `causer_type` varchar(255) null, `causer_id` bigint unsigned null, `properties` json null, `batch_uuid` uuid null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `activity_log` add index `subject`(`subject_type`, `subject_id`);
alter table `activity_log` add index `causer`(`causer_type`, `causer_id`);
alter table `activity_log` add index `activity_log_log_name_index`(`log_name`);

-- Migración: 2026_07_17_190321_migrar_nombres_permisos_shield_v4
select * from `permissions` where `name` like '%::%';

-- Migración: 2026_09_02_120000_olvidar_los_testigos_de_recordarme
select exists (select 1 from information_schema.tables where table_schema = schema() and table_name = 'users' and table_type in ('BASE TABLE', 'SYSTEM VERSIONED')) as `exists`;

-- Migración: 2026_09_06_204847_create_features_table
create table `features` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `scope` varchar(255) not null, `value` text not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `features` add unique `features_name_scope_unique`(`name`, `scope`);

-- Migración: 2026_10_02_000001_create_comunas_table
create table `comunas` (`id` bigint unsigned not null auto_increment primary key, `code` char(5) not null, `name` varchar(80) not null, `region_code` char(2) not null, `region_name` varchar(80) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `comunas` add unique `comunas_code_unique`(`code`);
alter table `comunas` add index `comunas_region_code_index`(`region_code`);

-- Migración: 2026_10_02_000002_create_waitlist_signups_table
create table `waitlist_signups` (`id` char(26) not null, `email` text not null, `email_hash` char(64) not null, `comuna_id` bigint unsigned null, `interests` json not null, `consent_version` varchar(32) not null, `consented_at` timestamp not null, `ip_hash` char(64) not null, `confirm_token_hash` char(64) null, `confirm_expires_at` timestamp null, `confirmed_at` timestamp null, `paid_penalty_last_year` tinyint(1) null, `unsubscribed_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `waitlist_signups` add constraint `waitlist_signups_comuna_id_foreign` foreign key (`comuna_id`) references `comunas` (`id`) on delete set null;
alter table `waitlist_signups` add unique `waitlist_signups_email_hash_unique`(`email_hash`);
alter table `waitlist_signups` add unique `waitlist_signups_confirm_token_hash_unique`(`confirm_token_hash`);
alter table `waitlist_signups` add index `waitlist_signups_confirm_expires_at_index`(`confirm_expires_at`);
alter table `waitlist_signups` add index `waitlist_signups_confirmed_at_index`(`confirmed_at`);

-- Migración: 2026_10_02_000003_create_metric_daily_table
create table `metric_daily` (`id` bigint unsigned not null auto_increment primary key, `date` date not null, `metric` varchar(64) not null, `value` int unsigned not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `metric_daily` add unique `metric_daily_date_metric_unique`(`date`, `metric`);

-- Registro de lo aplicado (para que `php artisan migrate` no lo repita).
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000000_create_users_table', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000001_create_cache_table', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000002_create_jobs_table', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_06_01_164859_create_permission_tables', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_06_02_124830_create_activity_log_table', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_07_17_190321_migrar_nombres_permisos_shield_v4', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_09_02_120000_olvidar_los_testigos_de_recordarme', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_09_06_204847_create_features_table', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_02_000001_create_comunas_table', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_02_000002_create_waitlist_signups_table', 1);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_10_02_000003_create_metric_daily_table', 1);
