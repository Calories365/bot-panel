/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bot_admins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telegram_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bot_admins_telegram_user_id_unique` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bot_manager` (
  `manager_id` bigint unsigned NOT NULL,
  `bot_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `manager_bot_manager_id_foreign` (`manager_id`),
  KEY `manager_bot_bot_id_foreign` (`bot_id`),
  CONSTRAINT `manager_bot_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `manager_bot_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `managers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bot_manager_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bot_id` bigint unsigned NOT NULL,
  `manager_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bot_manager_logs` (`bot_id`),
  KEY `bot_manager_logs_manager_id_foreign` (`manager_id`),
  CONSTRAINT `bot_manager_logs_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bot_manager_logs_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `managers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bot_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bot_user_bans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bot_id` bigint unsigned NOT NULL,
  `bot_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `banned_bot_user_relations_bot_id_foreign` (`bot_id`),
  KEY `banned_bot_user_relations_bot_user_id_foreign` (`bot_user_id`),
  CONSTRAINT `banned_bot_user_relations_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `banned_bot_user_relations_bot_user_id_foreign` FOREIGN KEY (`bot_user_id`) REFERENCES `bot_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bot_user_bots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bot_id` bigint unsigned NOT NULL,
  `bot_user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bot_user_bot_id_foreign` (`bot_id`),
  KEY `bot_user_bot_user_id_foreign` (`bot_user_id`),
  CONSTRAINT `bot_user_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bot_user_bot_user_id_foreign` FOREIGN KEY (`bot_user_id`) REFERENCES `bot_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bot_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calories_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_id` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bot_id` bigint unsigned DEFAULT NULL,
  `premium` tinyint(1) NOT NULL DEFAULT '0',
  `is_banned` tinyint(1) NOT NULL DEFAULT '0',
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locale` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `last_active_at` timestamp NULL DEFAULT NULL,
  `source` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `big_font` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bot_users_telegram_id_unique` (`telegram_id`),
  KEY `bot_users_bot_id_foreign` (`bot_id`),
  KEY `bot_users_last_active_at_index` (`last_active_at`),
  CONSTRAINT `bot_users_bot_id_foreign` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_id` bigint unsigned DEFAULT NULL,
  `wordpress_endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `web_hook` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `web_app_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret_token` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `message_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_ru` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_ua` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_eng` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bots_token_unique` (`token`),
  KEY `bots_type_id_foreign` (`type_id`),
  CONSTRAINT `bots_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `bot_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calories_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `calories_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_id` bigint unsigned DEFAULT NULL,
  `is_banned` tinyint(1) NOT NULL DEFAULT '0',
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `premium` tinyint(1) NOT NULL DEFAULT '0',
  `premium_calories` tinyint(1) NOT NULL DEFAULT '0',
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username_calories` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `calories_users_calories_id_unique` (`calories_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_activity` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_activity_date_unique` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `language_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `russian_language_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `managers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telegram_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_last` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `managers_telegram_id_unique` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `counter` int unsigned NOT NULL DEFAULT '0',
  `premium_until` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `telegram_verified` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2014_10_12_100000_create_password_reset_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2024_02_15_092927_create_bots_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2024_02_17_143732_create_bot_admins_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2024_02_17_143919_create_bot_users_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2024_02_17_145525_rename_telegram_user_id_to_telegram_id_in_bot_users_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2024_02_17_202556_add_bot_id_to_bot_users_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2024_02_18_232159_add_premium_to_bot_users_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2024_02_19_114717_modify_username_column_in_bot_users_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2024_02_19_140617_create_jobs_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2024_02_20_154919_create_bot_user_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2024_02_22_100300_add_message_image_to_bots_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2024_02_22_154505_add_is_banned_column_to_bot_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2024_02_26_112759_create_banned_bot_user_relations_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2024_05_10_113534_add_phone_to_bot_users_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2024_05_10_125815_create_bot_types_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2024_05_10_130158_add_type_id_to_bots_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2024_05_10_151246_add_web_hook_to_bots_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2024_05_21_131520_rename_bot_user_to_bot_bot_user',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2024_06_04_093906_create_managers_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2024_06_05_124153_rename_web_hook_to_wordpress_endpoint_in_bots_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2024_06_05_125120_add_web_hook_to_bots_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2024_06_06_132134_rename_banned_bot_user_relations_to_banned_bot_user',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2024_06_07_065253_rename_bot_bot_user_to_bot_bot_users',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2024_06_07_085936_rename_bot_bot_users_to_bot_user_bot',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2024_06_07_092912_rename_banned_bot_users_to_bot_user_bans',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2024_06_07_095909_rename_bot_user_bot_to_bot_user_bots',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2024_06_07_131444_create_manager_bot_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2024_06_07_142454_rename_manager_bot_to_bot_manager',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2024_06_10_080141_create_bot_manager_logs_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2024_12_25_141507_add_caloires_id_to_bot_users_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2024_12_26_132941_add_telegram_verified_to_users_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2024_12_27_181343_add_locale_to_bot_users_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2025_01_21_141358_add_last_active_at_to_bot_users_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2025_01_21_153227_create_daily_activity_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2025_01_21_183954_add_source_at_to_bot_users_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2025_01_23_113612_create_calories_users_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2025_01_23_173803_remove_unique_from_username_in_bot_users_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2025_01_24_142742_add_calories_id_to_calories_users_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2025_01_26_200550_create_subscriptions_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2025_01_27_150731_add_unique_calories_id_to_calories_users',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2025_03_01_193436_create_language_settings_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2025_03_23_172250_add_video_paths_to_bots_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2025_09_30_100000_add_big_font_to_bot_users_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2025_11_24_205424_add_secret_token_hash_to_bots_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_08_24_100000_add_web_app_url_to_bots_table',25);
