-- B.E.S.T. Learning and Interactive Study System: the presentation school, ready to load again.
--
-- WHAT THIS DOES: it DELETES the database named `sibol` and everything in it, then creates it again with the
-- presentation school (built late on 2026-10-26). All dates move with the day you run it. Before 11 PM the school
-- looks as it did the morning after it was built (work that was due "tomorrow" is due today), so nothing made
-- while it was built shows up as happening later today.
--
-- HOW TO USE (phpMyAdmin): open http://localhost/phpmyadmin, click "Import" at the top WITHOUT choosing a
-- database first, choose this file, and click "Import" at the bottom. Then copy the files in the "uploads"
-- folder next to this file into C:\xampp\htdocs\BEST\storage\uploads (or just run reset-presentation.bat,
-- which does both).
--
-- Every account uses the password Polomolok-2026. No AI key or app key is inside.

DROP DATABASE IF EXISTS `sibol`;
CREATE DATABASE `sibol` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sibol`;
-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sibol_present
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_periods`
--

DROP TABLE IF EXISTS `academic_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_periods_org` (`organization_id`,`starts_on`),
  CONSTRAINT `fk_periods_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_periods_dates` CHECK (`ends_on` >= `starts_on`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_periods`
--

LOCK TABLES `academic_periods` WRITE;
/*!40000 ALTER TABLE `academic_periods` DISABLE KEYS */;
INSERT INTO `academic_periods` VALUES (1,1,'School year 2025–2026','2025-06-17','2026-04-11',0,'2025-09-21 12:24:04','2025-09-21 12:24:04'),(2,1,'School year 2026–2027','2026-06-16','2027-04-10',1,'2026-06-28 12:24:04','2026-06-28 12:24:04');
/*!40000 ALTER TABLE `academic_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_citations`
--

DROP TABLE IF EXISTS `ai_citations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_citations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) unsigned NOT NULL,
  `ref_no` tinyint(3) unsigned NOT NULL,
  `material_id` int(10) unsigned DEFAULT NULL,
  `material_version_id` int(10) unsigned DEFAULT NULL,
  `chunk_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `page_from` int(10) unsigned DEFAULT NULL,
  `page_to` int(10) unsigned DEFAULT NULL,
  `section_title` varchar(255) DEFAULT NULL,
  `excerpt` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_citations_message` (`message_id`,`ref_no`),
  KEY `fk_ai_citations_material` (`material_id`),
  KEY `fk_ai_citations_chunk` (`chunk_id`),
  CONSTRAINT `fk_ai_citations_chunk` FOREIGN KEY (`chunk_id`) REFERENCES `material_chunks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_citations_material` FOREIGN KEY (`material_id`) REFERENCES `learning_materials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_citations_message` FOREIGN KEY (`message_id`) REFERENCES `ai_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_citations`
--

LOCK TABLES `ai_citations` WRITE;
/*!40000 ALTER TABLE `ai_citations` DISABLE KEYS */;
INSERT INTO `ai_citations` VALUES (10,8,1,1,1,7,'Breathing and circulation notes',NULL,NULL,'Two circuits working together','In pulmonary circulation, blood goes from the right ventricle to the lungs, picks up oxygen, and returns to the left atrium.','2026-10-26 12:31:25'),(11,8,2,1,1,1,'Breathing and circulation notes',NULL,NULL,'Why your body needs both systems','This process uses oxygen and produces carbon dioxide as a waste product.','2026-10-26 12:31:25'),(12,10,1,1,1,3,'Breathing and circulation notes',NULL,NULL,'Gas exchange in the alveoli','Three features make this exchange fast: the very large total surface area of the alveoli, their thin walls, and their moist lining, which lets gases dissolve before they diffuse.','2026-10-26 12:31:39'),(13,10,3,1,1,1,'Breathing and circulation notes',NULL,NULL,'Why your body needs both systems','The circulatory system carries both gases between the lungs and the rest of the body.','2026-10-26 12:31:39'),(14,12,1,2,2,12,'Heredity notes',3,3,NULL,'Geneticists write the dominant allele with a capital letter and the recessive allele with the same letter in lowercase.','2026-10-26 12:31:52'),(15,12,2,2,2,11,'Heredity notes',2,2,NULL,'From these results Mendel concluded that each plant carries two factors for a trait, which we now call alleles.','2026-10-26 12:31:52'),(16,14,1,6,6,29,'Quadratic equations: four methods',NULL,NULL,'Method 2: Factoring','Write the equation in standard form, factor the left side, then use the zero product property: if $pq = 0$, then $p = 0$ or $q = 0$.','2026-10-26 12:32:06');
/*!40000 ALTER TABLE `ai_citations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_conversations`
--

DROP TABLE IF EXISTS `ai_conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned DEFAULT NULL,
  `material_id` int(10) unsigned DEFAULT NULL,
  `mode` varchar(30) NOT NULL,
  `title` varchar(160) NOT NULL,
  `language` varchar(8) NOT NULL DEFAULT 'en',
  `level` varchar(30) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `last_message_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_conversations_user` (`user_id`,`archived_at`,`last_message_at`),
  KEY `idx_ai_conversations_retention` (`organization_id`,`last_message_at`),
  KEY `fk_ai_conversations_class` (`class_id`),
  KEY `fk_ai_conversations_material` (`material_id`),
  CONSTRAINT `fk_ai_conversations_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_conversations_material` FOREIGN KEY (`material_id`) REFERENCES `learning_materials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_conversations_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_conversations`
--

LOCK TABLES `ai_conversations` WRITE;
/*!40000 ALTER TABLE `ai_conversations` DISABLE KEYS */;
INSERT INTO `ai_conversations` VALUES (4,1,6,1,NULL,'ask_materials','How do the respiratory and circulatory systems work together when I…','en',NULL,NULL,'2026-10-25 09:31:25','2026-10-25 09:31:25','2026-10-25 09:31:25'),(5,1,6,1,NULL,'explain_back','Gas exchange happens in the alveoli. Oxygen from the air goes into…','en',NULL,NULL,'2026-10-24 10:31:39','2026-10-24 10:31:39','2026-10-24 10:31:39'),(6,1,6,1,NULL,'explain','What is the difference between a dominant and a recessive allele?','en',NULL,NULL,'2026-10-26 07:31:52','2026-10-26 07:31:52','2026-10-26 07:31:52'),(7,1,8,3,NULL,'explain','How do I solve x^2 - 5x + 6 = 0 by factoring?','en',NULL,NULL,'2026-10-24 11:32:06','2026-10-24 11:32:06','2026-10-24 11:32:06');
/*!40000 ALTER TABLE `ai_conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_grading_suggestions`
--

DROP TABLE IF EXISTS `ai_grading_suggestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_grading_suggestions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` int(10) unsigned NOT NULL,
  `requested_by` int(10) unsigned DEFAULT NULL,
  `suggestion` longtext NOT NULL,
  `provider` varchar(40) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_grading_submission` (`submission_id`,`created_at`),
  KEY `fk_ai_grading_requester` (`requested_by`),
  CONSTRAINT `fk_ai_grading_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_grading_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_grading_suggestions`
--

LOCK TABLES `ai_grading_suggestions` WRITE;
/*!40000 ALTER TABLE `ai_grading_suggestions` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_grading_suggestions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_messages`
--

DROP TABLE IF EXISTS `ai_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) unsigned NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `mode` varchar(30) DEFAULT NULL,
  `content` mediumtext NOT NULL,
  `status` enum('complete','streaming','error','cancelled') NOT NULL DEFAULT 'complete',
  `grounding` enum('sources','general','no_sources','demo') DEFAULT NULL,
  `provider` varchar(40) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `input_tokens` int(10) unsigned DEFAULT NULL,
  `output_tokens` int(10) unsigned DEFAULT NULL,
  `latency_ms` int(10) unsigned DEFAULT NULL,
  `error_code` varchar(60) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_messages_conversation` (`conversation_id`,`id`),
  CONSTRAINT `fk_ai_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_messages`
--

LOCK TABLES `ai_messages` WRITE;
/*!40000 ALTER TABLE `ai_messages` DISABLE KEYS */;
INSERT INTO `ai_messages` VALUES (7,4,'user',NULL,'How do the respiratory and circulatory systems work together when I exercise?','complete',NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-10-25 09:31:25'),(8,4,'assistant','ask_materials','When you start exercising, your muscles demand more oxygen and create more carbon‑dioxide as a waste product.  \n\n1. **Respiratory response** – Your breathing becomes faster and deeper so that more oxygen can enter the lungs and more carbon‑dioxide can be expelled [1].  \n\n2. **Circulatory response** – At the same time, your heart beats faster and each beat pushes a larger volume of blood (increased stroke volume). This pumps more oxygen‑rich blood from the left ventricle through the systemic circuit to the working muscles, and carries the carbon‑dioxide‑rich blood back to the right side of the heart and then to the lungs [1][2].  \n\nTogether, the two systems continuously exchange gases: the lungs load the blood with oxygen and unload carbon‑dioxide, while the heart distributes that oxygen to cells that need it for energy production, and returns the waste gas to be exhaled.  \n\n**Check your understanding:**  \n- What signals in your body tell the heart to beat faster during exercise?  \n- How does the increased breathing rate help remove the extra carbon‑dioxide produced by your muscles?  \n\nFeel free to try answering these, and we can discuss any part that’s still unclear!','complete','sources','openai_compatible','openai/gpt-oss-120b',0,1084,337,1316,NULL,'2026-10-25 09:31:25'),(9,5,'user',NULL,'Gas exchange happens in the alveoli. Oxygen from the air goes into the blood in the capillaries, and carbon dioxide goes out of the blood so we can breathe it out.','complete',NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-10-24 10:31:39'),(10,5,'assistant','explain_back','**What you got right**  \n- You correctly said that gas exchange occurs in the alveoli, with oxygen moving from the air into the blood and carbon‑dioxide moving from the blood into the air to be exhaled [1].\n\n**What’s missing or could be added**  \n- The explanation doesn’t mention why this exchange is so fast: the alveoli have a huge total surface area, their walls are only one cell thick, and the moist lining lets gases dissolve before they diffuse [1].  \n- It also doesn’t note that the capillaries surrounding each alveolus are part of the circulatory system that carries the gases to and from the rest of the body [3].\n\n**Question for you**  \nCan you explain how the structure of the alveoli (surface area, wall thickness, and moist lining) helps oxygen and carbon‑dioxide move quickly between the air and the blood?','complete','sources','openai_compatible','openai/gpt-oss-120b',0,1526,482,1742,NULL,'2026-10-24 10:31:39'),(11,6,'user',NULL,'What is the difference between a dominant and a recessive allele?','complete',NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-10-26 07:31:52'),(12,6,'assistant','explain','A **dominant allele** is the version of a gene that can mask the effect of another version (the recessive allele) when they are together in one organism.  \nA **recessive allele** is the version that is hidden (does not show up) when a dominant allele is also present; it only determines the trait when the organism has two copies of the recessive allele.\n\nIn terms of genetics notation, we write the dominant allele with a capital letter (e.g., **T**) and the recessive allele with the same letter in lowercase (e.g., **t**) [1].  \n\n- **Genotype** = the exact pair of alleles an organism carries (e.g., **TT**, **Tt**, or **tt**).  \n- **Phenotype** = the observable trait that results from the genotype.\n\nIf an organism’s genotype is **TT** (two dominant alleles) or **Tt** (one dominant, one recessive), the dominant trait appears—in this case, a tall plant. Only when the genotype is **tt** (two recessive alleles) does the recessive trait appear, giving a short plant [1][2].\n\n**Everyday analogy:** Think of a light switch that has two positions: **ON** (dominant) and **OFF** (recessive). If the switch is in the **ON** position (even if the other side of the switch is **OFF**), the light shines (dominant trait shows). The light will be off only when both sides are **OFF** (both recessive alleles), so the recessive trait appears.\n\n---\n\n**Check your understanding:**  \nIf a pea plant has the genotype **Tt**, which trait will you see—tall or short? Why?','complete','sources','openai_compatible','openai/gpt-oss-120b',0,1425,448,1509,NULL,'2026-10-26 07:31:52'),(13,7,'user',NULL,'How do I solve x^2 - 5x + 6 = 0 by factoring?','complete',NULL,NULL,NULL,0,NULL,NULL,NULL,NULL,'2026-10-24 11:32:06'),(14,7,'assistant','explain','To factor a quadratic \\(ax^{2}+bx+c=0\\) you want two numbers that\n\n1. **multiply** to give the product \\(a\\cdot c\\) (the “constant term” times the leading coefficient), and  \n2. **add** to give the middle‑term coefficient \\(b\\).\n\nWhen you find those numbers, you can rewrite the middle term and then factor by grouping, or you can spot the factorisation directly as \\((x + m)(x + n)=0\\) if \\(a=1\\) [1].\n\n**Step‑by‑step example (different numbers)**  \n\nSolve \\(x^{2}-7x+12=0\\).\n\n1. Look for two integers whose product is \\(+12\\) (the constant term) and whose sum is \\(-7\\) (the coefficient of \\(x\\)).  \n   The pair \\(-3\\) and \\(-4\\) works because \\((-3)(-4)=12\\) and \\((-3)+(-4)=-7\\).\n\n2. Write the quadratic as \\((x-3)(x-4)=0\\).  \n\n3. Apply the zero‑product property: if a product equals zero, at least one factor must be zero.  \n   \\[\n   x-3=0 \\quad\\text{or}\\quad x-4=0\n   \\]\n   Hence \\(x=3\\) or \\(x=4\\).  \n\nNow try the same steps with your equation \\(x^{2}-5x+6=0\\):\n\n* What two numbers multiply to \\(+6\\) and add to \\(-5\\)?\n* Write the quadratic as a product of two binomials using those numbers.\n* Use the zero‑product property to find the solutions.\n\n*Check:* After you think you have the factors, plug each root back into the original equation to verify it makes the left side zero, just as the source shows [1].\n\n**Question:** What two numbers satisfy the “multiply to 6 and add to ‑5” condition, and how would you write the factored form of the equation?','complete','sources','openai_compatible','openai/gpt-oss-120b',0,1135,724,2108,NULL,'2026-10-24 11:32:06');
/*!40000 ALTER TABLE `ai_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_reports`
--

DROP TABLE IF EXISTS `ai_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `message_id` bigint(20) unsigned DEFAULT NULL,
  `reporter_id` int(10) unsigned DEFAULT NULL,
  `reason` enum('incorrect','unsafe','not_helpful','privacy','other') NOT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `answer_excerpt` text DEFAULT NULL,
  `status` enum('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `resolution_note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_reports_org_status` (`organization_id`,`status`,`created_at`),
  KEY `fk_ai_reports_message` (`message_id`),
  KEY `fk_ai_reports_reporter` (`reporter_id`),
  KEY `fk_ai_reports_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_ai_reports_message` FOREIGN KEY (`message_id`) REFERENCES `ai_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_reports_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_reports_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_reports`
--

LOCK TABLES `ai_reports` WRITE;
/*!40000 ALTER TABLE `ai_reports` DISABLE KEYS */;
INSERT INTO `ai_reports` VALUES (2,1,14,8,'not_helpful','I wanted it to use the equation from our worksheet, not a new example.','To factor a quadratic \\(ax^{2}+bx+c=0\\) you want two numbers that\n\n1. **multiply** to give the product \\(a\\cdot c\\) (the “constant term” times the leading coefficient), and  \n2. **add** to give the middle‑term coefficient \\(b\\).\n\nWhen you find those numbers, you can rewrite the middle term and then factor by grouping, or you can spot the factorisation directly as \\((x + m)(x + n)=0\\) if \\(a=1\\) [1].\n\n**Step‑by‑step example (different numbers)**  \n\nSolve \\(x^{2}-7x+12=0\\).\n\n1. Look for two integers whose product is \\(+12\\) (the constant term) and whose sum is \\(-7\\) (the coefficient of \\(x\\)).  \n   The pair \\(-3\\) and \\(-4\\) works because \\((-3)(-4)=12\\) and \\((-3)+(-4)=-7\\).\n\n2. Write the quadratic as \\((x-3)(x-4)=0\\).  \n\n3. Apply the zero‑product property: if a product equals zero, at least one factor must be zero.  \n   \\[\n   x-3=0 \\quad\\text{or}\\quad x-4=0\n   \\]\n   Hence \\(x=3\\) or \\(x=4\\).  \n\nNow try the same steps with your equation \\(x^{2}-5x+6=0\\):\n\n* What two numbers multiply to \\(+6\\) and add to \\(-5\\)?\n* Write the quadratic as a product of two binomials using those numbers.\n* Use the zero‑product property to find the solutions.\n\n*Check:* After you think you have the factors, plug each root back into the original equation to verify it makes the left side zero, just as the source shows [1].\n\n**Question:** What two numbers satisfy the “multiply to 6 and add to ‑5” condition, and how would you write the factored form of the equation?','open',NULL,NULL,NULL,'2026-10-24 12:32:18');
/*!40000 ALTER TABLE `ai_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_usage_events`
--

DROP TABLE IF EXISTS `ai_usage_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_usage_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `user_role` varchar(20) DEFAULT NULL,
  `feature` varchar(40) NOT NULL,
  `provider` varchar(40) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ok','error','rate_limited','cancelled','blocked') NOT NULL,
  `input_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `output_tokens` int(10) unsigned NOT NULL DEFAULT 0,
  `est_cost_usd` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `latency_ms` int(10) unsigned DEFAULT NULL,
  `error_code` varchar(60) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_usage_org_time` (`organization_id`,`created_at`),
  KEY `idx_ai_usage_user_time` (`user_id`,`created_at`),
  CONSTRAINT `fk_ai_usage_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_usage_events`
--

LOCK TABLES `ai_usage_events` WRITE;
/*!40000 ALTER TABLE `ai_usage_events` DISABLE KEYS */;
INSERT INTO `ai_usage_events` VALUES (4,1,2,'teacher','reading_version','openai_compatible','openai/gpt-oss-120b',0,'ok',1032,2121,0.000000,5031,NULL,'2026-10-23 10:29:49'),(5,1,2,'teacher','reading_version','openai_compatible','openai/gpt-oss-120b',0,'ok',1026,1461,0.000000,3699,NULL,'2026-10-23 11:30:05'),(6,1,2,'teacher','reading_version','openai_compatible','openai/gpt-oss-120b',0,'ok',943,1624,0.000000,4266,NULL,'2026-10-25 16:30:21'),(7,1,2,'teacher','quiz_draft','openai_compatible','openai/gpt-oss-120b',0,'ok',766,1131,0.000000,2816,NULL,'2026-10-25 11:00:36'),(8,1,2,'teacher','lesson_draft','openai_compatible','openai/gpt-oss-120b',0,'ok',1381,1660,0.000000,4048,NULL,'2026-10-24 11:30:56'),(9,1,2,'teacher','lesson_draft','openai_compatible','openai/gpt-oss-120b',0,'ok',1911,1611,0.000000,4001,NULL,'2026-10-24 11:30:56'),(10,1,6,'student','companion','openai_compatible','openai/gpt-oss-120b',0,'ok',1084,337,0.000000,1316,NULL,'2026-10-25 09:31:25'),(11,1,6,'student','companion','openai_compatible','openai/gpt-oss-120b',0,'ok',1526,482,0.000000,1742,NULL,'2026-10-24 10:31:39'),(12,1,6,'student','companion','openai_compatible','openai/gpt-oss-120b',0,'ok',1425,448,0.000000,1509,NULL,'2026-10-26 07:31:52'),(13,1,8,'student','companion','openai_compatible','openai/gpt-oss-120b',0,'ok',1135,724,0.000000,2108,NULL,'2026-10-24 11:32:06'),(14,1,6,'student','practice','openai_compatible','openai/gpt-oss-120b',0,'ok',1046,1173,0.000000,2855,NULL,'2026-10-24 14:32:28'),(15,1,6,'student','practice','openai_compatible','openai/gpt-oss-120b',0,'ok',1225,812,0.000000,2379,NULL,'2026-10-25 14:32:42');
/*!40000 ALTER TABLE `ai_usage_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `author_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_announcements_class` (`class_id`,`created_at`),
  KEY `fk_announcements_org` (`organization_id`),
  KEY `fk_announcements_author` (`author_id`),
  CONSTRAINT `fk_announcements_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_announcements_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_announcements_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,1,1,2,'Bring a stopwatch on Monday','For the breathing-rate lab, bring a stopwatch or a phone with a timer. You will work in pairs, so agree with your partner who brings it.','2026-10-23 12:24:04','2026-10-23 12:24:04'),(2,1,3,3,'Factoring quiz results are out','Scores and comments are now visible on the quiz page. If a step confuses you, compare it with Method 2 in the notes before Friday\'s class.','2026-10-25 16:24:04','2026-10-25 16:24:04'),(3,1,5,4,'Library visit on Thursday','We will meet at the library for the first 20 minutes of class. Bring your inference journal.','2026-10-24 12:24:04','2026-10-24 12:24:04');
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_overrides`
--

DROP TABLE IF EXISTS `assignment_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_overrides` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `assignment_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `due_at` datetime DEFAULT NULL,
  `extra_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `excused` tinyint(1) NOT NULL DEFAULT 0,
  `absent` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_overrides_assignment_student` (`assignment_id`,`student_id`),
  KEY `idx_overrides_student` (`student_id`),
  KEY `fk_overrides_org` (`organization_id`),
  KEY `fk_overrides_creator` (`created_by`),
  CONSTRAINT `fk_overrides_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_overrides_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_overrides_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_overrides_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_overrides_minutes` CHECK (`extra_minutes` <= 600)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_overrides`
--

LOCK TABLES `assignment_overrides` WRITE;
/*!40000 ALTER TABLE `assignment_overrides` DISABLE KEYS */;
INSERT INTO `assignment_overrides` VALUES (1,1,11,7,NULL,0,1,0,2,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(2,1,11,9,NULL,0,0,1,2,'2026-10-26 12:24:05','2026-10-26 12:24:05');
/*!40000 ALTER TABLE `assignment_overrides` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_resources`
--

DROP TABLE IF EXISTS `assignment_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_resources` (
  `assignment_id` int(10) unsigned NOT NULL,
  `material_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`assignment_id`,`material_id`),
  KEY `fk_assignment_resources_material` (`material_id`),
  CONSTRAINT `fk_assignment_resources_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_resources_material` FOREIGN KEY (`material_id`) REFERENCES `learning_materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_resources`
--

LOCK TABLES `assignment_resources` WRITE;
/*!40000 ALTER TABLE `assignment_resources` DISABLE KEYS */;
INSERT INTO `assignment_resources` VALUES (2,1),(3,2),(14,3);
/*!40000 ALTER TABLE `assignment_resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_versions`
--

DROP TABLE IF EXISTS `assignment_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `version_no` int(10) unsigned NOT NULL,
  `snapshot` longtext NOT NULL,
  `published_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assignment_versions_no` (`assignment_id`,`version_no`),
  KEY `fk_assignment_versions_publisher` (`published_by`),
  CONSTRAINT `fk_assignment_versions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_versions_publisher` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_versions`
--

LOCK TABLES `assignment_versions` WRITE;
/*!40000 ALTER TABLE `assignment_versions` DISABLE KEYS */;
INSERT INTO `assignment_versions` VALUES (1,1,1,'{\"title\":\"Respiratory and circulatory systems check\",\"points_possible\":7,\"questions\":[{\"id\":1,\"qtype\":\"multiple_choice\",\"prompt\":\"Where does gas exchange between the air and the blood take place?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"Trachea\"},{\"key\":\"B\",\"text\":\"Bronchi\"},{\"key\":\"C\",\"text\":\"Alveoli\"},{\"key\":\"D\",\"text\":\"Larynx\"}],\"answer_key\":{\"correct\":\"C\"},\"explanation\":\"Gases diffuse across the thin walls of the alveoli into and out of the surrounding capillaries.\",\"topic\":\"Gas exchange\"},{\"id\":2,\"qtype\":\"multiple_choice\",\"prompt\":\"Which chamber of the heart pumps oxygen-rich blood to the rest of the body?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"Right atrium\"},{\"key\":\"B\",\"text\":\"Right ventricle\"},{\"key\":\"C\",\"text\":\"Left atrium\"},{\"key\":\"D\",\"text\":\"Left ventricle\"}],\"answer_key\":{\"correct\":\"D\"},\"explanation\":\"The left ventricle has the thickest wall because it pumps blood around the whole body.\",\"topic\":\"The heart\"},{\"id\":3,\"qtype\":\"true_false\",\"prompt\":\"Veins carry blood away from the heart.\",\"points\":1,\"options\":[],\"answer_key\":{\"correct\":false},\"explanation\":\"Arteries carry blood away from the heart; veins carry it back.\",\"topic\":\"Blood vessels\"},{\"id\":4,\"qtype\":\"multiple_choice\",\"prompt\":\"Why do oxygen and carbon dioxide move across the walls of the alveoli so easily?\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"The walls are thick and muscular\"},{\"key\":\"B\",\"text\":\"The walls are one cell thick and moist\"},{\"key\":\"C\",\"text\":\"The alveoli contain valves\"},{\"key\":\"D\",\"text\":\"The air in the alveoli is under high pressure\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"Thin, moist walls and a large surface area let the gases diffuse quickly.\",\"topic\":\"Gas exchange\"},{\"id\":5,\"qtype\":\"true_false\",\"prompt\":\"When you inhale, the diaphragm contracts and moves down.\",\"points\":1,\"options\":[],\"answer_key\":{\"correct\":true},\"explanation\":\"The contracting diaphragm flattens, making the chest cavity larger so air flows in.\",\"topic\":\"Breathing\"},{\"id\":6,\"qtype\":\"short_answer\",\"prompt\":\"Name the tiny blood vessels where substances pass between the blood and the body\'s cells.\",\"points\":1,\"options\":[],\"answer_key\":{\"accepted\":[\"capillaries\",\"capillary\"],\"case_sensitive\":false},\"explanation\":\"Capillaries have walls one cell thick.\",\"topic\":\"Blood vessels\"}]}',2,'2026-10-26 12:24:04'),(2,2,1,'{\"title\":\"Lab report: breathing rate before and after exercise\",\"points_possible\":10,\"questions\":[]}',2,'2026-10-26 12:24:04'),(3,3,1,'{\"title\":\"Heredity basics\",\"points_possible\":8,\"questions\":[{\"id\":7,\"qtype\":\"multiple_choice\",\"prompt\":\"Two heterozygous tall pea plants (Tt) are crossed. What is the probability that an offspring is short?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"0\"},{\"key\":\"B\",\"text\":\"1 in 4\"},{\"key\":\"C\",\"text\":\"1 in 2\"},{\"key\":\"D\",\"text\":\"3 in 4\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"The cross gives TT, Tt, Tt and tt; only tt is short, so 1 in 4.\",\"topic\":\"Punnett squares\"},{\"id\":8,\"qtype\":\"true_false\",\"prompt\":\"A person with type AB blood shows both the A and the B allele because they are codominant.\",\"points\":1,\"options\":[],\"answer_key\":{\"correct\":true},\"explanation\":\"In codominance both alleles are fully expressed.\",\"topic\":\"Non-Mendelian inheritance\"},{\"id\":9,\"qtype\":\"multiple_choice\",\"prompt\":\"What do we call the trait that an organism shows?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"Genotype\"},{\"key\":\"B\",\"text\":\"Phenotype\"},{\"key\":\"C\",\"text\":\"Allele\"},{\"key\":\"D\",\"text\":\"Gamete\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"The phenotype is the observable trait; the genotype is the allele combination.\",\"topic\":\"Vocabulary\"},{\"id\":10,\"qtype\":\"short_answer\",\"prompt\":\"What is the term for different versions of the same gene?\",\"points\":1,\"options\":[],\"answer_key\":{\"accepted\":[\"alleles\",\"allele\"],\"case_sensitive\":false},\"explanation\":\"Different versions of a gene are alleles.\",\"topic\":\"Vocabulary\"},{\"id\":11,\"qtype\":\"essay\",\"prompt\":\"In three or four sentences, explain how two tall parent plants can have a short offspring.\",\"points\":4,\"options\":[],\"answer_key\":[],\"explanation\":null,\"topic\":\"Punnett squares\"}]}',2,'2026-10-26 12:24:04'),(4,5,1,'{\"title\":\"Respiratory and circulatory systems check\",\"points_possible\":7,\"questions\":[{\"id\":12,\"qtype\":\"multiple_choice\",\"prompt\":\"Where does gas exchange between the air and the blood take place?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"Trachea\"},{\"key\":\"B\",\"text\":\"Bronchi\"},{\"key\":\"C\",\"text\":\"Alveoli\"},{\"key\":\"D\",\"text\":\"Larynx\"}],\"answer_key\":{\"correct\":\"C\"},\"explanation\":\"Gases diffuse across the thin walls of the alveoli into and out of the surrounding capillaries.\",\"topic\":\"Gas exchange\"},{\"id\":13,\"qtype\":\"multiple_choice\",\"prompt\":\"Which chamber of the heart pumps oxygen-rich blood to the rest of the body?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"Right atrium\"},{\"key\":\"B\",\"text\":\"Right ventricle\"},{\"key\":\"C\",\"text\":\"Left atrium\"},{\"key\":\"D\",\"text\":\"Left ventricle\"}],\"answer_key\":{\"correct\":\"D\"},\"explanation\":\"The left ventricle has the thickest wall because it pumps blood around the whole body.\",\"topic\":\"The heart\"},{\"id\":14,\"qtype\":\"true_false\",\"prompt\":\"Veins carry blood away from the heart.\",\"points\":1,\"options\":[],\"answer_key\":{\"correct\":false},\"explanation\":\"Arteries carry blood away from the heart; veins carry it back.\",\"topic\":\"Blood vessels\"},{\"id\":15,\"qtype\":\"multiple_choice\",\"prompt\":\"Why do oxygen and carbon dioxide move across the walls of the alveoli so easily?\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"The walls are thick and muscular\"},{\"key\":\"B\",\"text\":\"The walls are one cell thick and moist\"},{\"key\":\"C\",\"text\":\"The alveoli contain valves\"},{\"key\":\"D\",\"text\":\"The air in the alveoli is under high pressure\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"Thin, moist walls and a large surface area let the gases diffuse quickly.\",\"topic\":\"Gas exchange\"},{\"id\":16,\"qtype\":\"true_false\",\"prompt\":\"When you inhale, the diaphragm contracts and moves down.\",\"points\":1,\"options\":[],\"answer_key\":{\"correct\":true},\"explanation\":\"The contracting diaphragm flattens, making the chest cavity larger so air flows in.\",\"topic\":\"Breathing\"},{\"id\":17,\"qtype\":\"short_answer\",\"prompt\":\"Name the tiny blood vessels where substances pass between the blood and the body\'s cells.\",\"points\":1,\"options\":[],\"answer_key\":{\"accepted\":[\"capillaries\",\"capillary\"],\"case_sensitive\":false},\"explanation\":\"Capillaries have walls one cell thick.\",\"topic\":\"Blood vessels\"}]}',2,'2026-10-26 12:24:04'),(5,6,1,'{\"title\":\"Solving quadratic equations by factoring\",\"points_possible\":7,\"questions\":[{\"id\":18,\"qtype\":\"multiple_choice\",\"prompt\":\"Which equation is quadratic?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"3x + 2 = 0\"},{\"key\":\"B\",\"text\":\"x^2 - 4x + 3 = 0\"},{\"key\":\"C\",\"text\":\"x^3 = 8\"},{\"key\":\"D\",\"text\":\"2\\/x = 5\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"The highest power of x is 2.\",\"topic\":\"Standard form\"},{\"id\":19,\"qtype\":\"multiple_choice\",\"prompt\":\"Solve by factoring: x^2 + 5x + 6 = 0\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"x = 2 or x = 3\"},{\"key\":\"B\",\"text\":\"x = -2 or x = -3\"},{\"key\":\"C\",\"text\":\"x = -1 or x = -6\"},{\"key\":\"D\",\"text\":\"x = 1 or x = 6\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"(x + 2)(x + 3) = 0, so x = -2 or x = -3.\",\"topic\":\"Factoring\"},{\"id\":20,\"qtype\":\"multiple_choice\",\"prompt\":\"Solve by factoring: x^2 - 7x + 10 = 0\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"x = 2 or x = 5\"},{\"key\":\"B\",\"text\":\"x = -2 or x = -5\"},{\"key\":\"C\",\"text\":\"x = 1 or x = 10\"},{\"key\":\"D\",\"text\":\"x = -1 or x = -10\"}],\"answer_key\":{\"correct\":\"A\"},\"explanation\":\"(x - 2)(x - 5) = 0.\",\"topic\":\"Factoring\"},{\"id\":21,\"qtype\":\"true_false\",\"prompt\":\"If (x - 4)(x + 1) = 0, then x = 4 or x = -1.\",\"points\":1,\"options\":[],\"answer_key\":{\"correct\":true},\"explanation\":\"Set each factor equal to zero.\",\"topic\":\"Zero product property\"},{\"id\":22,\"qtype\":\"short_answer\",\"prompt\":\"What is the positive solution of x^2 - 49 = 0?\",\"points\":1,\"options\":[],\"answer_key\":{\"accepted\":[\"7\",\"x = 7\",\"x=7\"],\"case_sensitive\":false},\"explanation\":\"x^2 = 49, so x = 7 or x = -7.\",\"topic\":\"Extracting square roots\"}]}',3,'2026-10-26 12:24:04'),(6,7,1,'{\"title\":\"Choosing a method: explain your reasoning\",\"points_possible\":10,\"questions\":[]}',3,'2026-10-26 12:24:05'),(7,8,1,'{\"title\":\"Solving quadratic equations by factoring\",\"points_possible\":7,\"questions\":[{\"id\":23,\"qtype\":\"multiple_choice\",\"prompt\":\"Which equation is quadratic?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"3x + 2 = 0\"},{\"key\":\"B\",\"text\":\"x^2 - 4x + 3 = 0\"},{\"key\":\"C\",\"text\":\"x^3 = 8\"},{\"key\":\"D\",\"text\":\"2\\/x = 5\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"The highest power of x is 2.\",\"topic\":\"Standard form\"},{\"id\":24,\"qtype\":\"multiple_choice\",\"prompt\":\"Solve by factoring: x^2 + 5x + 6 = 0\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"x = 2 or x = 3\"},{\"key\":\"B\",\"text\":\"x = -2 or x = -3\"},{\"key\":\"C\",\"text\":\"x = -1 or x = -6\"},{\"key\":\"D\",\"text\":\"x = 1 or x = 6\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"(x + 2)(x + 3) = 0, so x = -2 or x = -3.\",\"topic\":\"Factoring\"},{\"id\":25,\"qtype\":\"multiple_choice\",\"prompt\":\"Solve by factoring: x^2 - 7x + 10 = 0\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"x = 2 or x = 5\"},{\"key\":\"B\",\"text\":\"x = -2 or x = -5\"},{\"key\":\"C\",\"text\":\"x = 1 or x = 10\"},{\"key\":\"D\",\"text\":\"x = -1 or x = -10\"}],\"answer_key\":{\"correct\":\"A\"},\"explanation\":\"(x - 2)(x - 5) = 0.\",\"topic\":\"Factoring\"},{\"id\":26,\"qtype\":\"true_false\",\"prompt\":\"If (x - 4)(x + 1) = 0, then x = 4 or x = -1.\",\"points\":1,\"options\":[],\"answer_key\":{\"correct\":true},\"explanation\":\"Set each factor equal to zero.\",\"topic\":\"Zero product property\"},{\"id\":27,\"qtype\":\"short_answer\",\"prompt\":\"What is the positive solution of x^2 - 49 = 0?\",\"points\":1,\"options\":[],\"answer_key\":{\"accepted\":[\"7\",\"x = 7\",\"x=7\"],\"case_sensitive\":false},\"explanation\":\"x^2 = 49, so x = 7 or x = -7.\",\"topic\":\"Extracting square roots\"}]}',3,'2026-10-26 12:24:05'),(8,9,1,'{\"title\":\"Reading response: The jeepney stop\",\"points_possible\":10,\"questions\":[]}',4,'2026-10-26 12:24:05'),(9,10,1,'{\"title\":\"Inference journal: three clues\",\"points_possible\":10,\"questions\":[]}',4,'2026-10-26 12:24:05'),(10,11,1,'{\"title\":\"Short test on paper: breathing and circulation\",\"points_possible\":20,\"questions\":[{\"id\":28,\"qtype\":\"multiple_choice\",\"prompt\":\"Which structure carries air from the larynx to the bronchi?\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"Trachea\"},{\"key\":\"B\",\"text\":\"Alveoli\"},{\"key\":\"C\",\"text\":\"Diaphragm\"},{\"key\":\"D\",\"text\":\"Capillaries\"}],\"answer_key\":{\"correct\":\"A\"},\"explanation\":null,\"topic\":\"Breathing\"},{\"id\":29,\"qtype\":\"multiple_choice\",\"prompt\":\"Which blood vessels carry blood back to the heart?\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"Arteries\"},{\"key\":\"B\",\"text\":\"Veins\"},{\"key\":\"C\",\"text\":\"Capillaries\"},{\"key\":\"D\",\"text\":\"Alveoli\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":null,\"topic\":\"Blood vessels\"},{\"id\":30,\"qtype\":\"multiple_choice\",\"prompt\":\"What does the right ventricle pump blood to?\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"The brain\"},{\"key\":\"B\",\"text\":\"The lungs\"},{\"key\":\"C\",\"text\":\"The liver\"},{\"key\":\"D\",\"text\":\"The arms and legs\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":null,\"topic\":\"The heart\"},{\"id\":31,\"qtype\":\"true_false\",\"prompt\":\"The diaphragm relaxes and moves up when you exhale.\",\"points\":2,\"options\":[],\"answer_key\":{\"correct\":true},\"explanation\":null,\"topic\":\"Breathing\"},{\"id\":32,\"qtype\":\"true_false\",\"prompt\":\"Capillary walls are many cells thick.\",\"points\":2,\"options\":[],\"answer_key\":{\"correct\":false},\"explanation\":null,\"topic\":\"Blood vessels\"},{\"id\":33,\"qtype\":\"short_answer\",\"prompt\":\"Name the gas that the blood picks up in the alveoli.\",\"points\":2,\"options\":[],\"answer_key\":{\"accepted\":[\"oxygen\"],\"case_sensitive\":false},\"explanation\":null,\"topic\":\"Gas exchange\"},{\"id\":34,\"qtype\":\"essay\",\"prompt\":\"Explain how the respiratory and circulatory systems work together to deliver oxygen to a muscle cell.\",\"points\":8,\"options\":[],\"answer_key\":[],\"explanation\":null,\"topic\":\"Gas exchange\"}]}',2,'2026-10-26 12:24:05'),(11,12,1,'{\"title\":\"Performance task in class: model of the lungs\",\"points_possible\":20,\"questions\":[]}',2,'2026-10-26 12:24:05'),(12,14,1,'{\"title\":\"Inheritance patterns check\",\"points_possible\":7,\"questions\":[{\"id\":40,\"qtype\":\"multiple_choice\",\"prompt\":\"In guinea pigs, black fur (B) is dominant over white fur (b). What fraction of the offspring of a Bb × bb cross is expected to have white fur?\",\"points\":1,\"options\":[{\"key\":\"A\",\"text\":\"1\\/4\"},{\"key\":\"B\",\"text\":\"1\\/2\"},{\"key\":\"C\",\"text\":\"3\\/4\"},{\"key\":\"D\",\"text\":\"None of them\"}],\"answer_key\":{\"correct\":\"B\"},\"explanation\":\"Bb × bb gives Bb, Bb, bb and bb, so half of the offspring have white fur.\",\"topic\":\"Punnett squares\"},{\"id\":41,\"qtype\":\"multiple_choice\",\"prompt\":\"In four o\'clock flowers, red and white show incomplete dominance, so Rr plants are pink. Two pink plants are crossed. Which ratio of offspring is expected?\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"All pink\"},{\"key\":\"B\",\"text\":\"3 red : 1 white\"},{\"key\":\"C\",\"text\":\"1 red : 2 pink : 1 white\"},{\"key\":\"D\",\"text\":\"1 red : 1 white\"}],\"answer_key\":{\"correct\":\"C\"},\"explanation\":\"Rr × Rr gives RR, Rr, Rr and rr: one red, two pink and one white.\",\"topic\":\"Non-Mendelian inheritance\"},{\"id\":42,\"qtype\":\"multiple_choice\",\"prompt\":\"A mother has type A blood with genotype IAi, and a father has type B blood with genotype IBi. Which blood types are possible for their children?\",\"points\":2,\"options\":[{\"key\":\"A\",\"text\":\"A and B only\"},{\"key\":\"B\",\"text\":\"AB only\"},{\"key\":\"C\",\"text\":\"A, B, AB and O\"},{\"key\":\"D\",\"text\":\"O only\"}],\"answer_key\":{\"correct\":\"C\"},\"explanation\":\"IAi × IBi gives IAIB (type AB), IAi (type A), IBi (type B) and ii (type O).\",\"topic\":\"Non-Mendelian inheritance\"},{\"id\":43,\"qtype\":\"true_false\",\"prompt\":\"An organism\'s genotype is the trait you can observe, such as the color of its fur.\",\"points\":1,\"options\":[],\"answer_key\":{\"correct\":false},\"explanation\":\"False. The trait you can observe is the phenotype. The genotype is the pair of alleles, such as Bb.\",\"topic\":\"Vocabulary\"},{\"id\":44,\"qtype\":\"short_answer\",\"prompt\":\"What word describes an organism with two different alleles for a gene, such as Bb?\",\"points\":1,\"options\":[],\"answer_key\":{\"accepted\":[\"heterozygous\",\"heterozygote\"],\"case_sensitive\":false},\"explanation\":\"Heterozygous: two different alleles. Two of the same, such as BB or bb, is homozygous.\",\"topic\":\"Vocabulary\"}]}',2,'2026-10-25 16:30:00');
/*!40000 ALTER TABLE `assignment_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `module_id` int(10) unsigned DEFAULT NULL,
  `kind` enum('assignment','quiz','paper') NOT NULL,
  `title` varchar(200) NOT NULL,
  `instructions` mediumtext DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `category` varchar(60) DEFAULT NULL,
  `grading_period_id` int(10) unsigned DEFAULT NULL,
  `status` enum('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  `opens_at` datetime DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `late_policy` enum('not_accepted','accepted','penalty') NOT NULL DEFAULT 'accepted',
  `late_penalty_pct` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `time_limit_minutes` smallint(5) unsigned DEFAULT NULL,
  `points_possible` decimal(7,2) NOT NULL DEFAULT 0.00,
  `allow_text` tinyint(1) NOT NULL DEFAULT 1,
  `allow_files` tinyint(1) NOT NULL DEFAULT 0,
  `allow_resubmission` tinyint(1) NOT NULL DEFAULT 0,
  `anti_cheating` tinyint(1) NOT NULL DEFAULT 1,
  `rubric` longtext DEFAULT NULL,
  `feedback_release` enum('manual','immediate') NOT NULL DEFAULT 'manual',
  `answer_key_release` enum('never','after_submit','after_due','manual') NOT NULL DEFAULT 'after_due',
  `answers_released_at` datetime DEFAULT NULL,
  `ai_assisted` tinyint(1) NOT NULL DEFAULT 0,
  `current_version_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `copied_from_id` int(10) unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_assignments_class` (`class_id`,`status`,`due_at`),
  KEY `idx_assignments_org_due` (`organization_id`,`status`,`due_at`),
  KEY `fk_assignments_module` (`module_id`),
  KEY `fk_assignments_creator` (`created_by`),
  KEY `fk_assignments_current_version` (`current_version_id`),
  KEY `idx_assignments_copied_from` (`copied_from_id`),
  KEY `fk_assignments_grading_period` (`grading_period_id`),
  FULLTEXT KEY `ft_assignments_text` (`title`,`instructions`),
  CONSTRAINT `fk_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_copied_from` FOREIGN KEY (`copied_from_id`) REFERENCES `assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignments_current_version` FOREIGN KEY (`current_version_id`) REFERENCES `assignment_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignments_grading_period` FOREIGN KEY (`grading_period_id`) REFERENCES `grading_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignments_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignments_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_assignments_penalty` CHECK (`late_penalty_pct` <= 100),
  CONSTRAINT `chk_assignments_attempts` CHECK (`max_attempts` between 1 and 20),
  CONSTRAINT `chk_assignments_window` CHECK (`opens_at` is null or `due_at` is null or `due_at` > `opens_at`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
INSERT INTO `assignments` VALUES (1,1,1,1,'quiz','Respiratory and circulatory systems check','Answer all six questions. You have 20 minutes once you start, and two attempts. Your best attempt counts.\n\nThe notes on gas exchange and blood vessels will help.','Explain gas exchange; identify the parts of the heart and blood vessels.','written',NULL,'published','2026-10-19 23:00:00','2026-10-24 15:59:00','not_accepted',0,2,20,7.00,0,0,0,1,NULL,'manual','after_due',NULL,0,1,2,NULL,'2026-10-19 07:30:00',NULL,'2026-10-19 07:30:00','2026-10-26 12:24:04'),(2,1,1,1,'assignment','Lab report: breathing rate before and after exercise','Work with your partner to count breaths per minute at rest, right after two minutes of jumping jacks, and after five minutes of rest.\n\nIn your report:\n\n1. Record your data in a table.\n2. Describe the pattern you see.\n3. Explain the pattern using **both** the respiratory and the circulatory systems.\n4. Write one question you would test next.\n\nYou may type your report below, attach a photo of your table, or both.','Collect and present data; explain how the two systems respond to exercise.','performance',NULL,'published','2026-10-20 23:00:00','2026-10-28 15:59:00','penalty',10,1,NULL,10.00,1,1,0,1,'[{\"id\":\"c1\",\"title\":\"Data and observations\",\"points\":4,\"description\":\"Accurate table with all three readings and a clear description of the pattern.\"},{\"id\":\"c2\",\"title\":\"Explanation using both body systems\",\"points\":4,\"description\":\"Links breathing rate and heart rate to the muscles\' need for oxygen.\"},{\"id\":\"c3\",\"title\":\"Next question\",\"points\":2,\"description\":\"A testable question that follows from the results.\"}]','manual','never',NULL,0,2,2,NULL,'2026-10-20 23:00:00',NULL,'2026-10-20 23:00:00','2026-10-26 12:24:04'),(3,1,1,2,'quiz','Heredity basics','Five questions on the heredity notes. The last question is written and will be read by your teacher. 20 minutes, one attempt.',NULL,'written',NULL,'published','2026-10-26 10:24:04','2026-11-01 15:59:00','accepted',0,1,20,8.00,0,0,0,1,NULL,'manual','after_due',NULL,0,3,2,NULL,'2026-10-25 08:00:00',NULL,'2026-10-25 08:00:00','2026-10-26 12:24:04'),(4,1,1,1,'assignment','Concept map: from air to cell','Draw a concept map that follows one oxygen molecule from the air to a muscle cell.',NULL,'performance',NULL,'draft',NULL,'2026-11-04 15:59:00','accepted',0,1,NULL,10.00,0,1,0,1,NULL,'manual','after_due',NULL,0,NULL,2,NULL,NULL,NULL,'2026-10-12 12:24:04','2026-10-12 12:24:04'),(5,1,2,3,'quiz','Respiratory and circulatory systems check','Answer all six questions. You have 20 minutes once you start, and two attempts. Your best attempt counts.\n\nThe notes on gas exchange and blood vessels will help.',NULL,'written',NULL,'published','2026-10-19 23:00:00','2026-10-24 15:59:00','not_accepted',0,2,20,7.00,0,0,0,1,NULL,'manual','after_due',NULL,0,4,2,NULL,'2026-10-19 07:40:00',NULL,'2026-10-19 07:40:00','2026-10-26 12:24:04'),(6,1,3,5,'quiz','Solving quadratic equations by factoring','Five questions. Show your work on scratch paper; only the final answer is recorded here.',NULL,'written',NULL,'published','2026-10-16 23:00:00','2026-10-25 13:00:00','not_accepted',0,1,25,7.00,0,0,0,1,NULL,'manual','after_due',NULL,0,5,3,NULL,'2026-10-16 06:00:00',NULL,'2026-10-16 06:00:00','2026-10-26 12:24:04'),(7,1,3,5,'assignment','Choosing a method: explain your reasoning','Solve each equation using the method you think is best, and explain in one or two sentences why you chose it.\n\n1. $x^2 - 81 = 0$\n2. $x^2 + 3x - 10 = 0$\n3. $2x^2 + 3x - 4 = 0$',NULL,'performance',NULL,'published','2026-10-23 23:00:00','2026-10-30 15:59:00','accepted',0,1,NULL,10.00,1,1,0,1,'[{\"id\":\"c1\",\"title\":\"Correct solutions\",\"points\":6,\"description\":\"Two points per equation, including both roots where they exist.\"},{\"id\":\"c2\",\"title\":\"Reasoning\",\"points\":4,\"description\":\"Explains why the method fits each equation.\"}]','manual','never',NULL,0,6,3,NULL,'2026-10-23 23:00:00',NULL,'2026-10-23 23:00:00','2026-10-26 12:24:05'),(8,1,4,6,'quiz','Solving quadratic equations by factoring','Five questions on factoring.',NULL,'written',NULL,'published','2026-10-26 23:00:00','2026-10-31 13:00:00','accepted',0,1,25,7.00,0,0,0,1,NULL,'manual','after_due',NULL,0,7,3,NULL,'2026-10-26 10:24:05',NULL,'2026-10-26 10:24:05','2026-10-26 12:24:05'),(9,1,5,7,'assignment','Reading response: The jeepney stop','Read the practice passage in \"Making inferences while reading\". What can you infer about Mara? Support your inference with at least two clues from the text.',NULL,'written',NULL,'published','2026-10-16 23:00:00','2026-10-21 15:59:00','accepted',0,1,NULL,10.00,1,0,0,1,'[{\"id\":\"c1\",\"title\":\"Inference\",\"points\":4,\"description\":\"States a reasonable inference that goes beyond what the text says directly.\"},{\"id\":\"c2\",\"title\":\"Evidence\",\"points\":4,\"description\":\"Uses at least two specific clues from the passage.\"},{\"id\":\"c3\",\"title\":\"Clarity\",\"points\":2,\"description\":\"Clear, complete sentences.\"}]','manual','never',NULL,0,8,4,NULL,'2026-10-16 23:00:00',NULL,'2026-10-16 23:00:00','2026-10-26 12:24:05'),(10,1,5,7,'assignment','Inference journal: three clues','Choose a short story or a news article you read this week. Write down three clues from it and the inference each clue supports. Use the clue + what I know = inference formula.',NULL,'performance',NULL,'published','2026-10-18 23:00:00','2026-10-27 13:00:00','penalty',10,1,NULL,10.00,1,0,0,1,'[{\"id\":\"c1\",\"title\":\"Three clue–inference pairs\",\"points\":6,\"description\":\"Each clue is quoted and leads to a reasonable inference.\"},{\"id\":\"c2\",\"title\":\"Use of the formula\",\"points\":2,\"description\":\"Shows the clue + what I know = inference reasoning.\"},{\"id\":\"c3\",\"title\":\"Clarity\",\"points\":2,\"description\":\"Clear, complete sentences.\"}]','manual','never',NULL,0,9,4,NULL,'2026-10-18 23:00:00',NULL,'2026-10-18 23:00:00','2026-10-26 12:24:05'),(11,1,1,1,'paper','Short test on paper: breathing and circulation','Written in class. Answer all questions on the test paper; the last question needs a short paragraph.',NULL,'periodic',NULL,'published',NULL,'2026-10-23 02:00:00','accepted',0,1,NULL,20.00,0,0,0,1,NULL,'manual','never',NULL,0,10,2,NULL,'2026-10-15 23:00:00',NULL,'2026-10-15 23:00:00','2026-10-26 12:24:05'),(12,1,1,1,'paper','Performance task in class: model of the lungs','In groups, you will build a working model of the lungs from a plastic bottle, balloons and a rubber sheet, then explain how it shows breathing in and out.\n\nBring: one 1.5-litre plastic bottle and two balloons.',NULL,'performance',NULL,'published',NULL,'2026-10-31 05:00:00','accepted',0,1,NULL,20.00,0,0,0,1,'[{\"id\":\"c1\",\"title\":\"Working model\",\"points\":8,\"description\":\"The model shows the lungs filling and emptying as the sheet moves.\"},{\"id\":\"c2\",\"title\":\"Explanation\",\"points\":8,\"description\":\"Explains how the diaphragm changes the space in the chest.\"},{\"id\":\"c3\",\"title\":\"Teamwork and care\",\"points\":4,\"description\":\"Everyone takes part; materials are handled safely.\"}]','manual','never',NULL,0,11,2,NULL,'2026-10-23 23:00:00',NULL,'2026-10-23 23:00:00','2026-10-26 12:24:05'),(13,1,1,1,'quiz','The heart and blood vessels','Answer each question. You have one attempt. Read the breathing and circulation notes first.',NULL,'written',NULL,'draft',NULL,'2026-11-04 15:59:00','accepted',0,1,20,5.00,0,0,0,1,NULL,'manual','after_due',NULL,1,NULL,2,NULL,NULL,NULL,'2026-10-25 10:30:33','2026-10-26 12:30:36'),(14,1,1,2,'quiz','Inheritance patterns check','Five questions from the Module 2 review sheet. Work out each cross on scrap paper first.\n\n15 minutes, one attempt.','Predict the results of crosses with dominant, incomplete and codominant alleles.','written',NULL,'published',NULL,'2026-10-31 15:59:00','accepted',0,1,15,7.00,0,0,0,1,NULL,'manual','after_due',NULL,0,12,2,NULL,'2026-10-25 16:30:00',NULL,'2026-10-25 16:30:00','2026-10-25 16:30:00');
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned DEFAULT NULL,
  `actor_id` int(10) unsigned DEFAULT NULL,
  `actor_role` varchar(20) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `target_type` varchar(40) DEFAULT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_org_time` (`organization_id`,`created_at`),
  KEY `idx_audit_actor` (`actor_id`,`created_at`),
  KEY `idx_audit_action` (`action`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_audit_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (65,1,1,'admin','class.created','class',1,'{\"name\":\"Science 9 \\u2013 Narra\"}',NULL,'2026-07-08 12:24:06'),(66,1,1,'admin','users.imported',NULL,NULL,'{\"created\":18,\"skipped\":0}',NULL,'2026-07-13 12:24:06'),(67,1,1,'admin','settings.grading_updated',NULL,NULL,'{\"model\":\"weighted\"}',NULL,'2026-07-18 12:24:06'),(68,1,1,'admin','user.invited','user',24,'{\"role\":\"student\"}',NULL,'2026-10-24 12:24:06'),(69,1,1,'admin','user.invited','user',5,'{\"role\":\"teacher\"}',NULL,'2026-10-24 12:24:06'),(70,1,1,'admin','user.deactivated','user',25,'{\"reason\":\"Transferred\"}',NULL,'2026-10-06 12:24:06'),(71,1,2,'teacher','material.published','material',2,NULL,NULL,'2026-10-24 12:24:06'),(72,1,2,'teacher','assignment.published','assignment',3,NULL,NULL,'2026-10-25 12:24:06'),(73,1,3,'teacher','submission.feedback_released','assignment',6,'{\"count\":9}',NULL,'2026-10-25 16:24:06'),(74,1,4,'teacher','gradebook.exported','class',5,'{\"format\":\"csv\"}',NULL,'2026-10-23 12:24:06'),(75,1,2,'teacher','reading_version.approved','reading_version',1,'{\"kind\":\"filipino\",\"material_id\":1}',NULL,'2026-10-23 10:29:49'),(76,1,2,'teacher','reading_version.approved','reading_version',2,'{\"kind\":\"simpler\",\"material_id\":1}',NULL,'2026-10-23 11:30:05'),(77,1,2,'teacher','reading_version.saved','reading_version',3,'{\"kind\":\"filipino\",\"material_id\":2}',NULL,'2026-10-25 16:30:21'),(78,1,2,'teacher','assignment.created','assignment',14,'{\"kind\":\"quiz\"}',NULL,'2026-10-25 16:30:00'),(79,1,2,'teacher','assignment.published','assignment',14,NULL,NULL,'2026-10-25 16:30:00');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_memberships`
--

DROP TABLE IF EXISTS `class_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `class_memberships` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `role` enum('teacher','student') NOT NULL,
  `status` enum('active','withdrawn') NOT NULL DEFAULT 'active',
  `joined_via` enum('admin','teacher','code','import') NOT NULL DEFAULT 'admin',
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  `withdrawn_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_memberships_class_user` (`class_id`,`user_id`),
  KEY `idx_memberships_user` (`user_id`,`status`,`role`),
  KEY `idx_memberships_class_role` (`class_id`,`role`,`status`),
  CONSTRAINT `fk_memberships_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_memberships_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_memberships`
--

LOCK TABLES `class_memberships` WRITE;
/*!40000 ALTER TABLE `class_memberships` DISABLE KEYS */;
INSERT INTO `class_memberships` VALUES (1,1,2,'teacher','active','admin','2026-07-08 12:24:04',NULL),(2,1,6,'student','active','import','2026-07-13 12:24:04',NULL),(3,1,7,'student','active','import','2026-07-13 12:24:04',NULL),(4,1,8,'student','active','import','2026-07-13 12:24:04',NULL),(5,1,9,'student','active','import','2026-07-13 12:24:04',NULL),(6,1,10,'student','active','import','2026-07-13 12:24:04',NULL),(7,1,11,'student','active','import','2026-07-13 12:24:04',NULL),(8,1,12,'student','active','import','2026-07-13 12:24:04',NULL),(9,1,13,'student','active','import','2026-07-13 12:24:04',NULL),(10,1,14,'student','active','import','2026-07-13 12:24:04',NULL),(11,1,15,'student','active','import','2026-07-13 12:24:04',NULL),(12,1,16,'student','active','import','2026-07-13 12:24:04',NULL),(13,1,17,'student','active','import','2026-07-13 12:24:04',NULL),(14,1,24,'student','active','admin','2026-10-24 12:24:04',NULL),(15,1,25,'student','withdrawn','import','2026-07-13 12:24:04','2026-10-06 12:24:04'),(16,2,2,'teacher','active','admin','2026-07-08 12:24:04',NULL),(17,2,18,'student','active','import','2026-07-13 12:24:04',NULL),(18,2,19,'student','active','import','2026-07-13 12:24:04',NULL),(19,2,20,'student','active','import','2026-07-13 12:24:04',NULL),(20,2,21,'student','active','import','2026-07-13 12:24:04',NULL),(21,2,22,'student','active','import','2026-07-13 12:24:04',NULL),(22,2,23,'student','active','import','2026-07-13 12:24:04',NULL),(23,3,3,'teacher','active','admin','2026-07-08 12:24:04',NULL),(24,3,6,'student','active','import','2026-07-13 12:24:04',NULL),(25,3,7,'student','active','import','2026-07-13 12:24:04',NULL),(26,3,8,'student','active','import','2026-07-13 12:24:04',NULL),(27,3,9,'student','active','import','2026-07-13 12:24:04',NULL),(28,3,10,'student','active','import','2026-07-13 12:24:04',NULL),(29,3,11,'student','active','import','2026-07-13 12:24:04',NULL),(30,3,12,'student','active','import','2026-07-13 12:24:04',NULL),(31,3,13,'student','active','import','2026-07-13 12:24:04',NULL),(32,3,14,'student','active','import','2026-07-13 12:24:04',NULL),(33,3,15,'student','active','import','2026-07-13 12:24:04',NULL),(34,3,16,'student','active','import','2026-07-13 12:24:04',NULL),(35,3,17,'student','active','import','2026-07-13 12:24:04',NULL),(36,3,24,'student','active','admin','2026-10-24 12:24:04',NULL),(37,3,25,'student','withdrawn','import','2026-07-13 12:24:04','2026-10-06 12:24:04'),(38,4,3,'teacher','active','admin','2026-07-08 12:24:04',NULL),(39,4,18,'student','active','import','2026-07-13 12:24:04',NULL),(40,4,19,'student','active','import','2026-07-13 12:24:04',NULL),(41,4,20,'student','active','import','2026-07-13 12:24:04',NULL),(42,4,21,'student','active','import','2026-07-13 12:24:04',NULL),(43,4,22,'student','active','import','2026-07-13 12:24:04',NULL),(44,4,23,'student','active','import','2026-07-13 12:24:04',NULL),(45,5,4,'teacher','active','admin','2026-07-08 12:24:04',NULL),(46,5,6,'student','active','import','2026-07-13 12:24:04',NULL),(47,5,7,'student','active','import','2026-07-13 12:24:04',NULL),(48,5,8,'student','active','import','2026-07-13 12:24:04',NULL),(49,5,9,'student','active','import','2026-07-13 12:24:04',NULL),(50,5,10,'student','active','import','2026-07-13 12:24:04',NULL),(51,5,11,'student','active','import','2026-07-13 12:24:04',NULL),(52,5,12,'student','active','import','2026-07-13 12:24:04',NULL),(53,5,13,'student','active','import','2026-07-13 12:24:04',NULL),(54,5,14,'student','active','import','2026-07-13 12:24:04',NULL),(55,5,15,'student','active','import','2026-07-13 12:24:04',NULL),(56,5,16,'student','active','import','2026-07-13 12:24:04',NULL),(57,5,17,'student','active','import','2026-07-13 12:24:04',NULL),(58,5,24,'student','active','admin','2026-10-24 12:24:04',NULL),(59,5,25,'student','withdrawn','import','2026-07-13 12:24:04','2026-10-06 12:24:04');
/*!40000 ALTER TABLE `class_memberships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `classes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `academic_period_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(140) NOT NULL,
  `section` varchar(60) DEFAULT NULL,
  `section_id` int(10) unsigned DEFAULT NULL,
  `grade_level` varchar(40) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','archived') NOT NULL DEFAULT 'active',
  `join_code` varchar(12) DEFAULT NULL,
  `join_code_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `join_code_expires_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_classes_join_code` (`join_code`),
  KEY `idx_classes_org` (`organization_id`,`status`),
  KEY `idx_classes_subject` (`subject_id`),
  KEY `fk_classes_period` (`academic_period_id`),
  KEY `fk_classes_creator` (`created_by`),
  KEY `idx_classes_section` (`section_id`),
  CONSTRAINT `fk_classes_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_classes_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_classes_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_classes_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_classes_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,1,1,2,'Science 9 – Narra','Narra',1,'Grade 9',NULL,'active','SCN9QX',1,'2026-11-05 15:59:00',1,'2026-07-08 12:24:04','2026-07-08 12:24:04'),(2,1,1,2,'Science 9 – Molave','Molave',2,'Grade 9',NULL,'active',NULL,0,NULL,1,'2026-07-08 12:24:04','2026-07-08 12:24:04'),(3,1,2,2,'Mathematics 9 – Narra','Narra',1,'Grade 9',NULL,'active','MTN9KP',1,'2026-11-05 15:59:00',1,'2026-07-08 12:24:04','2026-07-08 12:24:04'),(4,1,2,2,'Mathematics 9 – Molave','Molave',2,'Grade 9',NULL,'active',NULL,0,NULL,1,'2026-07-08 12:24:04','2026-07-08 12:24:04'),(5,1,3,2,'English 9 – Narra','Narra',1,'Grade 9',NULL,'active',NULL,0,NULL,1,'2026-07-08 12:24:04','2026-07-08 12:24:04');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comment_bank`
--

DROP TABLE IF EXISTS `comment_bank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comment_bank` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `body` text NOT NULL,
  `criterion` varchar(160) DEFAULT NULL,
  `uses` int(10) unsigned NOT NULL DEFAULT 0,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comment_bank_user` (`user_id`,`uses`),
  KEY `fk_comment_bank_org` (`organization_id`),
  CONSTRAINT `fk_comment_bank_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_bank_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comment_bank`
--

LOCK TABLES `comment_bank` WRITE;
/*!40000 ALTER TABLE `comment_bank` DISABLE KEYS */;
/*!40000 ALTER TABLE `comment_bank` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `competencies`
--

DROP TABLE IF EXISTS `competencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `code` varchar(60) NOT NULL,
  `description` varchar(1000) NOT NULL,
  `subject_id` int(10) unsigned DEFAULT NULL,
  `grade_level` varchar(40) DEFAULT NULL,
  `quarter` tinyint(3) unsigned DEFAULT NULL,
  `is_sample` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_competencies_code` (`organization_id`,`code`),
  KEY `idx_competencies_subject` (`organization_id`,`subject_id`,`grade_level`),
  KEY `fk_competencies_subject` (`subject_id`),
  CONSTRAINT `fk_competencies_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_competencies_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `competencies`
--

LOCK TABLES `competencies` WRITE;
/*!40000 ALTER TABLE `competencies` DISABLE KEYS */;
INSERT INTO `competencies` VALUES (1,1,'SCI9-Q1-01','Explain how the respiratory and circulatory systems work together to bring oxygen to the cells and remove carbon dioxide.',1,'Grade 9',1,0,NULL,'2026-07-18 12:24:05','2026-07-18 12:24:05'),(2,1,'SCI9-Q1-02','Identify the parts of the heart and the blood vessels and describe what each one does.',1,'Grade 9',1,0,NULL,'2026-07-18 12:24:05','2026-07-18 12:24:05'),(3,1,'SCI9-Q1-03','Describe how the diaphragm and the ribs change the size of the chest cavity during breathing.',1,'Grade 9',1,0,NULL,'2026-07-18 12:24:05','2026-07-18 12:24:05'),(4,1,'SCI9-Q2-01','Explain how traits pass from parents to offspring through genes and alleles, including patterns beyond simple dominance.',1,'Grade 9',2,0,NULL,'2026-07-18 12:24:05','2026-07-18 12:24:05'),(5,1,'SCI9-Q2-02','Use a Punnett square to predict the offspring of a cross and express the results as ratios.',1,'Grade 9',2,0,NULL,'2026-07-18 12:24:05','2026-07-18 12:24:05');
/*!40000 ALTER TABLE `competencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `content_views`
--

DROP TABLE IF EXISTS `content_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `content_views` (
  `user_id` int(10) unsigned NOT NULL,
  `content_type` enum('material','lesson','assignment') NOT NULL,
  `content_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `view_count` int(10) unsigned NOT NULL DEFAULT 1,
  `first_viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`content_type`,`content_id`),
  KEY `idx_content_views_recent` (`user_id`,`last_viewed_at`),
  KEY `fk_content_views_class` (`class_id`),
  CONSTRAINT `fk_content_views_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_content_views_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_views`
--

LOCK TABLES `content_views` WRITE;
/*!40000 ALTER TABLE `content_views` DISABLE KEYS */;
INSERT INTO `content_views` VALUES (6,'material',1,1,4,'2026-10-20 12:24:05','2026-10-25 09:24:05'),(6,'material',2,1,1,'2026-10-20 12:24:05','2026-10-25 10:24:05'),(6,'material',6,3,3,'2026-10-20 12:24:05','2026-10-23 12:24:05'),(6,'material',8,5,2,'2026-10-20 12:24:05','2026-10-21 12:24:05');
/*!40000 ALTER TABLE `content_views` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grading_history`
--

DROP TABLE IF EXISTS `grading_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grading_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` int(10) unsigned NOT NULL,
  `actor_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grading_history_submission` (`submission_id`,`created_at`),
  KEY `fk_grading_history_actor` (`actor_id`),
  CONSTRAINT `fk_grading_history_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_grading_history_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grading_history`
--

LOCK TABLES `grading_history` WRITE;
/*!40000 ALTER TABLE `grading_history` DISABLE KEYS */;
INSERT INTO `grading_history` VALUES (1,1,6,'submitted','{\"late\":false,\"auto_score\":5,\"penalty_pct\":0}','2026-10-23 07:10:00'),(2,2,7,'submitted','{\"late\":false,\"auto_score\":3,\"penalty_pct\":0}','2026-10-22 08:13:00'),(3,3,8,'submitted','{\"late\":false,\"auto_score\":7,\"penalty_pct\":0}','2026-10-21 09:16:00'),(4,4,9,'submitted','{\"late\":false,\"auto_score\":4,\"penalty_pct\":0}','2026-10-23 10:19:00'),(5,5,10,'submitted','{\"late\":false,\"auto_score\":7,\"penalty_pct\":0}','2026-10-22 11:22:00'),(6,6,12,'submitted','{\"late\":false,\"auto_score\":6,\"penalty_pct\":0}','2026-10-23 07:28:00'),(7,7,14,'submitted','{\"late\":false,\"auto_score\":4,\"penalty_pct\":0}','2026-10-21 09:34:00'),(8,8,15,'submitted','{\"late\":false,\"auto_score\":1,\"penalty_pct\":0}','2026-10-23 10:37:00'),(9,9,16,'submitted','{\"late\":false,\"auto_score\":3,\"penalty_pct\":0}','2026-10-22 11:40:00'),(10,10,17,'submitted','{\"late\":false,\"auto_score\":3,\"penalty_pct\":0}','2026-10-21 12:43:00'),(11,1,2,'released',NULL,'2026-10-26 12:24:04'),(12,2,2,'released',NULL,'2026-10-26 12:24:04'),(13,3,2,'released',NULL,'2026-10-26 12:24:04'),(14,4,2,'released',NULL,'2026-10-26 12:24:04'),(15,5,2,'released',NULL,'2026-10-26 12:24:04'),(16,6,2,'released',NULL,'2026-10-26 12:24:04'),(17,7,2,'released',NULL,'2026-10-26 12:24:04'),(18,8,2,'released',NULL,'2026-10-26 12:24:04'),(19,9,2,'released',NULL,'2026-10-26 12:24:04'),(20,10,2,'released',NULL,'2026-10-26 12:24:04'),(21,11,8,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-24 10:05:00'),(22,12,12,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-25 11:12:00'),(23,13,14,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-24 12:19:00'),(24,14,7,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-25 13:26:00'),(25,15,16,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-24 14:33:00'),(26,18,8,'submitted','{\"late\":false,\"auto_score\":4,\"penalty_pct\":0}','2026-10-26 11:44:04'),(27,19,16,'submitted','{\"late\":false,\"auto_score\":1,\"penalty_pct\":0}','2026-10-26 11:09:04'),(28,20,18,'submitted','{\"late\":false,\"auto_score\":4,\"penalty_pct\":0}','2026-10-23 08:10:00'),(29,21,19,'submitted','{\"late\":false,\"auto_score\":3,\"penalty_pct\":0}','2026-10-22 08:15:00'),(30,22,20,'submitted','{\"late\":false,\"auto_score\":6,\"penalty_pct\":0}','2026-10-23 08:20:00'),(31,23,22,'submitted','{\"late\":false,\"auto_score\":2,\"penalty_pct\":0}','2026-10-23 08:30:00'),(32,24,23,'submitted','{\"late\":false,\"auto_score\":2,\"penalty_pct\":0}','2026-10-22 08:35:00'),(33,25,6,'submitted','{\"late\":false,\"auto_score\":5,\"penalty_pct\":0}','2026-10-24 11:05:00'),(34,26,7,'submitted','{\"late\":false,\"auto_score\":4,\"penalty_pct\":0}','2026-10-23 12:09:00'),(35,27,8,'submitted','{\"late\":false,\"auto_score\":5,\"penalty_pct\":0}','2026-10-22 13:13:00'),(36,28,9,'submitted','{\"late\":false,\"auto_score\":5,\"penalty_pct\":0}','2026-10-21 11:17:00'),(37,29,10,'submitted','{\"late\":false,\"auto_score\":2,\"penalty_pct\":0}','2026-10-24 12:21:00'),(38,30,12,'submitted','{\"late\":false,\"auto_score\":7,\"penalty_pct\":0}','2026-10-22 11:29:00'),(39,31,13,'submitted','{\"late\":false,\"auto_score\":4,\"penalty_pct\":0}','2026-10-21 12:33:00'),(40,32,14,'submitted','{\"late\":false,\"auto_score\":7,\"penalty_pct\":0}','2026-10-24 13:37:00'),(41,33,15,'submitted','{\"late\":false,\"auto_score\":3,\"penalty_pct\":0}','2026-10-23 11:41:00'),(42,34,16,'submitted','{\"late\":false,\"auto_score\":4,\"penalty_pct\":0}','2026-10-22 12:45:00'),(43,35,17,'submitted','{\"late\":false,\"auto_score\":2,\"penalty_pct\":0}','2026-10-21 13:49:00'),(44,25,3,'released',NULL,'2026-10-26 12:24:04'),(45,26,3,'released',NULL,'2026-10-26 12:24:05'),(46,27,3,'released',NULL,'2026-10-26 12:24:05'),(47,28,3,'released',NULL,'2026-10-26 12:24:05'),(48,29,3,'released',NULL,'2026-10-26 12:24:05'),(49,30,3,'released',NULL,'2026-10-26 12:24:05'),(50,31,3,'released',NULL,'2026-10-26 12:24:05'),(51,32,3,'released',NULL,'2026-10-26 12:24:05'),(52,34,3,'released',NULL,'2026-10-26 12:24:05'),(53,36,6,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-20 11:12:00'),(54,36,4,'graded','{\"old_score\":null,\"new_score\":9,\"penalty_pct\":0}','2026-10-22 12:24:05'),(55,36,4,'released',NULL,'2026-10-22 12:24:05'),(56,37,7,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-19 12:15:00'),(57,37,4,'graded','{\"old_score\":null,\"new_score\":8,\"penalty_pct\":0}','2026-10-22 12:24:05'),(58,37,4,'released',NULL,'2026-10-22 12:24:05'),(59,38,8,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-20 13:18:00'),(60,38,4,'graded','{\"old_score\":null,\"new_score\":10,\"penalty_pct\":0}','2026-10-22 12:24:05'),(61,38,4,'released',NULL,'2026-10-22 12:24:05'),(62,39,9,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-19 14:21:00'),(63,39,4,'graded','{\"old_score\":null,\"new_score\":6,\"penalty_pct\":0}','2026-10-22 12:24:05'),(64,39,4,'released',NULL,'2026-10-22 12:24:05'),(65,40,10,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-20 11:24:00'),(66,40,4,'graded','{\"old_score\":null,\"new_score\":8,\"penalty_pct\":0}','2026-10-22 12:24:05'),(67,40,4,'released',NULL,'2026-10-22 12:24:05'),(68,41,11,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-19 12:27:00'),(69,41,4,'graded','{\"old_score\":null,\"new_score\":6,\"penalty_pct\":0}','2026-10-22 12:24:05'),(70,41,4,'released',NULL,'2026-10-22 12:24:05'),(71,42,12,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-20 13:30:00'),(72,42,4,'graded','{\"old_score\":null,\"new_score\":9,\"penalty_pct\":0}','2026-10-22 12:24:05'),(73,42,4,'released',NULL,'2026-10-22 12:24:05'),(74,43,14,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-20 11:36:00'),(75,43,4,'graded','{\"old_score\":null,\"new_score\":8,\"penalty_pct\":0}','2026-10-22 12:24:05'),(76,43,4,'released',NULL,'2026-10-22 12:24:05'),(77,44,15,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-19 12:39:00'),(78,44,4,'graded','{\"old_score\":null,\"new_score\":7,\"penalty_pct\":0}','2026-10-22 12:24:05'),(79,44,4,'released',NULL,'2026-10-22 12:24:05'),(80,45,16,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-20 13:42:00'),(81,45,4,'graded','{\"old_score\":null,\"new_score\":8,\"penalty_pct\":0}','2026-10-22 12:24:05'),(82,45,4,'released',NULL,'2026-10-22 12:24:05'),(83,46,17,'submitted','{\"late\":true,\"auto_score\":null,\"penalty_pct\":0}','2026-10-19 14:45:00'),(84,46,4,'graded','{\"old_score\":null,\"new_score\":7,\"penalty_pct\":0}','2026-10-22 12:24:05'),(85,46,4,'released',NULL,'2026-10-22 12:24:05'),(86,47,8,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-26 07:24:05'),(87,48,12,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-26 04:24:05'),(88,49,10,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-26 01:24:05'),(89,50,14,'submitted','{\"late\":false,\"auto_score\":null,\"penalty_pct\":0}','2026-10-25 22:24:05'),(90,51,2,'graded','{\"old_score\":null,\"new_score\":11,\"source\":\"paper\"}','2026-10-26 12:24:05'),(91,52,2,'graded','{\"old_score\":null,\"new_score\":11,\"source\":\"paper\"}','2026-10-26 12:24:05'),(92,53,2,'graded','{\"old_score\":null,\"new_score\":15,\"source\":\"paper\"}','2026-10-26 12:24:05'),(93,54,2,'graded','{\"old_score\":null,\"new_score\":17,\"source\":\"paper\"}','2026-10-26 12:24:05'),(94,55,2,'graded','{\"old_score\":null,\"new_score\":17,\"source\":\"paper\"}','2026-10-26 12:24:05'),(95,56,2,'graded','{\"old_score\":null,\"new_score\":13,\"source\":\"paper\"}','2026-10-26 12:24:05'),(96,57,2,'graded','{\"old_score\":null,\"new_score\":18,\"source\":\"paper\"}','2026-10-26 12:24:05'),(97,58,2,'graded','{\"old_score\":null,\"new_score\":8,\"source\":\"paper\"}','2026-10-26 12:24:05'),(98,59,2,'graded','{\"old_score\":null,\"new_score\":16,\"source\":\"paper\"}','2026-10-26 12:24:05'),(99,60,2,'graded','{\"old_score\":null,\"new_score\":15,\"source\":\"paper\"}','2026-10-26 12:24:05'),(100,53,2,'released',NULL,'2026-10-26 12:24:05'),(101,57,2,'released',NULL,'2026-10-26 12:24:05'),(102,55,2,'released',NULL,'2026-10-26 12:24:05'),(103,58,2,'released',NULL,'2026-10-26 12:24:05'),(104,59,2,'released',NULL,'2026-10-26 12:24:05'),(105,51,2,'released',NULL,'2026-10-26 12:24:05'),(106,54,2,'released',NULL,'2026-10-26 12:24:05'),(107,60,2,'released',NULL,'2026-10-26 12:24:05'),(108,52,2,'released',NULL,'2026-10-26 12:24:05');
/*!40000 ALTER TABLE `grading_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grading_periods`
--

DROP TABLE IF EXISTS `grading_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grading_periods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `academic_period_id` int(10) unsigned NOT NULL,
  `name` varchar(60) NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grading_periods_academic` (`academic_period_id`,`starts_on`),
  KEY `idx_grading_periods_org` (`organization_id`),
  CONSTRAINT `fk_grading_periods_academic` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grading_periods_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grading_periods`
--

LOCK TABLES `grading_periods` WRITE;
/*!40000 ALTER TABLE `grading_periods` DISABLE KEYS */;
INSERT INTO `grading_periods` VALUES (1,1,2,'Quarter 1',1,'2026-06-16','2026-08-28','2026-10-26 12:29:37','2026-10-26 12:29:37'),(2,1,2,'Quarter 2',2,'2026-08-29','2026-11-10','2026-10-26 12:29:37','2026-10-26 12:29:37'),(3,1,2,'Quarter 3',3,'2026-11-11','2027-01-23','2026-10-26 12:29:37','2026-10-26 12:29:37'),(4,1,2,'Quarter 4',4,'2027-01-24','2027-04-10','2026-10-26 12:29:37','2026-10-26 12:29:37');
/*!40000 ALTER TABLE `grading_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `guardian_consents`
--

DROP TABLE IF EXISTS `guardian_consents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guardian_consents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `topic` varchar(40) NOT NULL DEFAULT 'ai',
  `answer` enum('yes','no') NOT NULL,
  `answered_on` date NOT NULL,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_consents_student` (`student_id`,`topic`,`answered_on`),
  KEY `fk_consents_org` (`organization_id`),
  KEY `fk_consents_recorder` (`recorded_by`),
  CONSTRAINT `fk_consents_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_consents_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_consents_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `guardian_consents`
--

LOCK TABLES `guardian_consents` WRITE;
/*!40000 ALTER TABLE `guardian_consents` DISABLE KEYS */;
INSERT INTO `guardian_consents` VALUES (1,1,23,'ai','no','2026-07-23',3,'2026-07-23 12:24:04'),(2,1,20,'ai','yes','2026-07-22',3,'2026-07-22 12:24:04');
/*!40000 ALTER TABLE `guardian_consents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `learning_materials`
--

DROP TABLE IF EXISTS `learning_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `learning_materials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `module_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `topic` varchar(120) DEFAULT NULL,
  `kind` enum('file','link') NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `visibility` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `release_at` datetime DEFAULT NULL,
  `ai_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `current_version_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `copied_from_id` int(10) unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_materials_class` (`class_id`,`visibility`,`release_at`),
  KEY `idx_materials_module` (`module_id`),
  KEY `idx_materials_org` (`organization_id`,`created_at`),
  KEY `fk_materials_creator` (`created_by`),
  KEY `fk_materials_current_version` (`current_version_id`),
  KEY `idx_materials_copied_from` (`copied_from_id`),
  FULLTEXT KEY `ft_materials_text` (`title`,`description`,`topic`),
  CONSTRAINT `fk_materials_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_materials_copied_from` FOREIGN KEY (`copied_from_id`) REFERENCES `learning_materials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_materials_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_materials_current_version` FOREIGN KEY (`current_version_id`) REFERENCES `material_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_materials_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_materials_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_materials_link` CHECK (`kind` <> 'link' or `url` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `learning_materials`
--

LOCK TABLES `learning_materials` WRITE;
/*!40000 ALTER TABLE `learning_materials` DISABLE KEYS */;
INSERT INTO `learning_materials` VALUES (1,1,1,1,'Breathing and circulation notes','Read before the breathing-rate lab. Sections 3 and 7 are on the quiz.','Respiratory and circulatory systems','file',NULL,'published',NULL,1,1,2,NULL,'2026-10-14 12:24:04','2026-10-14 12:24:04','2026-10-14 12:24:04'),(2,1,1,2,'Heredity notes','Four pages: genes and chromosomes, Mendel, Punnett squares, and patterns beyond simple dominance.','Heredity','file',NULL,'published',NULL,1,2,2,NULL,'2026-10-24 12:24:04','2026-10-24 12:24:04','2026-10-24 12:24:04'),(3,1,1,2,'Module 2 review sheet',NULL,'Heredity','file',NULL,'published','2026-10-28 23:00:00',1,3,2,NULL,'2026-10-25 12:24:04','2026-10-25 12:24:04','2026-10-25 12:24:04'),(4,1,1,1,'Lab safety reminders',NULL,'Lab safety','file',NULL,'draft',NULL,0,4,2,NULL,NULL,'2026-10-25 12:24:04','2026-10-25 12:24:04'),(5,1,2,3,'Breathing and circulation notes',NULL,'Respiratory and circulatory systems','file',NULL,'published',NULL,1,5,2,NULL,'2026-10-14 12:24:04','2026-10-14 12:24:04','2026-10-14 12:24:04'),(6,1,3,5,'Quadratic equations: four methods','Worked examples for each method, plus the discriminant.','Quadratic equations','file',NULL,'published',NULL,1,6,3,NULL,'2026-10-18 12:24:04','2026-10-18 12:24:04','2026-10-18 12:24:04'),(7,1,4,6,'Quadratic equations: four methods',NULL,'Quadratic equations','file',NULL,'published',NULL,1,7,3,NULL,'2026-10-18 12:24:04','2026-10-18 12:24:04','2026-10-18 12:24:04'),(8,1,5,7,'Making inferences while reading',NULL,'Making inferences','file',NULL,'published',NULL,1,8,4,NULL,'2026-10-20 12:24:04','2026-10-20 12:24:04','2026-10-20 12:24:04'),(9,1,3,5,'Graph quadratic functions with Desmos','Type y = x^2 + 5x + 6 and find where the graph crosses the x-axis.','Quadratic equations','link','https://www.desmos.com/calculator','published',NULL,0,NULL,3,NULL,'2026-10-18 12:24:04','2026-10-18 12:24:04','2026-10-26 12:24:04');
/*!40000 ALTER TABLE `learning_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_competencies`
--

DROP TABLE IF EXISTS `lesson_competencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_competencies` (
  `lesson_id` int(10) unsigned NOT NULL,
  `competency_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`lesson_id`,`competency_id`),
  KEY `idx_lesson_competencies_competency` (`competency_id`),
  CONSTRAINT `fk_lesson_competencies_competency` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lesson_competencies_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_competencies`
--

LOCK TABLES `lesson_competencies` WRITE;
/*!40000 ALTER TABLE `lesson_competencies` DISABLE KEYS */;
INSERT INTO `lesson_competencies` VALUES (1,1),(2,5),(3,1);
/*!40000 ALTER TABLE `lesson_competencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lessons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `module_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `grade_level` varchar(40) DEFAULT NULL,
  `duration_minutes` smallint(5) unsigned DEFAULT NULL,
  `reading_level` varchar(40) DEFAULT NULL,
  `language` varchar(8) NOT NULL DEFAULT 'en',
  `lesson_format` varchar(40) DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `draft_content` longtext DEFAULT NULL,
  `published_content` longtext DEFAULT NULL,
  `source_material_ids` text DEFAULT NULL,
  `ai_assisted` tinyint(1) NOT NULL DEFAULT 0,
  `has_unpublished_changes` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `copied_from_id` int(10) unsigned DEFAULT NULL,
  `published_by` int(10) unsigned DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lessons_class` (`class_id`,`status`),
  KEY `fk_lessons_org` (`organization_id`),
  KEY `fk_lessons_module` (`module_id`),
  KEY `fk_lessons_creator` (`created_by`),
  KEY `fk_lessons_publisher` (`published_by`),
  KEY `idx_lessons_copied_from` (`copied_from_id`),
  CONSTRAINT `fk_lessons_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lessons_copied_from` FOREIGN KEY (`copied_from_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lessons_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lessons_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lessons_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lessons_publisher` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lessons`
--

LOCK TABLES `lessons` WRITE;
/*!40000 ALTER TABLE `lessons` DISABLE KEYS */;
INSERT INTO `lessons` VALUES (1,1,1,1,'Gas exchange in the alveoli','published','Grade 9',50,'Grade 9','en','Discussion and pair work','- Describe the structure of the alveoli.\n- Explain how oxygen and carbon dioxide move between the alveoli and the blood.','{\"objectives\":\"- Describe the structure of the alveoli.\\n- Explain how oxygen and carbon dioxide move between the alveoli and the blood.\",\"prior_knowledge\":\"You know that the body needs oxygen and that air enters through the nose and mouth.\",\"explanation\":\"Air reaches the alveoli through the bronchioles. Each alveolus has a wall one cell thick and is wrapped in capillaries. Oxygen moves from high concentration in the alveoli to lower concentration in the blood by **diffusion**; carbon dioxide moves the opposite way. [1]\",\"worked_example\":\"**Question:** why would gas exchange slow down if the alveolar walls became thicker?\\n\\n**Answer:** the gases would have farther to diffuse, so less oxygen would reach the blood each second.\",\"activities\":\"1. Pairs sketch an alveolus and its capillaries and label the direction each gas moves.\\n2. Groups compare a sponge and a solid block to discuss why surface area matters.\",\"checks\":\"- Which way does carbon dioxide move, and why?\\n- Name two features that make gas exchange fast.\",\"exit_ticket\":\"In one sentence, explain why the alveolar wall is only one cell thick.\",\"sources\":[{\"n\":1,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"Gas exchange in the alveoli\"}]}','{\"objectives\":\"- Describe the structure of the alveoli.\\n- Explain how oxygen and carbon dioxide move between the alveoli and the blood.\",\"prior_knowledge\":\"You know that the body needs oxygen and that air enters through the nose and mouth.\",\"explanation\":\"Air reaches the alveoli through the bronchioles. Each alveolus has a wall one cell thick and is wrapped in capillaries. Oxygen moves from high concentration in the alveoli to lower concentration in the blood by **diffusion**; carbon dioxide moves the opposite way. [1]\",\"worked_example\":\"**Question:** why would gas exchange slow down if the alveolar walls became thicker?\\n\\n**Answer:** the gases would have farther to diffuse, so less oxygen would reach the blood each second.\",\"activities\":\"1. Pairs sketch an alveolus and its capillaries and label the direction each gas moves.\\n2. Groups compare a sponge and a solid block to discuss why surface area matters.\",\"checks\":\"- Which way does carbon dioxide move, and why?\\n- Name two features that make gas exchange fast.\",\"exit_ticket\":\"In one sentence, explain why the alveolar wall is only one cell thick.\",\"sources\":[{\"n\":1,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"Gas exchange in the alveoli\"}]}','[1]',0,0,2,NULL,2,'2026-10-16 12:24:04','2026-10-15 12:24:04','2026-10-16 12:24:04'),(2,1,1,2,'Punnett squares in practice','draft','Grade 9',50,NULL,'en',NULL,'- Use a Punnett square to predict offspring genotypes.\n- Express results as ratios and probabilities.','{\"objectives\":\"- Use a Punnett square to predict offspring genotypes.\\n- Express results as ratios and probabilities.\",\"prior_knowledge\":\"Dominant and recessive alleles; genotype and phenotype.\",\"explanation\":\"\",\"worked_example\":\"\",\"activities\":\"\",\"checks\":\"\",\"exit_ticket\":\"\",\"sources\":[]}',NULL,'[2]',0,0,2,NULL,NULL,NULL,'2026-10-25 12:24:04','2026-10-25 12:24:04'),(3,1,1,1,'How the heart and lungs work together','draft','Grade 9',60,NULL,'en',NULL,'- You will be able to **trace the path of blood** through the heart and lungs.\n- You will be able to **explain how oxygen reaches the body’s cells**.','{\"objectives\":\"- You will be able to **trace the path of blood** through the heart and lungs.\\n- You will be able to **explain how oxygen reaches the body’s cells**.\",\"prior_knowledge\":\"- You already know that **cells get energy from food** and need oxygen, producing carbon dioxide as waste[1].\\n- You understand that **air enters the body through the nose or mouth** and travels down the airway passages[2].\\n- You are familiar with the idea that **the heart is a pump** that moves blood around the body[5].\",\"explanation\":\"- **Breathing** moves air into the lungs. When you inhale, the diaphragm flattens and the rib cage expands, lowering pressure inside the lungs so air flows in[4]. When you exhale, the diaphragm relaxes and pushes air out.\\n- Inside the lungs, air travels through the **trachea → bronchi → bronchioles → alveoli**[2]. The alveoli are tiny sacs surrounded by a dense network of capillaries.\\n- **Gas exchange** occurs across the thin walls of the alveoli and capillaries: oxygen diffuses into the blood, and carbon dioxide diffuses out to be exhaled[3]. The large surface area, thin walls, and moist lining make this exchange fast.\\n- The **heart** has four chambers: right atrium → right ventricle → left atrium → left ventricle[5]. Valves keep blood flowing one way.\\n- Blood travels in two circuits:\\n  1. **Pulmonary circuit** – right ventricle pumps oxygen‑poor blood to the lungs, where it picks up oxygen and returns to the left atrium[7].\\n  2. **Systemic circuit** – left ventricle pumps oxygen‑rich blood to the body’s tissues, delivering oxygen and picking up carbon dioxide, then returns to the right atrium[7].\\n- The **left ventricle** has a thicker muscular wall because it must push blood through the entire systemic circuit, which requires higher pressure[5].\\n- During exercise, both systems work harder: you breathe faster, and the heart beats faster and pumps more blood per beat, delivering extra oxygen to muscles[7].\",\"worked_example\":\"**Example: Follow one oxygen molecule from the air to a leg muscle cell**\\n1. **Inhale** – The molecule enters the nose, is warmed and moistened, and passes the pharynx and larynx.\\n2. **Travel down the airway** – It moves through the trachea, into a bronchus, then a bronchiole, and finally reaches an alveolus[2].\\n3. **Gas exchange** – Because the oxygen concentration is higher in the alveolus than in the surrounding capillary blood, the molecule diffuses across the thin alveolar wall into a red blood cell[3].\\n4. **Pulmonary circulation** – The oxygen‑rich blood is collected by pulmonary veins, returns to the left atrium, moves into the left ventricle, and is pumped out through the aorta into the systemic arteries[7].\\n5. **Arterial transport** – The molecule travels through progressively smaller arteries and arterioles until it reaches a capillary in the leg muscle.\\n6. **Capillary exchange** – The thin capillary wall allows the oxygen to diffuse from the blood into the muscle cell where it is used for cellular respiration[6].\\n7. **Result** – The muscle cell now has the oxygen it needs to produce energy, and the carbon dioxide produced will travel back to the lungs to be exhaled.\",\"activities\":\"- **Diagram labeling (15 min)**: Project a blank diagram of the heart and lungs. Students work in pairs to label the four chambers, major vessels, and airway pathway.\\n- **Guided discussion (10 min)**: Ask students to describe each step of the worked example in their own words; clarify misconceptions.\\n- **Group tracing activity (20 min)**: Provide each group with a set of cards showing steps (e.g., “right ventricle → pulmonary artery”). Students arrange them to show the complete path of blood and oxygen, then present.\\n- **Quick‑write (5 min)**: Individually write a short paragraph answering: *How does the structure of the alveoli and capillaries help gas exchange?*\\n- **Think‑pair‑share (5 min)**: Discuss why the left ventricle wall is thicker than the right ventricle.\",\"checks\":\"- **Exit‑question during discussion**: “What creates the pressure difference that pulls air into the lungs?” (Answer: diaphragm contraction and rib cage expansion)[4].\\n- **Mini‑quiz (3 questions)** at the end of the group activity:\\n  1. Name the two circulatory circuits.\\n  2. List the order of structures air passes through from nose to alveolus.\\n  3. Explain why oxygen moves from alveolus to blood.\\n- **Observation checklist** while students label diagrams (correct placement of atria, ventricles, pulmonary vs. systemic vessels).\",\"exit_ticket\":\"Write a 2‑sentence response:\\n1. Summarize how an oxygen molecule gets from the outside air to a body cell.\\n2. Why must the left ventricle work harder than the right ventricle?\",\"sources\":[{\"n\":1,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"Why your body needs both systems\"},{\"n\":2,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"The path air takes\"},{\"n\":3,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"Gas exchange in the alveoli\"},{\"n\":4,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"How breathing works\"},{\"n\":5,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"The heart and its four chambers\"},{\"n\":6,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"Blood vessels\"},{\"n\":7,\"material_id\":1,\"title\":\"Breathing and circulation notes\",\"where\":\"Two circuits working together\"}]}',NULL,'[1]',1,0,2,NULL,NULL,NULL,'2026-10-24 11:30:56','2026-10-24 12:30:56');
/*!40000 ALTER TABLE `lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `material_chunks`
--

DROP TABLE IF EXISTS `material_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `material_chunks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `material_id` int(10) unsigned NOT NULL,
  `material_version_id` int(10) unsigned NOT NULL,
  `chunk_index` int(10) unsigned NOT NULL,
  `page_from` int(10) unsigned DEFAULT NULL,
  `page_to` int(10) unsigned DEFAULT NULL,
  `section_title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `char_count` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chunks_version` (`material_version_id`,`chunk_index`),
  KEY `idx_chunks_class` (`class_id`,`material_id`),
  KEY `fk_chunks_org` (`organization_id`),
  KEY `fk_chunks_material` (`material_id`),
  FULLTEXT KEY `ft_chunks_text` (`section_title`,`content`),
  CONSTRAINT `fk_chunks_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chunks_material` FOREIGN KEY (`material_id`) REFERENCES `learning_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chunks_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chunks_version` FOREIGN KEY (`material_version_id`) REFERENCES `material_versions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `material_chunks`
--

LOCK TABLES `material_chunks` WRITE;
/*!40000 ALTER TABLE `material_chunks` DISABLE KEYS */;
INSERT INTO `material_chunks` VALUES (1,1,1,1,1,0,NULL,NULL,'Why your body needs both systems','Every cell in your body releases energy from food through cellular respiration. This process uses oxygen and produces carbon dioxide as a waste product. The respiratory system brings oxygen into the body and removes carbon dioxide. The circulatory system carries both gases between the lungs and the rest of the body. Neither system can do the job alone.',354,'2026-10-26 12:24:04'),(2,1,1,1,1,1,NULL,NULL,'The path air takes','When you breathe in, air enters through the nose or mouth. Hairs and mucus in the nasal cavity trap dust, and the air is warmed and moistened. The air passes the pharynx (throat) and the larynx (voice box), then travels down the trachea (windpipe). Rings of cartilage keep the trachea open.\n\nThe trachea divides into two bronchi, one for each lung. Inside the lungs the bronchi branch into smaller and smaller tubes called bronchioles, which end in clusters of tiny air sacs called alveoli.',490,'2026-10-26 12:24:04'),(3,1,1,1,1,2,NULL,NULL,'Gas exchange in the alveoli','The lungs contain hundreds of millions of alveoli. Their walls are only one cell thick and each one is wrapped in a net of capillaries. Oxygen diffuses from the air in the alveoli, where its concentration is high, into the blood, where its concentration is lower. Carbon dioxide diffuses the other way, from the blood into the alveoli, and is breathed out.\n\nThree features make this exchange fast: the very large total surface area of the alveoli, their thin walls, and their moist lining, which lets gases dissolve before they diffuse.',536,'2026-10-26 12:24:04'),(4,1,1,1,1,3,NULL,NULL,'How breathing works','Breathing is driven mainly by the diaphragm, a dome-shaped muscle under the lungs. When you inhale, the diaphragm contracts and flattens, and the muscles between the ribs pull the rib cage up and out. The chest cavity gets bigger, the air pressure inside the lungs drops below the pressure outside, and air flows in.\n\nWhen you exhale, the diaphragm relaxes and moves back up, the rib cage moves down and in, the chest cavity gets smaller, and air is pushed out.',461,'2026-10-26 12:24:04'),(5,1,1,1,1,4,NULL,NULL,'The heart and its four chambers','The heart is a muscular pump about the size of your fist. It has four chambers: two upper atria and two lower ventricles. The right side of the heart receives oxygen-poor blood from the body and pumps it to the lungs. The left side receives oxygen-rich blood from the lungs and pumps it to the rest of the body.\n\nValves between the chambers and at the exits of the ventricles keep blood flowing in one direction. The left ventricle has the thickest muscular wall because it must pump blood all the way around the body.',518,'2026-10-26 12:24:04'),(6,1,1,1,1,5,NULL,NULL,'Blood vessels','Arteries carry blood away from the heart. Their walls are thick and elastic so they can handle high pressure. Veins carry blood back to the heart. They have thinner walls and contain valves that stop blood from flowing backward. Capillaries are the smallest vessels. Their walls are one cell thick, which lets oxygen, nutrients, carbon dioxide and other wastes pass between the blood and the body\'s cells.',405,'2026-10-26 12:24:04'),(7,1,1,1,1,6,NULL,NULL,'Two circuits working together','Blood travels in two circuits. In pulmonary circulation, blood goes from the right ventricle to the lungs, picks up oxygen, and returns to the left atrium. In systemic circulation, blood goes from the left ventricle to the tissues of the body, delivers oxygen, and returns to the right atrium.\n\nWhen you exercise, your muscles need more oxygen and produce more carbon dioxide. You breathe faster and more deeply, and your heart beats faster and pumps more blood with each beat. This is how the two systems respond together to the body\'s needs.',543,'2026-10-26 12:24:04'),(8,1,1,1,1,7,NULL,NULL,'Keeping both systems healthy','Smoking damages the lining of the airways and the walls of the alveoli, and it makes the heart work harder. Regular physical activity strengthens the heart muscle and helps the lungs work efficiently. Avoiding second-hand smoke and wearing a well-fitted mask during heavy smoke or haze protect the airways. A balanced diet that limits excess salt and saturated fat helps keep blood vessels healthy.',398,'2026-10-26 12:24:04'),(9,1,1,1,1,8,NULL,NULL,'Check your understanding','1. Trace the path of an oxygen molecule from the air you breathe to a muscle cell in your leg.\n2. Explain why the walls of the alveoli and the capillaries are so thin.\n3. Why does the left ventricle have a thicker wall than the right ventricle?',244,'2026-10-26 12:24:04'),(10,1,1,2,2,0,1,1,NULL,'Heredity: inheritance and variation\nGenes, chromosomes and DNA\nTraits are characteristics that are passed from parents to their offspring, such as the shape of a seed or the colour of a flower. The instructions for traits are carried by genes.\nA gene is a segment of DNA, and genes are found on chromosomes inside the nucleus of a cell.\nHuman body cells contain 46 chromosomes arranged in 23 pairs. One chromosome of each pair comes from the mother and the other comes from the father. Because chromosomes come in pairs, most genes come in pairs too.\nDifferent versions of the same gene are called alleles. For example, a gene for stem length in pea plants has one allele for tall stems and another allele for short stems.',722,'2026-10-26 12:24:04'),(11,1,1,2,2,1,2,2,NULL,'Mendel\'s pea plant experiments\nGregor Mendel, a monk who worked in the 1800s, studied inheritance by crossing pea plants that showed contrasting traits, such as tall and short stems.\nWhen Mendel crossed pure-breeding tall plants with pure-breeding short plants, all of the offspring in the first generation, called F1, were tall. When he let the F1 plants self-pollinate, about three-fourths of the second generation, called F2, were tall and about one-fourth were short.\nFrom these results Mendel concluded that each plant carries two factors for a trait, which we now call alleles. One allele can hide the effect of the other: the allele that shows is dominant and the hidden one is recessive. The two alleles separate when sex cells (gametes) are formed, so each gamete carries only one allele. This idea is called the law of segregation.',841,'2026-10-26 12:24:04'),(12,1,1,2,2,2,3,3,NULL,'Using a Punnett square\nGeneticists write the dominant allele with a capital letter and the recessive allele with the same letter in lowercase. For stem length, T stands for tall and t stands for short.\nThe combination of alleles an organism has is its genotype. The trait that shows is its phenotype. An organism with two identical alleles, TT or tt, is homozygous. An organism with two different alleles, Tt, is heterozygous. A heterozygous pea plant is tall, because T is dominant.\nA Punnett square shows all the possible allele combinations from a cross. In a cross between two heterozygous plants (Tt x Tt), the offspring genotypes are TT, Tt, Tt and tt. The genotypic ratio is 1 TT : 2 Tt : 1 tt, and the phenotypic ratio is 3 tall : 1 short. The probability that any one offspring is short is 1 in 4, or 25 percent.',821,'2026-10-26 12:24:04'),(13,1,1,2,2,3,4,4,NULL,'Patterns beyond simple dominance\nNot every trait follows simple dominance. In incomplete dominance, the heterozygous offspring shows a phenotype in between the two parents. When a red-flowered four o\'clock plant is crossed with a white-flowered one, the offspring have pink flowers.\nIn codominance, both alleles are fully expressed. Roan cattle have both red hairs and white hairs. In human ABO blood types, the A and B alleles are codominant, so a person who inherits both has type AB blood.\nSome genes have more than two alleles in a population. The ABO blood type gene has three alleles: IA, IB and i. The i allele is recessive, so a person with type O blood has the genotype ii.\nSex-linked traits are controlled by genes on the X chromosome. Red-green colour blindness is more common in males because males have only one X chromosome, so a single recessive allele on that X chromosome is enough for the trait to appear.',923,'2026-10-26 12:24:04'),(14,1,1,3,3,0,NULL,NULL,'Vocabulary to know','- Gene, allele, chromosome\n- Dominant and recessive\n- Genotype and phenotype\n- Homozygous and heterozygous\n- Incomplete dominance and codominance',145,'2026-10-26 12:24:04'),(15,1,1,3,3,1,NULL,NULL,'Practice problems','1. In guinea pigs, black fur (B) is dominant over white fur (b). What fraction of the offspring of a Bb x bb cross is expected to have white fur?\n2. Two pink four o\'clock flowers are crossed. List the expected phenotypes of the offspring and their ratio.\n3. A mother has type A blood with genotype IAi and a father has type B blood with genotype IBi. Which blood types are possible for their children?',401,'2026-10-26 12:24:04'),(16,1,1,4,4,0,NULL,NULL,'Before the activity','- Read the whole procedure before you start.\n- Tie back long hair and keep bags under the table.',96,'2026-10-26 12:24:04'),(17,1,1,4,4,1,NULL,NULL,'During the activity','- Stop exercising and sit down if you feel dizzy or short of breath.\n- Tell your teacher right away about any spill or injury.',126,'2026-10-26 12:24:04'),(18,1,2,5,5,0,NULL,NULL,'Why your body needs both systems','Every cell in your body releases energy from food through cellular respiration. This process uses oxygen and produces carbon dioxide as a waste product. The respiratory system brings oxygen into the body and removes carbon dioxide. The circulatory system carries both gases between the lungs and the rest of the body. Neither system can do the job alone.',354,'2026-10-26 12:24:04'),(19,1,2,5,5,1,NULL,NULL,'The path air takes','When you breathe in, air enters through the nose or mouth. Hairs and mucus in the nasal cavity trap dust, and the air is warmed and moistened. The air passes the pharynx (throat) and the larynx (voice box), then travels down the trachea (windpipe). Rings of cartilage keep the trachea open.\n\nThe trachea divides into two bronchi, one for each lung. Inside the lungs the bronchi branch into smaller and smaller tubes called bronchioles, which end in clusters of tiny air sacs called alveoli.',490,'2026-10-26 12:24:04'),(20,1,2,5,5,2,NULL,NULL,'Gas exchange in the alveoli','The lungs contain hundreds of millions of alveoli. Their walls are only one cell thick and each one is wrapped in a net of capillaries. Oxygen diffuses from the air in the alveoli, where its concentration is high, into the blood, where its concentration is lower. Carbon dioxide diffuses the other way, from the blood into the alveoli, and is breathed out.\n\nThree features make this exchange fast: the very large total surface area of the alveoli, their thin walls, and their moist lining, which lets gases dissolve before they diffuse.',536,'2026-10-26 12:24:04'),(21,1,2,5,5,3,NULL,NULL,'How breathing works','Breathing is driven mainly by the diaphragm, a dome-shaped muscle under the lungs. When you inhale, the diaphragm contracts and flattens, and the muscles between the ribs pull the rib cage up and out. The chest cavity gets bigger, the air pressure inside the lungs drops below the pressure outside, and air flows in.\n\nWhen you exhale, the diaphragm relaxes and moves back up, the rib cage moves down and in, the chest cavity gets smaller, and air is pushed out.',461,'2026-10-26 12:24:04'),(22,1,2,5,5,4,NULL,NULL,'The heart and its four chambers','The heart is a muscular pump about the size of your fist. It has four chambers: two upper atria and two lower ventricles. The right side of the heart receives oxygen-poor blood from the body and pumps it to the lungs. The left side receives oxygen-rich blood from the lungs and pumps it to the rest of the body.\n\nValves between the chambers and at the exits of the ventricles keep blood flowing in one direction. The left ventricle has the thickest muscular wall because it must pump blood all the way around the body.',518,'2026-10-26 12:24:04'),(23,1,2,5,5,5,NULL,NULL,'Blood vessels','Arteries carry blood away from the heart. Their walls are thick and elastic so they can handle high pressure. Veins carry blood back to the heart. They have thinner walls and contain valves that stop blood from flowing backward. Capillaries are the smallest vessels. Their walls are one cell thick, which lets oxygen, nutrients, carbon dioxide and other wastes pass between the blood and the body\'s cells.',405,'2026-10-26 12:24:04'),(24,1,2,5,5,6,NULL,NULL,'Two circuits working together','Blood travels in two circuits. In pulmonary circulation, blood goes from the right ventricle to the lungs, picks up oxygen, and returns to the left atrium. In systemic circulation, blood goes from the left ventricle to the tissues of the body, delivers oxygen, and returns to the right atrium.\n\nWhen you exercise, your muscles need more oxygen and produce more carbon dioxide. You breathe faster and more deeply, and your heart beats faster and pumps more blood with each beat. This is how the two systems respond together to the body\'s needs.',543,'2026-10-26 12:24:04'),(25,1,2,5,5,7,NULL,NULL,'Keeping both systems healthy','Smoking damages the lining of the airways and the walls of the alveoli, and it makes the heart work harder. Regular physical activity strengthens the heart muscle and helps the lungs work efficiently. Avoiding second-hand smoke and wearing a well-fitted mask during heavy smoke or haze protect the airways. A balanced diet that limits excess salt and saturated fat helps keep blood vessels healthy.',398,'2026-10-26 12:24:04'),(26,1,2,5,5,8,NULL,NULL,'Check your understanding','1. Trace the path of an oxygen molecule from the air you breathe to a muscle cell in your leg.\n2. Explain why the walls of the alveoli and the capillaries are so thin.\n3. Why does the left ventricle have a thicker wall than the right ventricle?',244,'2026-10-26 12:24:04'),(27,1,3,6,6,0,NULL,NULL,'What makes an equation quadratic','A quadratic equation can be written in standard form $ax^2 + bx + c = 0$, where $a$, $b$ and $c$ are real numbers and $a \\neq 0$. The highest power of the variable is 2. A quadratic equation has at most two real solutions, also called roots.',241,'2026-10-26 12:24:04'),(28,1,3,6,6,1,NULL,NULL,'Method 1: Extracting square roots','Use this method when there is no $x$ term, that is, when $b = 0$. Isolate $x^2$, then take the square root of both sides. Remember that a positive number has two square roots.\n\nExample: $x^2 - 49 = 0$ gives $x^2 = 49$, so $x = 7$ or $x = -7$.',242,'2026-10-26 12:24:04'),(29,1,3,6,6,2,NULL,NULL,'Method 2: Factoring','Write the equation in standard form, factor the left side, then use the zero product property: if $pq = 0$, then $p = 0$ or $q = 0$.\n\nExample: $x^2 + 5x + 6 = 0$ factors as $(x + 2)(x + 3) = 0$, so $x = -2$ or $x = -3$.\n\nAlways check by substituting. For $x = -2$: $(-2)^2 + 5(-2) + 6 = 4 - 10 + 6 = 0$.',303,'2026-10-26 12:24:04'),(30,1,3,6,6,3,NULL,NULL,'Method 3: Completing the square','Rewrite the equation so that one side is a perfect square trinomial.\n\nExample: $x^2 + 6x - 7 = 0$. Move the constant term: $x^2 + 6x = 7$. Add the square of half the coefficient of $x$, which is $3^2 = 9$, to both sides: $x^2 + 6x + 9 = 16$, so $(x + 3)^2 = 16$. Then $x + 3 = 4$ or $x + 3 = -4$, which gives $x = 1$ or $x = -7$.',329,'2026-10-26 12:24:04'),(31,1,3,6,6,4,NULL,NULL,'Method 4: The quadratic formula','The quadratic formula works for every quadratic equation in standard form:\n\n$$x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}$$\n\nExample: $2x^2 - 3x - 2 = 0$ has $a = 2$, $b = -3$ and $c = -2$. Then $b^2 - 4ac = 9 + 16 = 25$, so $x = \\frac{3 \\pm 5}{4}$, which gives $x = 2$ or $x = -\\frac{1}{2}$.',287,'2026-10-26 12:24:04'),(32,1,3,6,6,5,NULL,NULL,'The discriminant','The expression $b^2 - 4ac$ is called the discriminant. It tells you the nature of the roots before you solve:\n\n- If $b^2 - 4ac > 0$, there are two distinct real roots.\n- If $b^2 - 4ac = 0$, there is exactly one real root, called a repeated root.\n- If $b^2 - 4ac < 0$, there are no real roots.',292,'2026-10-26 12:24:04'),(33,1,3,6,6,6,NULL,NULL,'Choosing a method','Extracting square roots is quickest when $b = 0$. Factoring is efficient when the trinomial factors easily over the integers. Completing the square shows where the quadratic formula comes from and helps you write a quadratic function in vertex form. The quadratic formula always works, so use it when the other methods are awkward or when the roots are not rational.',366,'2026-10-26 12:24:04'),(34,1,4,7,7,0,NULL,NULL,'What makes an equation quadratic','A quadratic equation can be written in standard form $ax^2 + bx + c = 0$, where $a$, $b$ and $c$ are real numbers and $a \\neq 0$. The highest power of the variable is 2. A quadratic equation has at most two real solutions, also called roots.',241,'2026-10-26 12:24:04'),(35,1,4,7,7,1,NULL,NULL,'Method 1: Extracting square roots','Use this method when there is no $x$ term, that is, when $b = 0$. Isolate $x^2$, then take the square root of both sides. Remember that a positive number has two square roots.\n\nExample: $x^2 - 49 = 0$ gives $x^2 = 49$, so $x = 7$ or $x = -7$.',242,'2026-10-26 12:24:04'),(36,1,4,7,7,2,NULL,NULL,'Method 2: Factoring','Write the equation in standard form, factor the left side, then use the zero product property: if $pq = 0$, then $p = 0$ or $q = 0$.\n\nExample: $x^2 + 5x + 6 = 0$ factors as $(x + 2)(x + 3) = 0$, so $x = -2$ or $x = -3$.\n\nAlways check by substituting. For $x = -2$: $(-2)^2 + 5(-2) + 6 = 4 - 10 + 6 = 0$.',303,'2026-10-26 12:24:04'),(37,1,4,7,7,3,NULL,NULL,'Method 3: Completing the square','Rewrite the equation so that one side is a perfect square trinomial.\n\nExample: $x^2 + 6x - 7 = 0$. Move the constant term: $x^2 + 6x = 7$. Add the square of half the coefficient of $x$, which is $3^2 = 9$, to both sides: $x^2 + 6x + 9 = 16$, so $(x + 3)^2 = 16$. Then $x + 3 = 4$ or $x + 3 = -4$, which gives $x = 1$ or $x = -7$.',329,'2026-10-26 12:24:04'),(38,1,4,7,7,4,NULL,NULL,'Method 4: The quadratic formula','The quadratic formula works for every quadratic equation in standard form:\n\n$$x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}$$\n\nExample: $2x^2 - 3x - 2 = 0$ has $a = 2$, $b = -3$ and $c = -2$. Then $b^2 - 4ac = 9 + 16 = 25$, so $x = \\frac{3 \\pm 5}{4}$, which gives $x = 2$ or $x = -\\frac{1}{2}$.',287,'2026-10-26 12:24:04'),(39,1,4,7,7,5,NULL,NULL,'The discriminant','The expression $b^2 - 4ac$ is called the discriminant. It tells you the nature of the roots before you solve:\n\n- If $b^2 - 4ac > 0$, there are two distinct real roots.\n- If $b^2 - 4ac = 0$, there is exactly one real root, called a repeated root.\n- If $b^2 - 4ac < 0$, there are no real roots.',292,'2026-10-26 12:24:04'),(40,1,4,7,7,6,NULL,NULL,'Choosing a method','Extracting square roots is quickest when $b = 0$. Factoring is efficient when the trinomial factors easily over the integers. Completing the square shows where the quadratic formula comes from and helps you write a quadratic function in vertex form. The quadratic formula always works, so use it when the other methods are awkward or when the roots are not rational.',366,'2026-10-26 12:24:04'),(41,1,5,8,8,0,NULL,NULL,'What an inference is','An inference is a logical conclusion you reach by combining clues in a text with what you already know. Writers do not state everything directly. They show actions, details and dialogue, and they expect readers to work out the rest.',232,'2026-10-26 12:24:04'),(42,1,5,8,8,1,NULL,NULL,'Clues plus knowledge','A useful formula is: text clues + what I know = inference. Text clues can be a character\'s actions, words or feelings, details of the setting, or the writer\'s choice of words. What you know comes from your own experience and from earlier reading.',246,'2026-10-26 12:24:04'),(43,1,5,8,8,2,NULL,NULL,'Steps for making an inference','1. Ask a question that the text does not answer directly, such as \"Why did the character leave early?\"\n2. Find two or three clues in the text that relate to the question.\n3. Connect the clues to what you already know.\n4. State your inference and point to the evidence that supports it.\n5. Check whether the rest of the text supports or challenges your inference.',362,'2026-10-26 12:24:04'),(44,1,5,8,8,3,NULL,NULL,'A short practice passage','Mara stood at the jeepney stop for the third time that week, clutching her umbrella even though the sky was clear. When the jeep finally came, she let two older women board first, then squeezed into the last seat near the door. She kept glancing at her wristwatch and tapping her foot.\n\nFrom the clues \"for the third time that week\", the repeated glances at her watch and the tapping foot, together with what we know about waiting for public transport, we can infer that Mara is worried about being late. Letting the older women board first suggests that she is considerate even when she is in a hurry.',602,'2026-10-26 12:24:04'),(45,1,5,8,8,4,NULL,NULL,'Common mistakes','- Making a guess that has no support in the text.\n- Ignoring clues that contradict your idea.\n- Confusing an inference with a summary of what the text says directly.',165,'2026-10-26 12:24:04'),(46,1,5,8,8,5,NULL,NULL,'Inference or prediction?','A prediction is a kind of inference about what will happen next. Like any inference, a prediction should be supported by clues from the text.',141,'2026-10-26 12:24:04');
/*!40000 ALTER TABLE `material_chunks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `material_gaps`
--

DROP TABLE IF EXISTS `material_gaps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `material_gaps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `keywords` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_material_gaps_class` (`class_id`,`created_at`),
  KEY `idx_material_gaps_student` (`student_id`),
  KEY `fk_material_gaps_org` (`organization_id`),
  CONSTRAINT `fk_material_gaps_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_material_gaps_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_material_gaps_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `material_gaps`
--

LOCK TABLES `material_gaps` WRITE;
/*!40000 ALTER TABLE `material_gaps` DISABLE KEYS */;
INSERT INTO `material_gaps` VALUES (1,1,1,6,'punnett probability','2026-10-24 10:24:06'),(2,1,1,7,'punnett probability','2026-10-23 09:24:06'),(3,1,1,8,'punnett probability','2026-10-21 08:24:06'),(4,1,1,9,'punnett probability','2026-10-20 07:24:06'),(5,1,1,10,'punnett probability','2026-10-17 06:24:06'),(6,1,1,11,'punnett probability','2026-10-14 05:24:06'),(7,1,1,6,'punnett probability dihybrid','2026-10-25 10:24:06'),(8,1,1,7,'punnett probability dihybrid','2026-10-22 09:24:06'),(9,1,1,9,'blood types inheritance','2026-10-25 10:24:06'),(10,1,1,10,'blood types inheritance','2026-10-24 09:24:06'),(11,1,1,11,'blood types inheritance','2026-10-19 08:24:06'),(12,1,1,12,'blood types inheritance','2026-10-18 07:24:06'),(13,1,1,13,'blood types inheritance','2026-10-11 06:24:06'),(14,1,1,14,'mitosis meiosis','2026-10-23 10:24:06'),(15,1,1,15,'mitosis meiosis','2026-10-16 09:24:06');
/*!40000 ALTER TABLE `material_gaps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `material_versions`
--

DROP TABLE IF EXISTS `material_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `material_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` int(10) unsigned NOT NULL,
  `version_no` int(10) unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `extension` varchar(10) NOT NULL,
  `size_bytes` int(10) unsigned NOT NULL,
  `sha256` char(64) NOT NULL,
  `page_count` int(10) unsigned DEFAULT NULL,
  `char_count` int(10) unsigned DEFAULT NULL,
  `processing_status` enum('uploaded','extracting','indexing','ready','failed','unsupported') NOT NULL DEFAULT 'uploaded',
  `processing_error` varchar(500) DEFAULT NULL,
  `processing_attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_material_versions_no` (`material_id`,`version_no`),
  KEY `idx_material_versions_status` (`processing_status`),
  KEY `fk_material_versions_uploader` (`uploaded_by`),
  CONSTRAINT `fk_material_versions_material` FOREIGN KEY (`material_id`) REFERENCES `learning_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_material_versions_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `material_versions`
--

LOCK TABLES `material_versions` WRITE;
/*!40000 ALTER TABLE `material_versions` DISABLE KEYS */;
INSERT INTO `material_versions` VALUES (1,1,1,'breathing-and-circulation.md','materials/2026/10/30717e1bb6a7316583d5dab6a9eeaf0d.md','text/plain; charset=utf-8','md',4292,'8855cc308c702308aed93cd2a496f76eab6304b6cca25a7bc3f04d2683a35c7d',NULL,3949,'ready',NULL,1,'2026-10-26 12:24:04',2,'2026-10-14 12:24:04'),(2,2,1,'heredity-notes.pdf','materials/2026/10/10db44c1eac3c8068e2b529fcc8cebdb.pdf','application/pdf','pdf',5473,'234fdf2317328b9ac33f829e480e4d6037d73487c4847290c7c49064e0cf9f3c',4,3307,'ready',NULL,1,'2026-10-26 12:24:04',2,'2026-10-24 12:24:04'),(3,3,1,'module-2-review.md','materials/2026/10/1673f945e19aa7089cf2f43c2c766be7.md','text/plain; charset=utf-8','md',629,'990d60f57109e0f0fc2bd396abe9989525f4428044088527d8b4a94321b84ff6',NULL,546,'ready',NULL,1,'2026-10-26 12:24:04',2,'2026-10-25 12:24:04'),(4,4,1,'lab-safety-reminders.md','materials/2026/10/0a9a52ef9f294d931cab69f881fee363.md','text/plain; charset=utf-8','md',305,'ac0c194bbe94881a7fa06cc3ba560e6980340a54b787873f0aa48a5c34c35d64',NULL,222,'ready',NULL,1,'2026-10-26 12:24:04',2,'2026-10-25 12:24:04'),(5,5,1,'breathing-and-circulation.md','materials/2026/10/b96d7fbb04899dcb475753fbee4f17db.md','text/plain; charset=utf-8','md',4292,'8855cc308c702308aed93cd2a496f76eab6304b6cca25a7bc3f04d2683a35c7d',NULL,3949,'ready',NULL,1,'2026-10-26 12:24:04',2,'2026-10-14 12:24:04'),(6,6,1,'quadratic-equations.md','materials/2026/10/35761a2014b43f3098adc79c6173240a.md','text/plain; charset=utf-8','md',2335,'a91ce146697543b14edfb8092de14cac4120362e639d471dc9f14a051bf045c1',NULL,2060,'ready',NULL,1,'2026-10-26 12:24:04',3,'2026-10-18 12:24:04'),(7,7,1,'quadratic-equations.md','materials/2026/10/88f8bc0c465a522933e550fade518333.md','text/plain; charset=utf-8','md',2335,'a91ce146697543b14edfb8092de14cac4120362e639d471dc9f14a051bf045c1',NULL,2060,'ready',NULL,1,'2026-10-26 12:24:04',3,'2026-10-18 12:24:04'),(8,8,1,'making-inferences.md','materials/2026/10/9a0237380ed2ed340088173a2ea4a6d3.md','text/plain; charset=utf-8','md',1956,'117e9b37ff6f722fdaee83dd3bdc83c47c90f1d362093694fdc256b4422fe999',NULL,1748,'ready',NULL,1,'2026-10-26 12:24:04',4,'2026-10-20 12:24:04');
/*!40000 ALTER TABLE `material_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modules`
--

DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `modules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int(10) unsigned NOT NULL,
  `title` varchar(160) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_modules_class` (`class_id`,`position`),
  CONSTRAINT `fk_modules_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modules`
--

LOCK TABLES `modules` WRITE;
/*!40000 ALTER TABLE `modules` DISABLE KEYS */;
INSERT INTO `modules` VALUES (1,1,'Module 1: Respiratory and circulatory systems',NULL,1,'2026-07-18 12:24:04','2026-07-18 12:24:04'),(2,1,'Module 2: Heredity and variation',NULL,2,'2026-07-18 12:24:04','2026-07-18 12:24:04'),(3,2,'Module 1: Respiratory and circulatory systems',NULL,1,'2026-07-18 12:24:04','2026-07-18 12:24:04'),(4,2,'Module 2: Heredity and variation',NULL,2,'2026-07-18 12:24:04','2026-07-18 12:24:04'),(5,3,'Module 1: Quadratic equations',NULL,1,'2026-07-18 12:24:04','2026-07-18 12:24:04'),(6,4,'Module 1: Quadratic equations',NULL,1,'2026-07-18 12:24:04','2026-07-18 12:24:04'),(7,5,'Module 1: Reading strategies',NULL,1,'2026-07-18 12:24:04','2026-07-18 12:24:04');
/*!40000 ALTER TABLE `modules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notice_acknowledgments`
--

DROP TABLE IF EXISTS `notice_acknowledgments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notice_acknowledgments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `notice` enum('privacy','rules') NOT NULL,
  `version` varchar(40) NOT NULL,
  `acknowledged_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notice_ack` (`user_id`,`notice`,`version`),
  KEY `idx_notice_ack_org` (`organization_id`,`notice`,`version`),
  CONSTRAINT `fk_notice_ack_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notice_ack_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notice_acknowledgments`
--

LOCK TABLES `notice_acknowledgments` WRITE;
/*!40000 ALTER TABLE `notice_acknowledgments` DISABLE KEYS */;
INSERT INTO `notice_acknowledgments` VALUES (1,1,6,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(2,1,6,'rules','2026.10-1','2026-07-18 12:24:04'),(3,1,7,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(4,1,7,'rules','2026.10-1','2026-07-18 12:24:04'),(5,1,8,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(6,1,8,'rules','2026.10-1','2026-07-18 12:24:04'),(7,1,9,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(8,1,9,'rules','2026.10-1','2026-07-18 12:24:04'),(9,1,10,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(10,1,10,'rules','2026.10-1','2026-07-18 12:24:04'),(11,1,11,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(12,1,11,'rules','2026.10-1','2026-07-18 12:24:04'),(13,1,12,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(14,1,12,'rules','2026.10-1','2026-07-18 12:24:04'),(15,1,13,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(16,1,13,'rules','2026.10-1','2026-07-18 12:24:04'),(17,1,14,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(18,1,14,'rules','2026.10-1','2026-07-18 12:24:04'),(19,1,15,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(20,1,15,'rules','2026.10-1','2026-07-18 12:24:04'),(21,1,16,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(22,1,16,'rules','2026.10-1','2026-07-18 12:24:04'),(23,1,17,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(24,1,17,'rules','2026.10-1','2026-07-18 12:24:04'),(25,1,18,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(26,1,18,'rules','2026.10-1','2026-07-18 12:24:04'),(27,1,19,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(28,1,19,'rules','2026.10-1','2026-07-18 12:24:04'),(29,1,20,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(30,1,20,'rules','2026.10-1','2026-07-18 12:24:04'),(31,1,21,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(32,1,21,'rules','2026.10-1','2026-07-18 12:24:04'),(33,1,22,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(34,1,22,'rules','2026.10-1','2026-07-18 12:24:04'),(35,1,23,'privacy','2026.10.3-1','2026-07-18 12:24:04'),(36,1,23,'rules','2026.10-1','2026-07-18 12:24:04');
/*!40000 ALTER TABLE `notice_acknowledgments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `type` varchar(40) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `dedupe_key` varchar(100) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notifications_dedupe` (`user_id`,`dedupe_key`),
  KEY `idx_notifications_user` (`user_id`,`read_at`,`created_at`),
  KEY `fk_notifications_org` (`organization_id`),
  CONSTRAINT `fk_notifications_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (89,1,6,'assignment','New quiz: Heredity basics','Science 9 – Narra. Due in 6 days.','/student/assignments/3',NULL,NULL,'2026-10-25 16:24:06'),(90,1,6,'feedback','Feedback released: Respiratory and circulatory systems check','Your teacher has returned your grade and comments.','/student/assignments/1',NULL,NULL,'2026-10-25 12:24:06'),(91,1,6,'material','New material: Heredity notes','Science 9 – Narra','/student/classes/1?tab=materials',NULL,NULL,'2026-10-24 12:24:06'),(92,1,6,'feedback','Feedback released: Solving quadratic equations by factoring','Your teacher has returned your grade and comments.','/student/assignments/6',NULL,'2026-10-25 17:24:06','2026-10-25 16:24:06'),(93,1,6,'feedback','Feedback released: Reading response: The jeepney stop','Your teacher has returned your grade and comments.','/student/assignments/9',NULL,'2026-10-22 13:24:06','2026-10-22 12:24:06'),(94,1,2,'submission','New work to review: Lab report: breathing rate before and after exercise','5 submissions so far.','/teacher/assignments/2/submissions',NULL,NULL,'2026-10-26 06:24:06'),(95,1,2,'submission','New work to review: Heredity basics','Written answers need your marking.','/teacher/assignments/3/submissions',NULL,NULL,'2026-10-26 09:24:06'),(96,1,1,'privacy','New privacy request','A student asked for a copy of their data.','/admin/privacy',NULL,NULL,'2026-10-25 12:24:06'),(97,1,1,'privacy','A companion answer was reported','Reason: not helpful','/admin/privacy#reports',NULL,NULL,'2026-10-24 12:32:18'),(98,1,6,'site_feedback','Reply to your feedback','Yes. Your teacher releases the answers after the due date, and they then appear on the same page as your receipt.','/feedback#feedback-1',NULL,'2026-10-16 12:24:06','2026-10-15 12:24:06'),(99,1,9,'site_feedback','Reply to your feedback','Not during the pilot, but we have noted it for later.','/feedback#feedback-3',NULL,'2026-10-19 12:24:06','2026-10-18 12:24:06'),(100,1,6,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(101,1,7,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(102,1,8,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(103,1,9,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(104,1,10,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(105,1,11,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(106,1,12,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(107,1,13,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(108,1,14,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(109,1,15,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(110,1,16,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00'),(111,1,17,'assignment','New quiz: Inheritance patterns check','Science 9 – Narra. Due Sat, Oct 31 at 11:59 PM','/student/assignments/14','assignment:14',NULL,'2026-10-25 16:30:00');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization_settings`
--

DROP TABLE IF EXISTS `organization_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organization_settings` (
  `organization_id` int(10) unsigned NOT NULL,
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`organization_id`,`setting_key`),
  CONSTRAINT `fk_orgsettings_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization_settings`
--

LOCK TABLES `organization_settings` WRITE;
/*!40000 ALTER TABLE `organization_settings` DISABLE KEYS */;
INSERT INTO `organization_settings` VALUES (1,'ai','{\"enabled\":true,\"student_modes\":[\"explain\",\"explain_back\",\"socratic\",\"practice\",\"flashcards\",\"summarize\",\"study_plan\",\"vocabulary\",\"ask_materials\"],\"teacher_tools\":true,\"student_daily_messages\":40,\"teacher_daily_requests\":80,\"monthly_token_budget\":2000000,\"general_answers\":true,\"max_question_chars\":2000,\"students_start_on\":true}',1,'2026-10-22 08:29:37'),(1,'grade_levels','[\"Grade 7\",\"Grade 8\",\"Grade 9\",\"Grade 10\"]',NULL,'2026-10-26 12:24:04'),(1,'grading','{\"model\":\"weighted\",\"categories\":[{\"key\":\"written\",\"label\":\"Written work\",\"weight\":30},{\"key\":\"performance\",\"label\":\"Performance tasks\",\"weight\":50},{\"key\":\"periodic\",\"label\":\"Quarterly assessment\",\"weight\":20}],\"groups\":[{\"key\":\"languages\",\"label\":\"Languages, Araling Panlipunan, EsP\",\"weights\":{\"written\":30,\"performance\":50,\"periodic\":20}},{\"key\":\"math_science\",\"label\":\"Mathematics and Science\",\"weights\":{\"written\":40,\"performance\":40,\"periodic\":20}},{\"key\":\"mapeh_tle\",\"label\":\"MAPEH and EPP\\/TLE\",\"weights\":{\"written\":20,\"performance\":60,\"periodic\":20}}],\"transmutation\":{\"enabled\":true,\"confirmed\":true,\"rows\":[{\"min\":100,\"grade\":100},{\"min\":98.4,\"grade\":99},{\"min\":96.8,\"grade\":98},{\"min\":95.2,\"grade\":97},{\"min\":93.6,\"grade\":96},{\"min\":92,\"grade\":95},{\"min\":90.4,\"grade\":94},{\"min\":88.8,\"grade\":93},{\"min\":87.2,\"grade\":92},{\"min\":85.6,\"grade\":91},{\"min\":84,\"grade\":90},{\"min\":82.4,\"grade\":89},{\"min\":80.8,\"grade\":88},{\"min\":79.2,\"grade\":87},{\"min\":77.6,\"grade\":86},{\"min\":76,\"grade\":85},{\"min\":74.4,\"grade\":84},{\"min\":72.8,\"grade\":83},{\"min\":71.2,\"grade\":82},{\"min\":69.6,\"grade\":81},{\"min\":68,\"grade\":80},{\"min\":66.4,\"grade\":79},{\"min\":64.8,\"grade\":78},{\"min\":63.2,\"grade\":77},{\"min\":61.6,\"grade\":76},{\"min\":60,\"grade\":75},{\"min\":56,\"grade\":74},{\"min\":52,\"grade\":73},{\"min\":48,\"grade\":72},{\"min\":44,\"grade\":71},{\"min\":40,\"grade\":70},{\"min\":36,\"grade\":69},{\"min\":32,\"grade\":68},{\"min\":28,\"grade\":67},{\"min\":24,\"grade\":66},{\"min\":20,\"grade\":65},{\"min\":16,\"grade\":64},{\"min\":12,\"grade\":63},{\"min\":8,\"grade\":62},{\"min\":4,\"grade\":61},{\"min\":0,\"grade\":60}],\"checked_by\":1,\"checked_at\":\"2026-10-21 03:45:44\"},\"passing_percent\":75,\"descriptors\":[{\"min\":90,\"label\":\"Outstanding\"},{\"min\":85,\"label\":\"Very satisfactory\"},{\"min\":80,\"label\":\"Satisfactory\"},{\"min\":75,\"label\":\"Fairly satisfactory\"},{\"min\":0,\"label\":\"Did not meet expectations\"}]}',1,'2026-10-22 08:29:37'),(1,'privacy','{\"contact_name\":\"Grace Lim, Data Protection Officer\",\"contact_email\":\"privacy@best-polomolok.edu.ph\"}',NULL,'2026-10-26 12:24:04');
/*!40000 ALTER TABLE `organization_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organizations`
--

DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organizations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `short_name` varchar(60) DEFAULT NULL,
  `slug` varchar(80) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Manila',
  `default_language` varchar(8) NOT NULL DEFAULT 'en',
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_organizations_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organizations`
--

LOCK TABLES `organizations` WRITE;
/*!40000 ALTER TABLE `organizations` DISABLE KEYS */;
INSERT INTO `organizations` VALUES (1,'B.E.S.T. College of Polomolok','B.E.S.T.','best-polomolok',NULL,'Asia/Manila','en',1,'2026-06-28 12:24:04','2026-06-28 12:24:04');
/*!40000 ALTER TABLE `organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `practice_sets`
--

DROP TABLE IF EXISTS `practice_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `practice_sets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned DEFAULT NULL,
  `material_id` int(10) unsigned DEFAULT NULL,
  `kind` enum('quiz','flashcards') NOT NULL,
  `title` varchar(200) NOT NULL,
  `topic` varchar(160) DEFAULT NULL,
  `items` longtext NOT NULL,
  `generator` enum('ai','demo') NOT NULL,
  `results` longtext DEFAULT NULL,
  `score` int(10) unsigned DEFAULT NULL,
  `total` int(10) unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `last_reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_practice_sets_user` (`user_id`,`created_at`),
  KEY `fk_practice_sets_org` (`organization_id`),
  KEY `fk_practice_sets_class` (`class_id`),
  KEY `fk_practice_sets_material` (`material_id`),
  CONSTRAINT `fk_practice_sets_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_practice_sets_material` FOREIGN KEY (`material_id`) REFERENCES `learning_materials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_practice_sets_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_practice_sets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `practice_sets`
--

LOCK TABLES `practice_sets` WRITE;
/*!40000 ALTER TABLE `practice_sets` DISABLE KEYS */;
INSERT INTO `practice_sets` VALUES (1,1,6,1,2,'quiz','Practice: Heredity notes',NULL,'[{\"type\":\"mcq\",\"question\":\"How many chromosomes are found in a typical human body cell?\",\"choices\":[\"23\",\"46\",\"92\",\"24\"],\"answer\":1,\"explanation\":\"Human body cells contain 46 chromosomes arranged in 23 pairs.\",\"source\":1,\"source_ref\":{\"title\":\"Heredity notes\",\"where\":\"p. 1\",\"material_id\":2}},{\"type\":\"mcq\",\"question\":\"In Mendel\'s pea plant experiments, what fraction of the F2 generation displayed the short stem phenotype?\",\"choices\":[\"1\\/2\",\"1\\/4\",\"3\\/4\",\"1\\/8\"],\"answer\":1,\"explanation\":\"About one‑fourth of the second generation (F2) were short stems.\",\"source\":2,\"source_ref\":{\"title\":\"Heredity notes\",\"where\":\"p. 2\",\"material_id\":2}},{\"type\":\"mcq\",\"question\":\"What is the genotypic ratio of offspring from a cross between two heterozygous tall pea plants (Tt × Tt)?\",\"choices\":[\"1 TT : 1 Tt : 2 tt\",\"1 TT : 2 Tt : 1 tt\",\"2 TT : 1 Tt : 1 tt\",\"1 TT : 1 Tt : 1 tt\"],\"answer\":1,\"explanation\":\"A Punnett square for Tt × Tt gives the ratio 1 TT : 2 Tt : 1 tt.\",\"source\":3,\"source_ref\":{\"title\":\"Heredity notes\",\"where\":\"p. 3\",\"material_id\":2}},{\"type\":\"mcq\",\"question\":\"When a red‑flowered four o\'clock plant is crossed with a white‑flowered one (incomplete dominance), what colour are the offspring’s flowers?\",\"choices\":[\"red\",\"white\",\"pink\",\"purple\"],\"answer\":2,\"explanation\":\"Incomplete dominance produces an intermediate phenotype, so the flowers are pink.\",\"source\":4,\"source_ref\":{\"title\":\"Heredity notes\",\"where\":\"p. 4\",\"material_id\":2}},{\"type\":\"mcq\",\"question\":\"Which human blood type results from inheriting both the A and B alleles?\",\"choices\":[\"Type A\",\"Type B\",\"Type AB\",\"Type O\"],\"answer\":2,\"explanation\":\"A and B alleles are codominant, so a person with both has type AB blood.\",\"source\":4,\"source_ref\":{\"title\":\"Heredity notes\",\"where\":\"p. 4\",\"material_id\":2}}]','ai','[{\"given\":\"1\",\"correct\":true},{\"given\":\"1\",\"correct\":true},{\"given\":\"2\",\"correct\":false},{\"given\":\"2\",\"correct\":true},{\"given\":\"3\",\"correct\":false}]',3,5,'2026-10-24 14:44:28','2026-10-24 14:44:28','2026-10-24 14:32:28','2026-10-24 14:44:28'),(2,1,6,1,1,'flashcards','Flashcards: Breathing and circulation notes',NULL,'[{\"front\":\"What two systems work together to deliver oxygen to body cells?\",\"back\":\"The respiratory system brings in oxygen and removes carbon dioxide, while the circulatory system transports gases between the lungs and the rest of the body.\",\"source\":1,\"source_ref\":{\"title\":\"Breathing and circulation notes\",\"where\":\"Why your body needs both systems\",\"material_id\":1}},{\"front\":\"List the order of structures air passes through from the nose to the alveoli.\",\"back\":\"Nose\\/mouth → nasal cavity (hairs and mucus) → pharynx → larynx → trachea → bronchi → bronchioles → alveoli.\",\"source\":2,\"source_ref\":{\"title\":\"Breathing and circulation notes\",\"where\":\"The path air takes\",\"material_id\":1}},{\"front\":\"Why is gas exchange in the alveoli so fast?\",\"back\":\"Because alveoli have a huge total surface area, very thin walls, and a moist lining that lets gases dissolve before diffusing.\",\"source\":3,\"source_ref\":{\"title\":\"Breathing and circulation notes\",\"where\":\"Gas exchange in the alveoli\",\"material_id\":1}},{\"front\":\"Which heart chamber has the thickest muscular wall and why?\",\"back\":\"The left ventricle; it must pump oxygen‑rich blood all the way around the body, requiring more force.\",\"source\":5,\"source_ref\":{\"title\":\"Breathing and circulation notes\",\"where\":\"The heart and its four chambers\",\"material_id\":1}},{\"front\":\"What are the two circulatory circuits and their main functions?\",\"back\":\"Pulmonary circulation: blood moves from the right ventricle to the lungs to pick up oxygen and returns to the left atrium. Systemic circulation: blood moves from the left ventricle to body tissues to deliver oxygen and returns to the right atrium.\",\"source\":7,\"source_ref\":{\"title\":\"Breathing and circulation notes\",\"where\":\"Two circuits working together\",\"material_id\":1}}]','ai','[{\"known\":true},{\"known\":false},{\"known\":true},{\"known\":true},{\"known\":false}]',3,5,'2026-10-25 14:44:42','2026-10-25 14:44:42','2026-10-25 14:32:42','2026-10-25 14:44:42');
/*!40000 ALTER TABLE `practice_sets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `privacy_requests`
--

DROP TABLE IF EXISTS `privacy_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `privacy_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `request_type` enum('export','correction','deletion') NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('submitted','in_review','completed','rejected') NOT NULL DEFAULT 'submitted',
  `reviewer_id` int(10) unsigned DEFAULT NULL,
  `resolution_note` text DEFAULT NULL,
  `export_path` varchar(255) DEFAULT NULL,
  `export_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_privacy_org_status` (`organization_id`,`status`,`created_at`),
  KEY `idx_privacy_user` (`user_id`),
  KEY `fk_privacy_reviewer` (`reviewer_id`),
  CONSTRAINT `fk_privacy_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_privacy_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_privacy_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `privacy_requests`
--

LOCK TABLES `privacy_requests` WRITE;
/*!40000 ALTER TABLE `privacy_requests` DISABLE KEYS */;
INSERT INTO `privacy_requests` VALUES (1,1,12,'export','I would like a copy of my information for my records.','submitted',NULL,NULL,NULL,NULL,'2026-10-25 12:24:05','2026-10-25 12:24:05',NULL);
/*!40000 ALTER TABLE `privacy_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `qtype` enum('multiple_choice','true_false','short_answer','essay') NOT NULL,
  `prompt` text NOT NULL,
  `points` decimal(6,2) NOT NULL DEFAULT 1.00,
  `options` longtext DEFAULT NULL,
  `answer_key` longtext DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `topic` varchar(120) DEFAULT NULL,
  `competency_id` int(10) unsigned DEFAULT NULL,
  `source_ref` varchar(255) DEFAULT NULL,
  `ai_generated` tinyint(1) NOT NULL DEFAULT 0,
  `reviewed` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_questions_assignment` (`assignment_id`,`position`),
  KEY `idx_questions_competency` (`competency_id`),
  CONSTRAINT `fk_questions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_questions_competency` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_questions_points` CHECK (`points` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (1,1,1,'multiple_choice','Where does gas exchange between the air and the blood take place?',1.00,'[{\"key\":\"A\",\"text\":\"Trachea\"},{\"key\":\"B\",\"text\":\"Bronchi\"},{\"key\":\"C\",\"text\":\"Alveoli\"},{\"key\":\"D\",\"text\":\"Larynx\"}]','{\"correct\":\"C\"}','Gases diffuse across the thin walls of the alveoli into and out of the surrounding capillaries.','Gas exchange',1,'Breathing and circulation notes: Gas exchange in the alveoli',0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(2,1,2,'multiple_choice','Which chamber of the heart pumps oxygen-rich blood to the rest of the body?',1.00,'[{\"key\":\"A\",\"text\":\"Right atrium\"},{\"key\":\"B\",\"text\":\"Right ventricle\"},{\"key\":\"C\",\"text\":\"Left atrium\"},{\"key\":\"D\",\"text\":\"Left ventricle\"}]','{\"correct\":\"D\"}','The left ventricle has the thickest wall because it pumps blood around the whole body.','The heart',2,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(3,1,3,'true_false','Veins carry blood away from the heart.',1.00,NULL,'{\"correct\":false}','Arteries carry blood away from the heart; veins carry it back.','Blood vessels',2,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(4,1,4,'multiple_choice','Why do oxygen and carbon dioxide move across the walls of the alveoli so easily?',2.00,'[{\"key\":\"A\",\"text\":\"The walls are thick and muscular\"},{\"key\":\"B\",\"text\":\"The walls are one cell thick and moist\"},{\"key\":\"C\",\"text\":\"The alveoli contain valves\"},{\"key\":\"D\",\"text\":\"The air in the alveoli is under high pressure\"}]','{\"correct\":\"B\"}','Thin, moist walls and a large surface area let the gases diffuse quickly.','Gas exchange',1,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(5,1,5,'true_false','When you inhale, the diaphragm contracts and moves down.',1.00,NULL,'{\"correct\":true}','The contracting diaphragm flattens, making the chest cavity larger so air flows in.','Breathing',3,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(6,1,6,'short_answer','Name the tiny blood vessels where substances pass between the blood and the body\'s cells.',1.00,NULL,'{\"accepted\":[\"capillaries\",\"capillary\"],\"case_sensitive\":false}','Capillaries have walls one cell thick.','Blood vessels',2,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(7,3,1,'multiple_choice','Two heterozygous tall pea plants (Tt) are crossed. What is the probability that an offspring is short?',1.00,'[{\"key\":\"A\",\"text\":\"0\"},{\"key\":\"B\",\"text\":\"1 in 4\"},{\"key\":\"C\",\"text\":\"1 in 2\"},{\"key\":\"D\",\"text\":\"3 in 4\"}]','{\"correct\":\"B\"}','The cross gives TT, Tt, Tt and tt; only tt is short, so 1 in 4.','Punnett squares',5,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(8,3,2,'true_false','A person with type AB blood shows both the A and the B allele because they are codominant.',1.00,NULL,'{\"correct\":true}','In codominance both alleles are fully expressed.','Non-Mendelian inheritance',4,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(9,3,3,'multiple_choice','What do we call the trait that an organism shows?',1.00,'[{\"key\":\"A\",\"text\":\"Genotype\"},{\"key\":\"B\",\"text\":\"Phenotype\"},{\"key\":\"C\",\"text\":\"Allele\"},{\"key\":\"D\",\"text\":\"Gamete\"}]','{\"correct\":\"B\"}','The phenotype is the observable trait; the genotype is the allele combination.','Vocabulary',4,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(10,3,4,'short_answer','What is the term for different versions of the same gene?',1.00,NULL,'{\"accepted\":[\"alleles\",\"allele\"],\"case_sensitive\":false}','Different versions of a gene are alleles.','Vocabulary',4,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(11,3,5,'essay','In three or four sentences, explain how two tall parent plants can have a short offspring.',4.00,NULL,NULL,NULL,'Punnett squares',5,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(12,5,1,'multiple_choice','Where does gas exchange between the air and the blood take place?',1.00,'[{\"key\":\"A\",\"text\":\"Trachea\"},{\"key\":\"B\",\"text\":\"Bronchi\"},{\"key\":\"C\",\"text\":\"Alveoli\"},{\"key\":\"D\",\"text\":\"Larynx\"}]','{\"correct\":\"C\"}','Gases diffuse across the thin walls of the alveoli into and out of the surrounding capillaries.','Gas exchange',1,'Breathing and circulation notes: Gas exchange in the alveoli',0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(13,5,2,'multiple_choice','Which chamber of the heart pumps oxygen-rich blood to the rest of the body?',1.00,'[{\"key\":\"A\",\"text\":\"Right atrium\"},{\"key\":\"B\",\"text\":\"Right ventricle\"},{\"key\":\"C\",\"text\":\"Left atrium\"},{\"key\":\"D\",\"text\":\"Left ventricle\"}]','{\"correct\":\"D\"}','The left ventricle has the thickest wall because it pumps blood around the whole body.','The heart',2,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(14,5,3,'true_false','Veins carry blood away from the heart.',1.00,NULL,'{\"correct\":false}','Arteries carry blood away from the heart; veins carry it back.','Blood vessels',2,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(15,5,4,'multiple_choice','Why do oxygen and carbon dioxide move across the walls of the alveoli so easily?',2.00,'[{\"key\":\"A\",\"text\":\"The walls are thick and muscular\"},{\"key\":\"B\",\"text\":\"The walls are one cell thick and moist\"},{\"key\":\"C\",\"text\":\"The alveoli contain valves\"},{\"key\":\"D\",\"text\":\"The air in the alveoli is under high pressure\"}]','{\"correct\":\"B\"}','Thin, moist walls and a large surface area let the gases diffuse quickly.','Gas exchange',1,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(16,5,5,'true_false','When you inhale, the diaphragm contracts and moves down.',1.00,NULL,'{\"correct\":true}','The contracting diaphragm flattens, making the chest cavity larger so air flows in.','Breathing',3,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(17,5,6,'short_answer','Name the tiny blood vessels where substances pass between the blood and the body\'s cells.',1.00,NULL,'{\"accepted\":[\"capillaries\",\"capillary\"],\"case_sensitive\":false}','Capillaries have walls one cell thick.','Blood vessels',2,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(18,6,1,'multiple_choice','Which equation is quadratic?',1.00,'[{\"key\":\"A\",\"text\":\"3x + 2 = 0\"},{\"key\":\"B\",\"text\":\"x^2 - 4x + 3 = 0\"},{\"key\":\"C\",\"text\":\"x^3 = 8\"},{\"key\":\"D\",\"text\":\"2\\/x = 5\"}]','{\"correct\":\"B\"}','The highest power of x is 2.','Standard form',NULL,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(19,6,2,'multiple_choice','Solve by factoring: x^2 + 5x + 6 = 0',2.00,'[{\"key\":\"A\",\"text\":\"x = 2 or x = 3\"},{\"key\":\"B\",\"text\":\"x = -2 or x = -3\"},{\"key\":\"C\",\"text\":\"x = -1 or x = -6\"},{\"key\":\"D\",\"text\":\"x = 1 or x = 6\"}]','{\"correct\":\"B\"}','(x + 2)(x + 3) = 0, so x = -2 or x = -3.','Factoring',NULL,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(20,6,3,'multiple_choice','Solve by factoring: x^2 - 7x + 10 = 0',2.00,'[{\"key\":\"A\",\"text\":\"x = 2 or x = 5\"},{\"key\":\"B\",\"text\":\"x = -2 or x = -5\"},{\"key\":\"C\",\"text\":\"x = 1 or x = 10\"},{\"key\":\"D\",\"text\":\"x = -1 or x = -10\"}]','{\"correct\":\"A\"}','(x - 2)(x - 5) = 0.','Factoring',NULL,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(21,6,4,'true_false','If (x - 4)(x + 1) = 0, then x = 4 or x = -1.',1.00,NULL,'{\"correct\":true}','Set each factor equal to zero.','Zero product property',NULL,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(22,6,5,'short_answer','What is the positive solution of x^2 - 49 = 0?',1.00,NULL,'{\"accepted\":[\"7\",\"x = 7\",\"x=7\"],\"case_sensitive\":false}','x^2 = 49, so x = 7 or x = -7.','Extracting square roots',NULL,NULL,0,1,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(23,8,1,'multiple_choice','Which equation is quadratic?',1.00,'[{\"key\":\"A\",\"text\":\"3x + 2 = 0\"},{\"key\":\"B\",\"text\":\"x^2 - 4x + 3 = 0\"},{\"key\":\"C\",\"text\":\"x^3 = 8\"},{\"key\":\"D\",\"text\":\"2\\/x = 5\"}]','{\"correct\":\"B\"}','The highest power of x is 2.','Standard form',NULL,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(24,8,2,'multiple_choice','Solve by factoring: x^2 + 5x + 6 = 0',2.00,'[{\"key\":\"A\",\"text\":\"x = 2 or x = 3\"},{\"key\":\"B\",\"text\":\"x = -2 or x = -3\"},{\"key\":\"C\",\"text\":\"x = -1 or x = -6\"},{\"key\":\"D\",\"text\":\"x = 1 or x = 6\"}]','{\"correct\":\"B\"}','(x + 2)(x + 3) = 0, so x = -2 or x = -3.','Factoring',NULL,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(25,8,3,'multiple_choice','Solve by factoring: x^2 - 7x + 10 = 0',2.00,'[{\"key\":\"A\",\"text\":\"x = 2 or x = 5\"},{\"key\":\"B\",\"text\":\"x = -2 or x = -5\"},{\"key\":\"C\",\"text\":\"x = 1 or x = 10\"},{\"key\":\"D\",\"text\":\"x = -1 or x = -10\"}]','{\"correct\":\"A\"}','(x - 2)(x - 5) = 0.','Factoring',NULL,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(26,8,4,'true_false','If (x - 4)(x + 1) = 0, then x = 4 or x = -1.',1.00,NULL,'{\"correct\":true}','Set each factor equal to zero.','Zero product property',NULL,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(27,8,5,'short_answer','What is the positive solution of x^2 - 49 = 0?',1.00,NULL,'{\"accepted\":[\"7\",\"x = 7\",\"x=7\"],\"case_sensitive\":false}','x^2 = 49, so x = 7 or x = -7.','Extracting square roots',NULL,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(28,11,1,'multiple_choice','Which structure carries air from the larynx to the bronchi?',2.00,'[{\"key\":\"A\",\"text\":\"Trachea\"},{\"key\":\"B\",\"text\":\"Alveoli\"},{\"key\":\"C\",\"text\":\"Diaphragm\"},{\"key\":\"D\",\"text\":\"Capillaries\"}]','{\"correct\":\"A\"}',NULL,'Breathing',3,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(29,11,2,'multiple_choice','Which blood vessels carry blood back to the heart?',2.00,'[{\"key\":\"A\",\"text\":\"Arteries\"},{\"key\":\"B\",\"text\":\"Veins\"},{\"key\":\"C\",\"text\":\"Capillaries\"},{\"key\":\"D\",\"text\":\"Alveoli\"}]','{\"correct\":\"B\"}',NULL,'Blood vessels',2,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(30,11,3,'multiple_choice','What does the right ventricle pump blood to?',2.00,'[{\"key\":\"A\",\"text\":\"The brain\"},{\"key\":\"B\",\"text\":\"The lungs\"},{\"key\":\"C\",\"text\":\"The liver\"},{\"key\":\"D\",\"text\":\"The arms and legs\"}]','{\"correct\":\"B\"}',NULL,'The heart',2,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(31,11,4,'true_false','The diaphragm relaxes and moves up when you exhale.',2.00,NULL,'{\"correct\":true}',NULL,'Breathing',3,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(32,11,5,'true_false','Capillary walls are many cells thick.',2.00,NULL,'{\"correct\":false}',NULL,'Blood vessels',2,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(33,11,6,'short_answer','Name the gas that the blood picks up in the alveoli.',2.00,NULL,'{\"accepted\":[\"oxygen\"],\"case_sensitive\":false}',NULL,'Gas exchange',1,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(34,11,7,'essay','Explain how the respiratory and circulatory systems work together to deliver oxygen to a muscle cell.',8.00,NULL,NULL,NULL,'Gas exchange',1,NULL,0,1,'2026-10-26 12:24:05','2026-10-26 12:24:05'),(35,13,1,'multiple_choice','According to the passage, which type of blood vessel has walls that are only one cell thick, allowing exchange of gases and nutrients?',1.00,'[{\"key\":\"A\",\"text\":\"Arteries\"},{\"key\":\"B\",\"text\":\"Veins\"},{\"key\":\"C\",\"text\":\"Capillaries\"},{\"key\":\"D\",\"text\":\"All of the above\"}]','{\"correct\":\"C\"}','The passage states that capillaries are the smallest vessels and their walls are one cell thick, which lets oxygen, nutrients, carbon dioxide and other wastes pass between the blood and the body\'s cells.','Blood vessels',NULL,'Breathing and circulation notes, Blood vessels',1,0,'2026-10-25 11:00:36','2026-10-25 11:00:36'),(36,13,2,'multiple_choice','Which chamber of the heart has the thickest muscular wall because it must pump blood throughout the entire body?',1.00,'[{\"key\":\"A\",\"text\":\"Right atrium\"},{\"key\":\"B\",\"text\":\"Left atrium\"},{\"key\":\"C\",\"text\":\"Right ventricle\"},{\"key\":\"D\",\"text\":\"Left ventricle\"}]','{\"correct\":\"D\"}','The passage explains that the left ventricle has the thickest muscular wall because it must pump blood all the way around the body.','Heart chambers',NULL,'Breathing and circulation notes, The heart and its four chambers',1,0,'2026-10-25 11:00:36','2026-10-25 11:00:36'),(37,13,3,'multiple_choice','When you exercise, which of the following changes occurs in both the respiratory and circulatory systems?',1.00,'[{\"key\":\"A\",\"text\":\"Breathing becomes slower and shallower\"},{\"key\":\"B\",\"text\":\"Heart rate decreases\"},{\"key\":\"C\",\"text\":\"Breathing becomes faster and deeper\"},{\"key\":\"D\",\"text\":\"Blood flow to the muscles is reduced\"}]','{\"correct\":\"C\"}','The passage notes that during exercise muscles need more oxygen, so you breathe faster and more deeply, and the heart beats faster and pumps more blood.','Exercise response',NULL,'Breathing and circulation notes, Two circuits working together',1,0,'2026-10-25 11:00:36','2026-10-25 11:00:36'),(38,13,4,'true_false','Veins have thicker walls than arteries.',1.00,NULL,'{\"correct\":false}','The passage states that arteries have thick, elastic walls to handle high pressure, while veins have thinner walls and contain valves.','Blood vessel structure',NULL,'Breathing and circulation notes, Blood vessels',1,0,'2026-10-25 11:00:36','2026-10-25 11:00:36'),(39,13,5,'short_answer','Name the two circulatory circuits and identify the heart chamber where each circuit begins.',1.00,NULL,'{\"accepted\":[\"Pulmonary circulation begins in the right ventricle; systemic circulation begins in the left ventricle\",\"Right ventricle starts pulmonary circuit; left ventricle starts systemic circuit\"],\"case_sensitive\":false}','The passage describes pulmonary circulation as starting from the right ventricle to the lungs, and systemic circulation as starting from the left ventricle to the body’s tissues.','Circulatory circuits',NULL,'Breathing and circulation notes, Two circuits working together',1,0,'2026-10-25 11:00:36','2026-10-25 11:00:36'),(40,14,1,'multiple_choice','In guinea pigs, black fur (B) is dominant over white fur (b). What fraction of the offspring of a Bb × bb cross is expected to have white fur?',1.00,'[{\"key\":\"A\",\"text\":\"1\\/4\"},{\"key\":\"B\",\"text\":\"1\\/2\"},{\"key\":\"C\",\"text\":\"3\\/4\"},{\"key\":\"D\",\"text\":\"None of them\"}]','{\"correct\":\"B\"}','Bb × bb gives Bb, Bb, bb and bb, so half of the offspring have white fur.','Punnett squares',5,NULL,0,1,'2026-10-25 16:30:00','2026-10-25 16:30:00'),(41,14,2,'multiple_choice','In four o\'clock flowers, red and white show incomplete dominance, so Rr plants are pink. Two pink plants are crossed. Which ratio of offspring is expected?',2.00,'[{\"key\":\"A\",\"text\":\"All pink\"},{\"key\":\"B\",\"text\":\"3 red : 1 white\"},{\"key\":\"C\",\"text\":\"1 red : 2 pink : 1 white\"},{\"key\":\"D\",\"text\":\"1 red : 1 white\"}]','{\"correct\":\"C\"}','Rr × Rr gives RR, Rr, Rr and rr: one red, two pink and one white.','Non-Mendelian inheritance',4,NULL,0,1,'2026-10-25 16:30:00','2026-10-25 16:30:00'),(42,14,3,'multiple_choice','A mother has type A blood with genotype IAi, and a father has type B blood with genotype IBi. Which blood types are possible for their children?',2.00,'[{\"key\":\"A\",\"text\":\"A and B only\"},{\"key\":\"B\",\"text\":\"AB only\"},{\"key\":\"C\",\"text\":\"A, B, AB and O\"},{\"key\":\"D\",\"text\":\"O only\"}]','{\"correct\":\"C\"}','IAi × IBi gives IAIB (type AB), IAi (type A), IBi (type B) and ii (type O).','Non-Mendelian inheritance',4,NULL,0,1,'2026-10-25 16:30:00','2026-10-25 16:30:00'),(43,14,4,'true_false','An organism\'s genotype is the trait you can observe, such as the color of its fur.',1.00,NULL,'{\"correct\":false}','False. The trait you can observe is the phenotype. The genotype is the pair of alleles, such as Bb.','Vocabulary',4,NULL,0,1,'2026-10-25 16:30:00','2026-10-25 16:30:00'),(44,14,5,'short_answer','What word describes an organism with two different alleles for a gene, such as Bb?',1.00,NULL,'{\"accepted\":[\"heterozygous\",\"heterozygote\"],\"case_sensitive\":false}','Heterozygous: two different alleles. Two of the same, such as BB or bb, is homozygous.','Vocabulary',4,NULL,0,1,'2026-10-25 16:30:00','2026-10-25 16:30:00');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `bucket` varchar(40) NOT NULL,
  `subject_hash` char(64) NOT NULL,
  `window_start` datetime NOT NULL,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`,`subject_hash`,`window_start`),
  KEY `idx_rate_limits_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reading_versions`
--

DROP TABLE IF EXISTS `reading_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reading_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `material_id` int(10) unsigned NOT NULL,
  `kind` varchar(20) NOT NULL,
  `content` mediumtext NOT NULL,
  `status` enum('draft','approved') NOT NULL DEFAULT 'draft',
  `ai_assisted` tinyint(1) NOT NULL DEFAULT 0,
  `source_version_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reading_versions_kind` (`material_id`,`kind`),
  KEY `fk_reading_versions_org` (`organization_id`),
  KEY `fk_reading_versions_creator` (`created_by`),
  KEY `fk_reading_versions_approver` (`approved_by`),
  CONSTRAINT `fk_reading_versions_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reading_versions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reading_versions_material` FOREIGN KEY (`material_id`) REFERENCES `learning_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reading_versions_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reading_versions`
--

LOCK TABLES `reading_versions` WRITE;
/*!40000 ALTER TABLE `reading_versions` DISABLE KEYS */;
INSERT INTO `reading_versions` VALUES (1,1,1,'filipino','# Breathing and circulation notes\n\nBawat selula sa iyong katawan ay naglalabas ng enerhiya mula sa pagkain sa pamamagitan ng **cellular respiration** (respirasyon ng selula). Ang prosesong ito ay gumagamit ng **oxygen** at bumubuo ng **carbon dioxide** bilang basurang produkto. Ang **respiratory system** (sistemang paghinga) ay nagdadala ng oxygen papasok sa katawan at nag-aalis ng carbon dioxide. Ang **circulatory system** (sistemang sirkulasyon) ay nagdadala ng parehong mga gas sa pagitan ng mga baga at ng natitirang bahagi ng katawan. Walang sistemang kayang gawin ito nang mag-isa.\n\n## Paghinga\n\nKapag humihinga ka papasok, ang hangin ay pumapasok sa pamamagitan ng ilong o bibig. Ang mga buhok at mucus sa loob ng **nasal cavity** (lukab ng ilong) ay humuhuli ng alikabok, at ang hangin ay pinaiinit at binabasa. Ang hangin ay dadaan sa **pharynx** (lalamunan) at **larynx** (kahon ng boses), pagkatapos ay bababa sa **trachea** (windpipe). Ang mga singsing ng **cartilage** (kartilago) ay nagpapanatiling bukas ang trachea.\n\nAng trachea ay naghahati sa dalawang **bronchi**, isa para sa bawat baga. Sa loob ng mga baga, ang mga bronchi ay nahahati sa mas maliliit na tubo na tinatawag na **bronchioles**, na nagtatapos sa mga kumpol ng napakaliit na **air sacs** na tinatawag na **alveoli**.\n\nAng mga baga ay naglalaman ng daan‑daang milyong **alveoli**. Ang kanilang mga pader ay isang selula lamang ang kapal at bawat isa ay napalilibutan ng isang lambat ng **capillaries** (kapilyar). Ang **oxygen** ay dumadaloy mula sa hangin sa alveoli, kung saan mataas ang konsentrasyon, papunta sa dugo, kung saan mas mababa ang konsentrasyon. Ang **carbon dioxide** ay dumadaloy sa kabaligtaran, mula sa dugo papunta sa alveoli, at inilalabas sa paghinga palabas.\n\nTatlong katangian ang nagpapabilis ng palitan na ito: ang napakalaking kabuuang **surface area** (lawak ng ibabaw) ng mga alveoli, ang manipis na mga pader nito, at ang kanilang **moist lining** (basang sapin sa loob) na nagpapahintulot sa mga gas na matunaw bago dumaloy.\n\nAng paghinga ay pangunahing pinapagana ng **diaphragm**, isang dome‑shaped na kalamnan sa ilalim ng mga baga. Kapag humihinga ka papasok, ang diaphragm ay nagko‑contract at nagiging patag, at ang mga kalamnan sa pagitan ng mga tadyang ay humihila sa **rib cage** (balangkas ng tadyang) pataas at palabas. Lumalaki ang **chest cavity** (espasyo ng dibdib), bumababa ang presyon ng hangin sa loob ng mga baga kaysa sa labas, at dumadaloy ang hangin papasok.\n\nKapag humihinga ka palabas, ang diaphragm ay nagre‑relax at bumabalik sa orihinal na posisyon, ang rib cage ay bumababa at papasok, lumiliit ang chest cavity, at itinutulak palabas ang hangin.\n\n## Puso at Sirkulasyon\n\nAng **heart** (puso) ay isang muscular pump na kasing laki ng iyong kamao. Mayroon itong apat na silid: dalawang **atria** sa itaas at dalawang **ventricles** (ventrikulo) sa ibaba. Ang kanang bahagi ng puso ay tumatanggap ng dugo na mababa ang **oxygen** (oxygen‑poor) mula sa katawan at pinapadaloy ito papunta sa mga baga. Ang kaliwang bahagi ay tumatanggap ng dugo na mayaman sa **oxygen** (oxygen‑rich) mula sa mga baga at pinapadaloy ito sa natitirang bahagi ng katawan.\n\nAng mga **valves** (balbula) sa pagitan ng mga silid at sa mga labasan ng mga ventricle ay nagpapanatiling umaagos ang dugo sa isang direksyon lamang. Ang kaliwang ventricle ay may pinakamakapal na pader ng kalamnan dahil kailangan nitong ibomba ang dugo sa buong katawan.\n\n- **Arteries** (arterya) ay nagdadala ng dugo palabas mula sa puso. Ang kanilang mga pader ay makapal at elastic upang kayanin ang mataas na presyon.  \n- **Veins** (mga ugat) ay nagdadala ng dugo pabalik sa puso. Mas manipis ang kanilang mga pader at may mga balbula na pumipigil sa pagbalik ng dugo.  \n- **Capillaries** (kapilyar) ay ang pinakamaliit na daluyan. Ang kanilang mga pader ay isang selula lamang ang kapal, kaya’t pinapayagan ang **oxygen**, mga nutrisyon, **carbon dioxide**, at iba pang basurang produkto na dumaloy sa pagitan ng dugo at mga selula ng katawan.\n\nAng dugo ay dumadaloy sa dalawang circuit:\n\n1. **Pulmonary circulation** (pulmonaryong sirkulasyon): mula sa kanang ventricle papunta sa mga baga, kumukuha ng **oxygen**, at bumabalik sa kaliwang atrium.  \n2. **Systemic circulation** (sistemikong sirkulasyon): mula sa kaliwang ventricle papunta sa mga tisyu ng katawan, nagdadala ng **oxygen**, at bumabalik sa kanang atrium.\n\n## Ehersisyo at Kalusugan\n\nKapag nag-eehersisyo ka, ang iyong mga kalamnan ay nangangailangan ng mas maraming **oxygen** at bumubuo ng mas maraming **carbon dioxide**. Mas mabilis at mas malalim ang iyong paghinga, at mas mabilis ang tibok ng puso na nagbobomba ng mas maraming dugo sa bawat tibok. Ganito sabay na tumutugon ang dalawang sistema upang matugunan ang pangangailangan ng katawan.\n\nAng **smoking** (paninigarilyo) ay sumisira sa panloob na lining ng mga daanan ng hangin at sa mga pader ng mga alveoli, at pinapahirapan ang puso. Ang regular na pisikal na aktibidad ay nagpapalakas ng kalamnan ng puso at tumutulong sa mga baga na magtrabaho nang mahusay. Ang pag-iwas sa **second‑hand smoke** (usok mula sa ibang tao) at pagsusuot ng maayos na sukat na maskara kapag may makakapal na usok o hamog ay nagpoprotekta sa mga daanan ng hangin. Ang balanseng diyeta na naglilimita sa sobrang **salt** (asin) at **saturated fat** ay nakakatulong upang mapanatiling malusog ang mga daluyan ng dugo.\n\n## Mga Tanong\n\n1. Sundan ang landas ng isang molekula ng **oxygen** mula sa hangin na iyong nilalanghap hanggang sa isang selula ng kalamnan sa iyong binti.  \n2. Ipaliwanag kung bakit napakamanipis ng mga pader ng mga **alveoli** at **capillaries**.  \n3. Bakit mas makapal ang pader ng kaliwang ventricle kumpara sa kanang ventricle?','approved',1,1,2,2,'2026-10-23 12:30:17','2026-10-23 10:30:17','2026-10-23 12:30:17'),(2,1,1,'simpler','# Breathing and circulation notes  \n\nEvery cell in your body gets energy from food by **cellular respiration** (the process cells use to turn food into energy). This process needs **oxygen** (the gas we breathe in) and makes **carbon dioxide** (a waste gas) as a by‑product.  \n\nThe **respiratory system** (the parts that bring in oxygen and take out carbon dioxide) moves these gases in and out of the body. The **circulatory system** (the heart and blood vessels) carries the gases between the lungs and the rest of the body. Neither system can work alone.\n\n---\n\n## How air enters the body  \n\n1. You breathe in through your nose or mouth.  \n2. Hairs and **mucus** (sticky fluid) in the **nasal cavity** (the space inside the nose) catch dust.  \n3. The air is warmed and moistened.  \n4. The air passes the **pharynx** (throat) and the **larynx** (voice box).  \n5. It then goes down the **trachea** (windpipe). Rings of **cartilage** (hard tissue) keep the trachea open.  \n\nThe trachea splits into two **bronchi** (large tubes), one for each lung. Inside each lung the bronchi branch into smaller tubes called **bronchioles**. The bronchioles end in many tiny air sacs called **alveoli**.\n\n---\n\n## The lungs and gas exchange  \n\n- Lungs have hundreds of millions of alveoli.  \n- Each alveolus wall is only one cell thick.  \n- Each alveolus is surrounded by a network of **capillaries** (the smallest blood vessels).  \n\n**Oxygen** moves (diffuses) from the air in the alveolus, where there is a lot of it, into the blood, where there is less. **Carbon dioxide** moves the opposite way, from the blood into the alveolus, and is breathed out.\n\nThree things make this exchange fast:  \n\n1. Very large total surface area of all the alveoli.  \n2. Very thin walls of alveoli and capillaries.  \n3. A moist lining that lets gases dissolve before they diffuse.\n\n---\n\n## How breathing works  \n\nThe main driver is the **diaphragm** (a dome‑shaped muscle under the lungs).  \n\n- **Inhale** (breathing in): the diaphragm contracts and flattens. Muscles between the ribs pull the rib cage up and out. The chest cavity gets bigger, so air pressure inside the lungs becomes lower than outside pressure. Air flows in.  \n\n- **Exhale** (breathing out): the diaphragm relaxes and moves back up. The rib cage moves down and in. The chest cavity gets smaller, pushing air out.\n\n---\n\n## The heart and blood vessels  \n\nThe heart is a muscular pump about the size of your fist. It has four chambers:  \n\n- Two upper chambers called **atria** (singular: atrium).  \n- Two lower chambers called **ventricles**.  \n\nThe right side of the heart receives **oxygen‑poor** (low‑oxygen) blood from the body and pumps it to the lungs. The left side receives **oxygen‑rich** (high‑oxygen) blood from the lungs and pumps it to the rest of the body.\n\n**Valves** (flap‑like doors) between the chambers and at the exits of the ventricles keep blood moving in one direction. The **left ventricle** has the thickest muscular wall because it must push blood all the way around the body.\n\n- **Arteries** carry blood away from the heart. Their walls are thick and elastic (stretchy) to handle high pressure.  \n- **Veins** carry blood back to the heart. Their walls are thinner and they contain valves that stop blood from flowing backward.  \n- **Capillaries** are the smallest vessels. Their walls are one cell thick, allowing oxygen, nutrients, carbon dioxide, and other wastes to pass between the blood and body cells.\n\n---\n\n## Two blood circuits  \n\n1. **Pulmonary circulation** – blood goes from the right ventricle to the lungs, picks up oxygen, and returns to the left atrium.  \n2. **Systemic circulation** – blood goes from the left ventricle to the body’s tissues, delivers oxygen, and returns to the right atrium.\n\n---\n\n## What happens during exercise  \n\n- Muscles need more oxygen and make more carbon dioxide.  \n- You breathe faster and deeper.  \n- Your heart beats faster and pumps more blood with each beat.  \n\nThese changes show how the breathing and circulatory systems work together to meet the body’s needs.\n\n---\n\n## Health tips  \n\n- **Smoking** damages the lining of the airways and the walls of the alveoli, and makes the heart work harder.  \n- Regular physical activity strengthens the heart muscle and helps the lungs work well.  \n- Avoid second‑hand smoke and wear a well‑fitted mask when there is heavy smoke or haze to protect the airways.  \n- A balanced diet low in excess salt and saturated fat keeps blood vessels healthy.\n\n---\n\n### Review questions  \n\n1. Trace the path of an oxygen molecule from the air you breathe to a muscle cell in your leg.  \n2. Explain why the walls of the alveoli and the capillaries are so thin.  \n3. Why does the left ventricle have a thicker wall than the right ventricle?','approved',1,1,2,2,'2026-10-23 12:30:17','2026-10-23 10:30:17','2026-10-23 12:30:17'),(3,1,2,'filipino','# Heredity notes  \n\n## Pagmamana: inheritance at variation  \n**Genes (mga gene), chromosomes (kromosoma) at DNA**  \nAng mga katangian (traits) ay mga katangiang naipapasa mula sa magulang papunta sa kanilang mga anak, tulad ng hugis ng buto o kulay ng bulaklak. Ang mga tagubilin para sa mga katangian ay dala ng **genes**.  \n\nAng **gene** ay isang bahagi ng **DNA**, at matatagpuan ang mga gene sa **chromosomes** sa loob ng nucleus ng selula.  \n\nAng mga selula ng katawan ng tao ay may **46 chromosomes** na nakaayos sa **23 pares**. Isang kromosoma mula sa bawat pares ay nagmumula sa ina at ang isa naman ay nagmumula sa ama. Dahil ang mga kromosoma ay nasa pares, karamihan ng mga gene ay nasa pares din.  \n\nAng magkaibang bersyon ng parehong gene ay tinatawag na **alleles**. Halimbawa, ang gene para sa haba ng tangkay sa mga halamang gisantes ay may isang allele para sa matataas na tangkay at isa pang allele para sa maikling tangkay.  \n\n## Ang mga eksperimento ni Mendel sa halamang gisantes  \nSi **Gregor Mendel**, isang mongheng nabuhay noong 1800s, ay nag-aral ng pagmamana sa pamamagitan ng pagpapalahi (crossing) ng mga halamang gisantes na may magkasalungat na katangian, tulad ng matataas at maikling tangkay.  \n\nNang pagtawirin ni Mendel ang mga pure‑breeding na matataas na halaman at ang mga pure‑breeding na maiikling halaman, lahat ng supling sa unang henerasyon, na tinatawag na \\(F_1\\), ay matataas. Nang hayaang mag‑self‑pollinate ang mga \\(F_1\\) na halaman, humigit‑kumulang tatlong‑kapat ng ikalawang henerasyon, na tinatawag na \\(F_2\\), ay matataas at isang‑kapat ay maikli.  \n\nMula sa mga resulta, nahinuha ni Mendel na bawat halaman ay may dalawang **factor** (ngayon ay tinatawag na **alleles**) para sa isang katangian. Maaaring itago ng isang allele ang epekto ng isa pa: ang allele na lumalabas ay **dominant** at ang nakatago ay **recessive**. Ang dalawang allele ay naghihiwalay kapag nabubuo ang mga selulang sekswal (**gametes**), kaya’t bawat gamete ay may isang allele lamang. Ang ideyang ito ay tinatawag na **law of segregation** (batas ng paghihiwalay).  \n\n## Paggamit ng Punnett square  \nIsinusulat ng mga genetiko ang **dominant allele** gamit ang malaking titik at ang **recessive allele** gamit ang parehong titik ngunit maliit (lowercase). Para sa haba ng tangkay, **T** ay tumutukoy sa matataas at **t** ay tumutukoy sa maikli.  \n\nAng kombinasyon ng mga allele na taglay ng isang organismo ay tinatawag na **genotype**. Ang katangiang nakikita ay tinatawag na **phenotype**. Ang organismo na may dalawang magkaparehong allele, **TT** o **tt**, ay **homozygous**. Ang organismo na may dalawang magkaibang allele, **Tt**, ay **heterozygous**. Ang isang heterozygous na halaman ng gisantes ay mataas, dahil ang **T** ay dominant.  \n\nAng **Punnett square** ay nagpapakita ng lahat ng posibleng kombinasyon ng allele mula sa isang pag‑cross. Sa pag‑cross ng dalawang heterozygous na halaman (**Tt × Tt**), ang mga genotype ng mga supling ay **TT**, **Tt**, **Tt**, at **tt**. Ang **genotypic ratio** ay \\(1\\;TT : 2\\;Tt : 1\\;tt\\), at ang **phenotypic ratio** ay \\(3\\) matataas : \\(1\\) maikli. Ang posibilidad na ang alinmang isang supling ay maikli ay \\(1\\) sa \\(4\\), o \\(25\\%\\).  \n\n## Mga pattern na lampas sa simpleng dominansiya  \nHindi lahat ng katangian ay sumusunod sa simpleng dominansiya.  \n\n* Sa **incomplete dominance** (hindi ganap na dominansiya), ang heterozygous na supling ay nagpapakita ng phenotype na nasa gitna ng dalawang magulang. Halimbawa, kapag pinagtawid ang **four o’clock** na may pulang bulaklak at ang may puting bulaklak, ang mga supling ay may pink na bulaklak.  \n\n* Sa **codominance** (kapwa dominansiya), parehong ganap na naipapakita ang dalawang allele. Ang mga **roan cattle** ay may pulang balahibo at puting balahibo. Sa mga tao, sa **ABO blood types**, ang **A** at **B** na allele ay **codominant**, kaya ang isang taong nagmana ng pareho ay may uri ng dugo na **AB**.  \n\n* May ilang gene na may higit sa dalawang allele sa isang populasyon. Ang gene para sa uri ng dugo (ABO) ay may tatlong allele: **IA**, **IB**, at **i**. Ang **i** allele ay **recessive**, kaya ang isang taong may uri ng dugo **O** ay may genotype na **ii**.  \n\n* Ang mga **sex‑linked traits** ay kontrolado ng mga gene sa **X chromosome**. Ang **red‑green colour blindness** ay mas karaniwan sa mga lalaki dahil ang mga lalaki ay may iisang X chromosome lamang, kaya sapat na ang isang recessive allele sa X chromosome para lumitaw ang katangian.','draft',1,2,2,NULL,NULL,'2026-10-25 16:30:33','2026-10-25 16:30:33');
/*!40000 ALTER TABLE `reading_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recovery_codes`
--

DROP TABLE IF EXISTS `recovery_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recovery_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `code_hash` char(64) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recovery_codes_hash` (`user_id`,`code_hash`),
  CONSTRAINT `fk_recovery_codes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recovery_codes`
--

LOCK TABLES `recovery_codes` WRITE;
/*!40000 ALTER TABLE `recovery_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `recovery_codes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_items`
--

DROP TABLE IF EXISTS `review_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `review_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `source` enum('practice','quiz') NOT NULL,
  `source_key` varchar(60) NOT NULL,
  `practice_set_id` int(10) unsigned DEFAULT NULL,
  `assignment_id` int(10) unsigned DEFAULT NULL,
  `material_id` int(10) unsigned DEFAULT NULL,
  `kind` enum('choice','text','card') NOT NULL,
  `content` longtext NOT NULL,
  `step` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `reviews` int(10) unsigned NOT NULL DEFAULT 0,
  `lapses` int(10) unsigned NOT NULL DEFAULT 0,
  `due_on` date NOT NULL,
  `last_reviewed_on` date DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review_items_source` (`user_id`,`source_key`),
  KEY `idx_review_items_due` (`user_id`,`done_at`,`due_on`),
  KEY `idx_review_items_reviewed` (`user_id`,`last_reviewed_on`),
  KEY `idx_review_items_class` (`class_id`),
  KEY `fk_review_items_org` (`organization_id`),
  KEY `fk_review_items_practice` (`practice_set_id`),
  KEY `fk_review_items_assignment` (`assignment_id`),
  KEY `fk_review_items_material` (`material_id`),
  CONSTRAINT `fk_review_items_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_items_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_items_material` FOREIGN KEY (`material_id`) REFERENCES `learning_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_items_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_items_practice` FOREIGN KEY (`practice_set_id`) REFERENCES `practice_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_items_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_items`
--

LOCK TABLES `review_items` WRITE;
/*!40000 ALTER TABLE `review_items` DISABLE KEYS */;
INSERT INTO `review_items` VALUES (1,1,6,1,'practice','practice:1:2',1,NULL,2,'choice','{\"kind\":\"choice\",\"prompt\":\"What is the genotypic ratio of offspring from a cross between two heterozygous tall pea plants (Tt × Tt)?\",\"choices\":[\"1 TT : 1 Tt : 2 tt\",\"1 TT : 2 Tt : 1 tt\",\"2 TT : 1 Tt : 1 tt\",\"1 TT : 1 Tt : 1 tt\"],\"correct\":1,\"explanation\":\"A Punnett square for Tt × Tt gives the ratio 1 TT : 2 Tt : 1 tt.\",\"source\":\"Heredity notes, p. 3\"}',0,0,0,'2026-10-24',NULL,NULL,'2026-10-24 14:44:28','2026-10-24 14:44:28'),(2,1,6,1,'practice','practice:1:4',1,NULL,2,'choice','{\"kind\":\"choice\",\"prompt\":\"Which human blood type results from inheriting both the A and B alleles?\",\"choices\":[\"Type A\",\"Type B\",\"Type AB\",\"Type O\"],\"correct\":2,\"explanation\":\"A and B alleles are codominant, so a person with both has type AB blood.\",\"source\":\"Heredity notes, p. 4\"}',0,0,0,'2026-10-24',NULL,NULL,'2026-10-24 14:44:28','2026-10-24 14:44:28'),(3,1,6,1,'practice','practice:2:1',2,NULL,1,'card','{\"kind\":\"card\",\"prompt\":\"List the order of structures air passes through from the nose to the alveoli.\",\"back\":\"Nose\\/mouth → nasal cavity (hairs and mucus) → pharynx → larynx → trachea → bronchi → bronchioles → alveoli.\",\"source\":\"Breathing and circulation notes, The path air takes\"}',1,1,0,'2026-10-26','2026-10-25',NULL,'2026-10-25 14:44:42','2026-10-26 14:44:42'),(4,1,6,1,'practice','practice:2:4',2,NULL,1,'card','{\"kind\":\"card\",\"prompt\":\"What are the two circulatory circuits and their main functions?\",\"back\":\"Pulmonary circulation: blood moves from the right ventricle to the lungs to pick up oxygen and returns to the left atrium. Systemic circulation: blood moves from the left ventricle to body tissues to deliver oxygen and returns to the right atrium.\",\"source\":\"Breathing and circulation notes, Two circuits working together\"}',0,0,0,'2026-10-25',NULL,NULL,'2026-10-25 14:44:42','2026-10-25 14:44:42'),(5,1,6,1,'quiz','quiz:1:2',NULL,1,NULL,'choice','{\"prompt\":\"Which chamber of the heart pumps oxygen-rich blood to the rest of the body?\",\"explanation\":\"The left ventricle has the thickest wall because it pumps blood around the whole body.\",\"source\":\"Respiratory and circulatory systems check\",\"kind\":\"choice\",\"choices\":[\"Right atrium\",\"Right ventricle\",\"Left atrium\",\"Left ventricle\"],\"correct\":3}',0,0,0,'2026-10-26',NULL,NULL,'2026-10-26 12:32:54','2026-10-26 12:32:54'),(6,1,6,1,'quiz','quiz:1:6',NULL,1,NULL,'text','{\"prompt\":\"Name the tiny blood vessels where substances pass between the blood and the body\'s cells.\",\"explanation\":\"Capillaries have walls one cell thick.\",\"source\":\"Respiratory and circulatory systems check\",\"kind\":\"text\",\"accepted\":[\"capillaries\",\"capillary\"],\"case_sensitive\":false}',0,0,0,'2026-10-26',NULL,NULL,'2026-10-26 12:32:54','2026-10-26 12:32:54'),(7,1,6,3,'quiz','quiz:6:20',NULL,6,NULL,'choice','{\"prompt\":\"Solve by factoring: x^2 - 7x + 10 = 0\",\"explanation\":\"(x - 2)(x - 5) = 0.\",\"source\":\"Solving quadratic equations by factoring\",\"kind\":\"choice\",\"choices\":[\"x = 2 or x = 5\",\"x = -2 or x = -5\",\"x = 1 or x = 10\",\"x = -1 or x = -10\"],\"correct\":0}',0,0,0,'2026-10-26',NULL,NULL,'2026-10-26 12:32:54','2026-10-26 12:32:54');
/*!40000 ALTER TABLE `review_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(100) NOT NULL,
  `applied_at` datetime NOT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_migrations`
--

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
INSERT INTO `schema_migrations` VALUES ('001_core.sql','2026-10-25 12:24:02'),('002_academics.sql','2026-10-25 12:24:02'),('003_materials.sql','2026-10-25 12:24:02'),('004_assessments.sql','2026-10-25 12:24:03'),('005_ai_and_practice.sql','2026-10-25 12:24:03'),('006_section_copies.sql','2026-10-25 12:24:03'),('007_assignment_overrides.sql','2026-10-25 12:24:03'),('008_attempt_reopen.sql','2026-10-25 12:24:03'),('009_accounts_without_email.sql','2026-10-25 12:24:03'),('010_paper_scores.sql','2026-10-25 12:24:03'),('011_notices_and_consent.sql','2026-10-25 12:24:03'),('012_site_feedback.sql','2026-10-25 12:24:03'),('013_pilot_indexes.sql','2026-10-25 12:24:03'),('014_grading_periods.sql','2026-10-25 12:24:03'),('015_review_items.sql','2026-10-25 12:24:03'),('016_comment_bank.sql','2026-10-25 12:24:03'),('017_two_step.sql','2026-10-25 12:24:03'),('018_competencies.sql','2026-10-25 12:24:03'),('019_heading_search.sql','2026-10-25 12:24:03'),('020_material_gaps.sql','2026-10-25 12:24:04'),('021_reading_versions.sql','2026-10-25 12:24:04'),('022_message_mode.sql','2026-10-25 12:24:04'),('023_anti_cheating.sql','2026-10-26 15:38:01'),('024_attempt_anti_cheating.sql','2026-10-26 15:38:01');
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `academic_period_id` int(10) unsigned DEFAULT NULL,
  `grade_level` varchar(40) DEFAULT NULL,
  `name` varchar(60) NOT NULL,
  `adviser_id` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sections_name` (`organization_id`,`academic_period_id`,`grade_level`,`name`),
  KEY `idx_sections_adviser` (`adviser_id`),
  KEY `fk_sections_period` (`academic_period_id`),
  CONSTRAINT `fk_sections_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sections_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sections_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sections`
--

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
INSERT INTO `sections` VALUES (1,1,2,'Grade 9','Narra',2,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(2,1,2,'Grade 9','Molave',3,'2026-10-26 12:24:04','2026-10-26 12:24:04');
/*!40000 ALTER TABLE `sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shared_excerpts`
--

DROP TABLE IF EXISTS `shared_excerpts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shared_excerpts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned NOT NULL,
  `kind` enum('excerpt','reflection') NOT NULL,
  `content` text NOT NULL,
  `student_note` varchar(1000) DEFAULT NULL,
  `seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shared_excerpts_class` (`class_id`,`created_at`),
  KEY `idx_shared_excerpts_student` (`student_id`),
  KEY `fk_shared_excerpts_org` (`organization_id`),
  CONSTRAINT `fk_shared_excerpts_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shared_excerpts_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shared_excerpts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shared_excerpts`
--

LOCK TABLES `shared_excerpts` WRITE;
/*!40000 ALTER TABLE `shared_excerpts` DISABLE KEYS */;
/*!40000 ALTER TABLE `shared_excerpts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_feedback`
--

DROP TABLE IF EXISTS `site_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_feedback` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `user_role` varchar(20) NOT NULL,
  `category` enum('problem','confusing','idea') NOT NULL,
  `body` text NOT NULL,
  `page_path` varchar(200) DEFAULT NULL,
  `status` enum('new','looking','fixed','wont_fix') NOT NULL DEFAULT 'new',
  `reply` varchar(500) DEFAULT NULL,
  `replied_by` int(10) unsigned DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_site_feedback_org` (`organization_id`,`status`,`created_at`),
  KEY `idx_site_feedback_time` (`organization_id`,`created_at`),
  KEY `idx_site_feedback_user` (`user_id`,`created_at`),
  KEY `fk_site_feedback_replier` (`replied_by`),
  CONSTRAINT `fk_site_feedback_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_site_feedback_replier` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_site_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_feedback`
--

LOCK TABLES `site_feedback` WRITE;
/*!40000 ALTER TABLE `site_feedback` DISABLE KEYS */;
INSERT INTO `site_feedback` VALUES (1,1,6,'student','confusing','I could not find the correct answers after I submitted the quiz. Are they hidden until later?','/student/assignments/1','fixed','Yes. Your teacher releases the answers after the due date, and they then appear on the same page as your receipt.',1,'2026-10-15 12:24:06','2026-10-14 12:24:06','2026-10-15 12:24:06'),(2,1,8,'student','problem','The class page took a long time to open on mobile data this morning.','/student/classes/3','looking',NULL,NULL,NULL,'2026-10-23 12:24:06','2026-10-23 12:24:06'),(3,1,9,'student','idea','Could the flashcards show a picture from the module?','/student/learning','wont_fix','Not during the pilot, but we have noted it for later.',1,'2026-10-18 12:24:06','2026-10-17 12:24:06','2026-10-18 12:24:06'),(4,1,2,'teacher','idea','It would help to print the score grid as a class record sheet.','/teacher/assignments/11/scores','new',NULL,NULL,NULL,'2026-10-25 12:24:06','2026-10-25 12:24:06'),(5,1,3,'teacher','confusing','I was not sure whether held scores count in the gradebook before I release them.','/teacher/gradebook','new',NULL,NULL,NULL,'2026-10-26 06:24:06','2026-10-26 06:24:06');
/*!40000 ALTER TABLE `site_feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `study_sessions`
--

DROP TABLE IF EXISTS `study_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `study_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `class_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `notes` text DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `duration_minutes` smallint(5) unsigned NOT NULL DEFAULT 30,
  `completed_at` datetime DEFAULT NULL,
  `source` enum('manual','companion') NOT NULL DEFAULT 'manual',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_study_sessions_user` (`user_id`,`starts_at`),
  KEY `fk_study_sessions_org` (`organization_id`),
  KEY `fk_study_sessions_class` (`class_id`),
  CONSTRAINT `fk_study_sessions_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_study_sessions_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_study_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_study_sessions_duration` CHECK (`duration_minutes` between 5 and 480)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `study_sessions`
--

LOCK TABLES `study_sessions` WRITE;
/*!40000 ALTER TABLE `study_sessions` DISABLE KEYS */;
INSERT INTO `study_sessions` VALUES (1,1,6,1,'Review Punnett squares before the heredity quiz','Page 3 of the heredity notes.','2026-10-27 11:00:00',30,NULL,'manual','2026-10-25 12:24:05');
/*!40000 ALTER TABLE `study_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subjects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `grading_group` varchar(40) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_name` (`organization_id`,`name`),
  CONSTRAINT `fk_subjects_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,1,'Science','SCI','math_science',NULL,NULL,'2026-06-28 12:24:04','2026-06-28 12:24:04'),(2,1,'Mathematics','MATH','math_science',NULL,NULL,'2026-06-28 12:24:04','2026-06-28 12:24:04'),(3,1,'English','ENG','languages',NULL,NULL,'2026-06-28 12:24:04','2026-06-28 12:24:04'),(4,1,'Filipino','FIL','languages',NULL,NULL,'2026-06-28 12:24:04','2026-06-28 12:24:04');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_answers`
--

DROP TABLE IF EXISTS `submission_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_answers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` int(10) unsigned NOT NULL,
  `question_id` int(10) unsigned NOT NULL,
  `response` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_awarded` decimal(6,2) DEFAULT NULL,
  `needs_manual` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_submission_answers` (`submission_id`,`question_id`),
  KEY `idx_submission_answers_question` (`question_id`,`is_correct`),
  CONSTRAINT `fk_submission_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submission_answers_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=311 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_answers`
--

LOCK TABLES `submission_answers` WRITE;
/*!40000 ALTER TABLE `submission_answers` DISABLE KEYS */;
INSERT INTO `submission_answers` VALUES (1,1,1,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(2,1,2,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(3,1,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(4,1,4,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(5,1,5,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(6,1,6,'veins',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(13,2,1,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(14,2,2,'D',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(15,2,3,'true',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(16,2,4,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(17,2,5,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(18,2,6,'veins',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(25,3,1,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(26,3,2,'D',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(27,3,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(28,3,4,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(29,3,5,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(30,3,6,'capillaries',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(37,4,1,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(38,4,2,'D',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(39,4,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(40,4,4,'D',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(41,4,5,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(42,4,6,'capillaries',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(49,5,1,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(50,5,2,'D',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(51,5,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(52,5,4,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(53,5,5,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(54,5,6,'capillaries',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(61,6,1,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(62,6,2,'D',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(63,6,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(64,6,4,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(65,6,5,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(66,6,6,'capillaries',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(73,7,1,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(74,7,2,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(75,7,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(76,7,4,'D',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(77,7,5,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(78,7,6,'capillaries',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(85,8,1,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(86,8,2,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(87,8,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(88,8,4,'D',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(89,8,5,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(90,8,6,'veins',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(97,9,1,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(98,9,2,'D',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(99,9,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(100,9,4,'D',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(101,9,5,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(102,9,6,'veins',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(109,10,1,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(110,10,2,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(111,10,3,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(112,10,4,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(113,10,5,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(114,10,6,'veins',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(121,18,7,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(122,18,8,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(123,18,9,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(124,18,10,'alleles',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(125,18,11,'Each tall parent can be heterozygous (Tt), carrying a hidden recessive allele for shortness. During gamete formation the alleles separate, so each parent can pass on t. If both pass on t, the offspring is tt and short. A Punnett square shows this happens about 1 in 4 times.',NULL,NULL,1,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(131,19,7,'A',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(132,19,8,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(133,19,9,'A',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(134,19,10,'alleles',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(135,19,11,'The parents can both be Tt. Then the child can get t from each parent and be short.',NULL,NULL,1,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(141,20,12,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(142,20,13,'D',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(143,20,14,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(144,20,15,'D',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(145,20,16,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(146,20,17,'veins',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(153,21,12,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(154,21,13,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(155,21,14,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(156,21,15,'D',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(157,21,16,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(158,21,17,'capillaries',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(165,22,12,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(166,22,13,'D',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(167,22,14,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(168,22,15,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(169,22,16,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(170,22,17,'veins',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(177,23,12,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(178,23,13,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(179,23,14,'true',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(180,23,15,'D',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(181,23,16,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(182,23,17,'capillaries',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(189,24,12,'C',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(190,24,13,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(191,24,14,'false',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(192,24,15,'D',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(193,24,16,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(194,24,17,'veins',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(201,25,18,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(202,25,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(203,25,20,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(204,25,21,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(205,25,22,'7',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(211,26,18,'A',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(212,26,19,'A',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(213,26,20,'A',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(214,26,21,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(215,26,22,'7',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(221,27,18,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(222,27,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(223,27,20,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(224,27,21,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(225,27,22,'7',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(231,28,18,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(232,28,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(233,28,20,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(234,28,21,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(235,28,22,'7',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(241,29,18,'A',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(242,29,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(243,29,20,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(244,29,21,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(245,29,22,'49',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(251,30,18,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(252,30,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(253,30,20,'A',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(254,30,21,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(255,30,22,'7',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(261,31,18,'A',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(262,31,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(263,31,20,'A',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(264,31,21,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(265,31,22,'49',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(271,32,18,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(272,32,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(273,32,20,'A',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(274,32,21,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(275,32,22,'7',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(281,33,18,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(282,33,19,'A',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(283,33,20,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(284,33,21,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(285,33,22,'7',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(291,34,18,'B',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(292,34,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(293,34,20,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(294,34,21,'true',1,1.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(295,34,22,'49',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(301,35,18,'A',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(302,35,19,'B',1,2.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(303,35,20,'B',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(304,35,21,'false',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04'),(305,35,22,'49',0,0.00,0,NULL,'2026-10-26 12:24:04','2026-10-26 12:24:04');
/*!40000 ALTER TABLE `submission_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submission_files`
--

DROP TABLE IF EXISTS `submission_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` int(10) unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `stored_path` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `size_bytes` int(10) unsigned NOT NULL,
  `sha256` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_submission_files_submission` (`submission_id`),
  CONSTRAINT `fk_submission_files_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_files`
--

LOCK TABLES `submission_files` WRITE;
/*!40000 ALTER TABLE `submission_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `submission_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submissions`
--

DROP TABLE IF EXISTS `submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `assignment_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `assignment_version_id` int(10) unsigned DEFAULT NULL,
  `attempt_no` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `source` enum('online','paper') NOT NULL DEFAULT 'online',
  `status` enum('in_progress','submitted','returned') NOT NULL DEFAULT 'in_progress',
  `body` mediumtext DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deadline_at` datetime DEFAULT NULL,
  `resume_minutes` smallint(5) unsigned DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `auto_score` decimal(7,2) DEFAULT NULL,
  `score` decimal(7,2) DEFAULT NULL,
  `points_possible` decimal(7,2) DEFAULT NULL,
  `grading_status` enum('ungraded','needs_review','graded') NOT NULL DEFAULT 'ungraded',
  `feedback` text DEFAULT NULL,
  `rubric_scores` longtext DEFAULT NULL,
  `feedback_status` enum('none','draft','released') NOT NULL DEFAULT 'none',
  `released_at` datetime DEFAULT NULL,
  `graded_by` int(10) unsigned DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `receipt_code` char(10) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `left_page_count` int(10) unsigned NOT NULL DEFAULT 0,
  `left_page_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `paste_blocked_count` int(10) unsigned NOT NULL DEFAULT 0,
  `anti_cheating` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_submissions_attempt` (`assignment_id`,`student_id`,`attempt_no`),
  UNIQUE KEY `uq_submissions_receipt` (`receipt_code`),
  KEY `idx_submissions_student` (`student_id`,`status`),
  KEY `idx_submissions_review` (`assignment_id`,`status`,`grading_status`,`submitted_at`),
  KEY `fk_submissions_version` (`assignment_version_id`),
  KEY `fk_submissions_grader` (`graded_by`),
  KEY `idx_submissions_org_time` (`organization_id`,`submitted_at`,`student_id`),
  KEY `idx_submissions_pair_status` (`assignment_id`,`student_id`,`status`,`is_late`),
  CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_grader` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_submissions_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_version` FOREIGN KEY (`assignment_version_id`) REFERENCES `assignment_versions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submissions`
--

LOCK TABLES `submissions` WRITE;
/*!40000 ALTER TABLE `submissions` DISABLE KEYS */;
INSERT INTO `submissions` VALUES (1,1,1,6,1,1,'online','submitted',NULL,'2026-10-23 06:52:00','2026-10-26 12:44:04',NULL,'2026-10-23 07:10:00',0,5.00,5.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-23 07:10:00','YKHUHVPX4H','2026-10-23 06:52:00','2026-10-26 12:24:04',0,0,0,NULL),(2,1,1,7,1,1,'online','submitted',NULL,'2026-10-22 07:55:00','2026-10-26 12:44:04',NULL,'2026-10-22 08:13:00',0,3.00,3.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-22 08:13:00','GHRF3PU628','2026-10-22 07:55:00','2026-10-26 12:24:04',0,0,0,NULL),(3,1,1,8,1,1,'online','submitted',NULL,'2026-10-21 08:58:00','2026-10-26 12:44:04',NULL,'2026-10-21 09:16:00',0,7.00,7.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-21 09:16:00','MG3JMC9C79','2026-10-21 08:58:00','2026-10-26 12:24:04',0,0,0,NULL),(4,1,1,9,1,1,'online','submitted',NULL,'2026-10-23 10:01:00','2026-10-26 12:44:04',NULL,'2026-10-23 10:19:00',0,4.00,4.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-23 10:19:00','579VAJT8VE','2026-10-23 10:01:00','2026-10-26 12:24:04',0,0,0,NULL),(5,1,1,10,1,1,'online','submitted',NULL,'2026-10-22 11:04:00','2026-10-26 12:44:04',NULL,'2026-10-22 11:22:00',0,7.00,7.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-22 11:22:00','P6FJXUDH83','2026-10-22 11:04:00','2026-10-26 12:24:04',0,0,0,NULL),(6,1,1,12,1,1,'online','submitted',NULL,'2026-10-23 07:10:00','2026-10-26 12:44:04',NULL,'2026-10-23 07:28:00',0,6.00,6.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-23 07:28:00','JV3KDAVKPR','2026-10-23 07:10:00','2026-10-26 12:24:04',0,0,0,NULL),(7,1,1,14,1,1,'online','submitted',NULL,'2026-10-21 09:16:00','2026-10-26 12:44:04',NULL,'2026-10-21 09:34:00',0,4.00,4.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-21 09:34:00','SGDE5SRSTT','2026-10-21 09:16:00','2026-10-26 12:24:04',0,0,0,NULL),(8,1,1,15,1,1,'online','submitted',NULL,'2026-10-23 10:19:00','2026-10-26 12:44:04',NULL,'2026-10-23 10:37:00',0,1.00,1.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-23 10:37:00','HF5SB245DX','2026-10-23 10:19:00','2026-10-26 12:24:04',0,0,0,NULL),(9,1,1,16,1,1,'online','submitted',NULL,'2026-10-22 11:22:00','2026-10-26 12:44:04',NULL,'2026-10-22 11:40:00',0,3.00,3.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-22 11:40:00','3ZX9HD5DH7','2026-10-22 11:22:00','2026-10-26 12:24:04',0,0,0,NULL),(10,1,1,17,1,1,'online','submitted',NULL,'2026-10-21 12:25:00','2026-10-26 12:44:04',NULL,'2026-10-21 12:43:00',0,3.00,3.00,7.00,'graded',NULL,NULL,'released','2026-10-25 12:24:04',NULL,'2026-10-21 12:43:00','VEDWZQ82F5','2026-10-21 12:25:00','2026-10-26 12:24:04',0,0,0,NULL),(11,1,2,8,2,1,'online','submitted','Resting: 16 breaths per minute. After jumping jacks: 34. After 5 minutes rest: 19.\n\nOur breathing rate more than doubled after exercise and almost returned to normal after resting. The leg muscles were using more oxygen for cellular respiration and making more carbon dioxide, so we breathed faster to bring in oxygen and remove CO2. My partner\'s pulse also went up, which means the heart pumped blood faster to carry the oxygen to the muscles.\n\nNext question: does breathing rate return to normal faster for students who exercise every day?','2026-10-24 09:15:00',NULL,NULL,'2026-10-24 10:05:00',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'4U5H7VQFUN','2026-10-24 09:15:00','2026-10-24 10:05:00',0,0,0,NULL),(12,1,2,12,2,1,'online','submitted','At rest 15, after exercise 31, after rest 18 breaths per minute.\n\nBreathing went up because the muscles needed more energy. The circulatory system moved blood faster too. Next I would test if holding your breath first changes the results.','2026-10-25 10:22:00',NULL,NULL,'2026-10-25 11:12:00',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'4C3XH33FFG','2026-10-25 10:22:00','2026-10-25 11:12:00',0,0,0,NULL),(13,1,2,14,2,1,'online','submitted','Rest 17 / exercise 30 / recovery 20.\n\nWe breathed faster after exercising because the body needs more oxygen. Next time I want to measure heart rate also.','2026-10-24 11:29:00',NULL,NULL,'2026-10-24 12:19:00',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'5HNH2X3WG3','2026-10-24 11:29:00','2026-10-24 12:19:00',0,0,0,NULL),(14,1,2,7,2,1,'online','submitted','Resting 18, after jumping jacks 29, after resting 22. Breathing increases when you exercise.','2026-10-25 12:36:00',NULL,NULL,'2026-10-25 13:26:00',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'8B3923NTKS','2026-10-25 12:36:00','2026-10-25 13:26:00',0,0,0,NULL),(15,1,2,16,2,1,'online','submitted','Data: 16, 33, 18. When we exercise the muscles need more oxygen and produce more carbon dioxide. The diaphragm works faster so more air comes in, and the heart beats faster so the blood carries the oxygen to the muscles quicker. Question: does the type of exercise matter?','2026-10-24 13:43:00',NULL,NULL,'2026-10-24 14:33:00',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'SYMR2S9NJJ','2026-10-24 13:43:00','2026-10-24 14:33:00',0,0,0,NULL),(16,1,2,6,2,1,'online','in_progress','Resting: 15 breaths per minute\nAfter jumping jacks: 32\nAfter 5 minutes rest: 17\n\nThe pattern: breathing rate doubled after exercise and went almost back to normal after resting.','2026-10-25 16:24:04',NULL,NULL,NULL,0,NULL,NULL,10.00,'ungraded',NULL,NULL,'none',NULL,NULL,NULL,NULL,'2026-10-25 16:24:04','2026-10-25 16:24:04',0,0,0,NULL),(17,1,2,9,2,1,'online','in_progress','Rest 18, exercise 30','2026-10-25 12:24:04',NULL,NULL,NULL,0,NULL,NULL,10.00,'ungraded',NULL,NULL,'none',NULL,NULL,NULL,NULL,'2026-10-25 12:24:04','2026-10-25 12:24:04',0,0,0,NULL),(18,1,3,8,3,1,'online','submitted',NULL,'2026-10-26 11:26:04','2026-10-26 12:44:04',NULL,'2026-10-26 11:44:04',0,4.00,NULL,8.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'PAWZ8PN7UR','2026-10-26 11:26:04','2026-10-26 11:44:04',0,0,0,NULL),(19,1,3,16,3,1,'online','submitted',NULL,'2026-10-26 10:51:04','2026-10-26 12:44:04',NULL,'2026-10-26 11:09:04',0,1.00,NULL,8.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'W4KFST53JY','2026-10-26 10:51:04','2026-10-26 11:09:04',0,0,0,NULL),(20,1,5,18,4,1,'online','submitted',NULL,'2026-10-23 07:52:00','2026-10-26 12:44:04',NULL,'2026-10-23 08:10:00',0,4.00,4.00,7.00,'graded',NULL,NULL,'draft',NULL,NULL,'2026-10-23 08:10:00','SM7CH2DRGE','2026-10-23 07:52:00','2026-10-23 08:10:00',0,0,0,NULL),(21,1,5,19,4,1,'online','submitted',NULL,'2026-10-22 07:57:00','2026-10-26 12:44:04',NULL,'2026-10-22 08:15:00',0,3.00,3.00,7.00,'graded',NULL,NULL,'draft',NULL,NULL,'2026-10-22 08:15:00','UST4QJNKX8','2026-10-22 07:57:00','2026-10-22 08:15:00',0,0,0,NULL),(22,1,5,20,4,1,'online','submitted',NULL,'2026-10-23 08:02:00','2026-10-26 12:44:04',NULL,'2026-10-23 08:20:00',0,6.00,6.00,7.00,'graded',NULL,NULL,'draft',NULL,NULL,'2026-10-23 08:20:00','WFDQ7DMGRQ','2026-10-23 08:02:00','2026-10-23 08:20:00',0,0,0,NULL),(23,1,5,22,4,1,'online','submitted',NULL,'2026-10-23 08:12:00','2026-10-26 12:44:04',NULL,'2026-10-23 08:30:00',0,2.00,2.00,7.00,'graded',NULL,NULL,'draft',NULL,NULL,'2026-10-23 08:30:00','2QE2ZCRK6K','2026-10-23 08:12:00','2026-10-23 08:30:00',0,0,0,NULL),(24,1,5,23,4,1,'online','submitted',NULL,'2026-10-22 08:17:00','2026-10-26 12:44:04',NULL,'2026-10-22 08:35:00',0,2.00,2.00,7.00,'graded',NULL,NULL,'draft',NULL,NULL,'2026-10-22 08:35:00','QS4K4RBAZD','2026-10-22 08:17:00','2026-10-22 08:35:00',0,0,0,NULL),(25,1,6,6,5,1,'online','submitted',NULL,'2026-10-24 10:47:00','2026-10-26 12:49:04',NULL,'2026-10-24 11:05:00',0,5.00,5.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-24 11:05:00','DSYG8DZ64K','2026-10-24 10:47:00','2026-10-26 12:24:04',0,0,0,NULL),(26,1,6,7,5,1,'online','submitted',NULL,'2026-10-23 11:51:00','2026-10-26 12:49:04',NULL,'2026-10-23 12:09:00',0,4.00,4.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-23 12:09:00','ZYS6C5GEGD','2026-10-23 11:51:00','2026-10-26 12:24:05',0,0,0,NULL),(27,1,6,8,5,1,'online','submitted',NULL,'2026-10-22 12:55:00','2026-10-26 12:49:04',NULL,'2026-10-22 13:13:00',0,5.00,5.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-22 13:13:00','GQKEPQ7CDS','2026-10-22 12:55:00','2026-10-26 12:24:05',0,0,0,NULL),(28,1,6,9,5,1,'online','submitted',NULL,'2026-10-21 10:59:00','2026-10-26 12:49:04',NULL,'2026-10-21 11:17:00',0,5.00,5.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-21 11:17:00','C55FE37RN5','2026-10-21 10:59:00','2026-10-26 12:24:05',0,0,0,NULL),(29,1,6,10,5,1,'online','submitted',NULL,'2026-10-24 12:03:00','2026-10-26 12:49:04',NULL,'2026-10-24 12:21:00',0,2.00,2.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-24 12:21:00','JXAMDDCXAC','2026-10-24 12:03:00','2026-10-26 12:24:05',0,0,0,NULL),(30,1,6,12,5,1,'online','submitted',NULL,'2026-10-22 11:11:00','2026-10-26 12:49:04',NULL,'2026-10-22 11:29:00',0,7.00,7.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-22 11:29:00','K26E3X37XD','2026-10-22 11:11:00','2026-10-26 12:24:05',0,0,0,NULL),(31,1,6,13,5,1,'online','submitted',NULL,'2026-10-21 12:15:00','2026-10-26 12:49:04',NULL,'2026-10-21 12:33:00',0,4.00,4.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-21 12:33:00','K2TPNWRKDT','2026-10-21 12:15:00','2026-10-26 12:24:05',0,0,0,NULL),(32,1,6,14,5,1,'online','submitted',NULL,'2026-10-24 13:19:00','2026-10-26 12:49:04',NULL,'2026-10-24 13:37:00',0,7.00,7.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-24 13:37:00','CSUTXT9PTN','2026-10-24 13:19:00','2026-10-26 12:24:05',0,0,0,NULL),(33,1,6,15,5,1,'online','submitted',NULL,'2026-10-23 11:23:00','2026-10-26 12:49:04',NULL,'2026-10-23 11:41:00',0,3.00,3.00,7.00,'graded',NULL,NULL,'draft',NULL,NULL,'2026-10-23 11:41:00','PS79BT27WG','2026-10-23 11:23:00','2026-10-23 11:41:00',0,0,0,NULL),(34,1,6,16,5,1,'online','submitted',NULL,'2026-10-22 12:27:00','2026-10-26 12:49:04',NULL,'2026-10-22 12:45:00',0,4.00,4.00,7.00,'graded',NULL,NULL,'released','2026-10-25 16:24:04',NULL,'2026-10-22 12:45:00','DMMWXKPRA8','2026-10-22 12:27:00','2026-10-26 12:24:05',0,0,0,NULL),(35,1,6,17,5,1,'online','submitted',NULL,'2026-10-21 13:31:00','2026-10-26 12:49:04',NULL,'2026-10-21 13:49:00',0,2.00,2.00,7.00,'graded',NULL,NULL,'draft',NULL,NULL,'2026-10-21 13:49:00','VTCFV222T5','2026-10-21 13:31:00','2026-10-21 13:49:00',0,0,0,NULL),(36,1,9,6,8,1,'online','submitted','I infer that Mara is worried about being late for something important. She has been waiting at the stop \"for the third time that week\", and she keeps \"glancing at her wristwatch and tapping her foot\". People do that when time is running out. She still lets the older women board first, so she is considerate even when she is in a hurry.','2026-10-20 10:22:00',NULL,NULL,'2026-10-20 11:12:00',0,NULL,9.00,10.00,'graded','Strong inference. Add one more sentence explaining how the second clue supports your idea.','{\"c1\":4,\"c2\":3,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','HP4JQGK695','2026-10-20 10:22:00','2026-10-26 12:24:05',0,0,0,NULL),(37,1,9,7,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-19 11:25:00',NULL,NULL,'2026-10-19 12:15:00',0,NULL,8.00,10.00,'graded','Good work. You found the right clues; push your inference a step further than the text states.','{\"c1\":3,\"c2\":3,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','FUDPUUQRG6','2026-10-19 11:25:00','2026-10-26 12:24:05',0,0,0,NULL),(38,1,9,8,8,1,'online','submitted','Mara is anxious about time, probably about being late for school or work. The clues are that she checks her watch again and again and taps her foot, and that this is the third time this week she has waited. Her umbrella on a clear day might show she is always prepared, or that the weather has been unpredictable.','2026-10-20 12:28:00',NULL,NULL,'2026-10-20 13:18:00',0,NULL,10.00,10.00,'graded','Excellent. Your inference is well supported, and you explained how each clue connects to it.','{\"c1\":4,\"c2\":4,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','Q3FK9FM8DT','2026-10-20 12:28:00','2026-10-26 12:24:05',0,0,0,NULL),(39,1,9,9,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-19 13:31:00',NULL,NULL,'2026-10-19 14:21:00',0,NULL,6.00,10.00,'graded','You summarised the passage well. Next time, say what the clues suggest that the writer does not state.','{\"c1\":2,\"c2\":2,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','GHDA6W6M7N','2026-10-19 13:31:00','2026-10-26 12:24:05',0,0,0,NULL),(40,1,9,10,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-20 10:34:00',NULL,NULL,'2026-10-20 11:24:00',0,NULL,8.00,10.00,'graded','Good work. You found the right clues; push your inference a step further than the text states.','{\"c1\":3,\"c2\":3,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','QKQTG5ZD9F','2026-10-20 10:34:00','2026-10-26 12:24:05',0,0,0,NULL),(41,1,9,11,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-19 11:37:00',NULL,NULL,'2026-10-19 12:27:00',0,NULL,6.00,10.00,'graded','You summarised the passage well. Next time, say what the clues suggest that the writer does not state.','{\"c1\":2,\"c2\":2,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','PNF2J99EUU','2026-10-19 11:37:00','2026-10-26 12:24:05',0,0,0,NULL),(42,1,9,12,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-20 12:40:00',NULL,NULL,'2026-10-20 13:30:00',0,NULL,9.00,10.00,'graded','Strong inference. Add one more sentence explaining how the second clue supports your idea.','{\"c1\":4,\"c2\":3,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','RQZCXMQ4NR','2026-10-20 12:40:00','2026-10-26 12:24:05',0,0,0,NULL),(43,1,9,14,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-20 10:46:00',NULL,NULL,'2026-10-20 11:36:00',0,NULL,8.00,10.00,'graded','Good work. You found the right clues; push your inference a step further than the text states.','{\"c1\":3,\"c2\":3,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','CJ2X3SKNJP','2026-10-20 10:46:00','2026-10-26 12:24:05',0,0,0,NULL),(44,1,9,15,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-19 11:49:00',NULL,NULL,'2026-10-19 12:39:00',0,NULL,7.00,10.00,'graded','A reasonable inference. Quote the clues directly so your evidence is easier to follow.','{\"c1\":3,\"c2\":2,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','H96U6ZR69B','2026-10-19 11:49:00','2026-10-26 12:24:05',0,0,0,NULL),(45,1,9,16,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-20 12:52:00',NULL,NULL,'2026-10-20 13:42:00',0,NULL,8.00,10.00,'graded','Good work. You found the right clues; push your inference a step further than the text states.','{\"c1\":3,\"c2\":3,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','CHUTSE2BEM','2026-10-20 12:52:00','2026-10-26 12:24:05',0,0,0,NULL),(46,1,9,17,8,1,'online','submitted','Mara is worried about being late because she keeps looking at her watch and tapping her foot. She has waited many times this week.','2026-10-19 13:55:00',NULL,NULL,'2026-10-19 14:45:00',0,NULL,7.00,10.00,'graded','A reasonable inference. Quote the clues directly so your evidence is easier to follow.','{\"c1\":3,\"c2\":2,\"c3\":2}','released','2026-10-22 12:24:05',4,'2026-10-22 12:24:05','BNPWSWZNMT','2026-10-19 13:55:00','2026-10-26 12:24:05',0,0,0,NULL),(47,1,10,8,9,1,'online','submitted','Story: \"The Mango Tree\". Clue 1: the grandmother waters the tree every evening. Inference: the tree is important to her family history. Clue 2: ... Clue 3: ...','2026-10-26 06:34:05',NULL,NULL,'2026-10-26 07:24:05',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'HTCSFACC8M','2026-10-26 06:34:05','2026-10-26 07:24:05',0,0,0,NULL),(48,1,10,12,9,1,'online','submitted','Story: \"The Mango Tree\". Clue 1: the grandmother waters the tree every evening. Inference: the tree is important to her family history. Clue 2: ... Clue 3: ...','2026-10-26 03:34:05',NULL,NULL,'2026-10-26 04:24:05',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'HV39DQ4C33','2026-10-26 03:34:05','2026-10-26 04:24:05',0,0,0,NULL),(49,1,10,10,9,1,'online','submitted','Story: \"The Mango Tree\". Clue 1: the grandmother waters the tree every evening. Inference: the tree is important to her family history. Clue 2: ... Clue 3: ...','2026-10-26 00:34:05',NULL,NULL,'2026-10-26 01:24:05',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'88EBWFUCXW','2026-10-26 00:34:05','2026-10-26 01:24:05',0,0,0,NULL),(50,1,10,14,9,1,'online','submitted','Story: \"The Mango Tree\". Clue 1: the grandmother waters the tree every evening. Inference: the tree is important to her family history. Clue 2: ... Clue 3: ...','2026-10-25 21:34:05',NULL,NULL,'2026-10-25 22:24:05',0,NULL,NULL,10.00,'needs_review',NULL,NULL,'none',NULL,NULL,NULL,'YJ8AR9FBBZ','2026-10-25 21:34:05','2026-10-25 22:24:05',0,0,0,NULL),(51,1,11,13,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,11.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(52,1,11,17,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,11.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(53,1,11,6,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,15.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(54,1,11,14,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,17.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(55,1,11,10,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,17.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(56,1,11,16,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,13.00,20.00,'graded',NULL,NULL,'draft',NULL,2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(57,1,11,8,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,18.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(58,1,11,11,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,8.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(59,1,11,12,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,16.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL),(60,1,11,15,10,1,'paper','submitted',NULL,'2026-10-23 02:00:00',NULL,NULL,'2026-10-23 02:00:00',0,NULL,15.00,20.00,'graded',NULL,NULL,'released','2026-10-25 09:00:00',2,'2026-10-24 08:00:00',NULL,'2026-10-26 12:24:05','2026-10-26 12:24:05',0,0,0,NULL);
/*!40000 ALTER TABLE `submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_tokens`
--

DROP TABLE IF EXISTS `user_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `purpose` enum('invitation','password_reset') NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_tokens_hash` (`token_hash`),
  KEY `idx_user_tokens_user` (`user_id`,`purpose`),
  KEY `fk_user_tokens_creator` (`created_by`),
  CONSTRAINT `fk_user_tokens_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_tokens`
--

LOCK TABLES `user_tokens` WRITE;
/*!40000 ALTER TABLE `user_tokens` DISABLE KEYS */;
INSERT INTO `user_tokens` VALUES (1,5,'invitation','4f7148c54e80f2ef262efedc2867e3c036c4818b5c5bf3f11108f87632febf07','2026-11-09 12:24:04',NULL,NULL,1,'2026-10-26 12:24:04'),(2,24,'invitation','de98a34235f426ccb508f8cfc92f7e993274b4f85a06bf0c8777c76e4650408a','2026-11-09 12:24:04',NULL,NULL,1,'2026-10-26 12:24:04');
/*!40000 ALTER TABLE `user_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization_id` int(10) unsigned NOT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `status` enum('invited','active','deactivated') NOT NULL DEFAULT 'invited',
  `email` varchar(190) DEFAULT NULL,
  `username` varchar(40) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `given_name` varchar(80) NOT NULL,
  `family_name` varchar(80) NOT NULL,
  `preferred_name` varchar(80) DEFAULT NULL,
  `grade_level` varchar(40) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `language` varchar(8) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `preferences` text DEFAULT NULL,
  `ai_enabled` tinyint(1) DEFAULT NULL,
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `totp_secret` varchar(255) DEFAULT NULL,
  `totp_enabled_at` datetime DEFAULT NULL,
  `totp_last_step` bigint(20) unsigned DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `anonymized_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_org_role` (`organization_id`,`role`,`status`),
  KEY `idx_users_org_name` (`organization_id`,`family_name`,`given_name`),
  CONSTRAINT `fk_users_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,'admin','active','teresa.villanueva@best-polomolok.edu.ph','teresa.villanueva','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Teresa','Villanueva','Ma. Teresa',NULL,NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-24 21:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(2,1,'teacher','active','ramon.dizon@best-polomolok.edu.ph','ramon.dizon','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Ramon','Dizon',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-24 23:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(3,1,'teacher','active','liza.mercado@best-polomolok.edu.ph','liza.mercado','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Liza','Mercado',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-24 20:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(4,1,'teacher','active','joy.santiago@best-polomolok.edu.ph','joy.santiago','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Joy','Santiago',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 00:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(5,1,'teacher','invited','carmela.reyes@best-polomolok.edu.ph','carmela.reyes',NULL,'Carmela','Reyes',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(6,1,'student','active',NULL,'andrea.cruz','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Andrea','Cruz',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-26 02:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(7,1,'student','active',NULL,'miguel.bautista','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Miguel','Bautista',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 23:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(8,1,'student','active',NULL,'bea.ramos','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Bea','Ramos',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-26 01:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(9,1,'student','active',NULL,'joshua.garcia','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Joshua','Garcia',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-26 06:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(10,1,'student','active',NULL,'patricia.lim','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Patricia','Lim',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 08:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(11,1,'student','active',NULL,'carlo.reyes','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Carlo','Reyes',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-26 06:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(12,1,'student','active',NULL,'nicole.tan','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Nicole','Tan',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 23:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(13,1,'student','active',NULL,'paolo.aquino','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Paolo','Aquino',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 03:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(14,1,'student','active',NULL,'sofia.delacruz','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Sofia','Dela Cruz',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-26 02:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(15,1,'student','active',NULL,'gabriel.torres','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Gabriel','Torres',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-24 20:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(16,1,'student','active',NULL,'kyla.navarro','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Kyla','Navarro',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 10:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(17,1,'student','active',NULL,'enzo.castillo','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Enzo','Castillo',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 07:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(18,1,'student','active',NULL,'janelle.ocampo','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Janelle','Ocampo',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 17:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(19,1,'student','active',NULL,'marco.villareal','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Marco','Villareal',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-24 21:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(20,1,'student','active',NULL,'trisha.mendoza','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Trisha','Mendoza',NULL,'Grade 9',NULL,NULL,NULL,NULL,1,1,1,'2026-10-24 22:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(21,1,'student','active',NULL,'daniel.soriano','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Daniel','Soriano',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-25 16:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(22,1,'student','active',NULL,'bianca.flores','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Bianca','Flores',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,'2026-10-26 08:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(23,1,'student','active',NULL,'luis.pascual','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Luis','Pascual',NULL,'Grade 9',NULL,NULL,NULL,NULL,0,1,1,'2026-10-25 10:24:04','2026-07-18 12:24:04',NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(24,1,'student','invited',NULL,'rafael.domingo',NULL,'Rafael','Domingo',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04'),(25,1,'student','deactivated',NULL,'angelica.santos','$2y$10$ek30Exsa.yejzu4uU/b6oeHy75RtazNPs71yH3JfxH0KrrC7h5dIG','Angelica','Santos',NULL,'Grade 9',NULL,NULL,NULL,NULL,NULL,1,1,NULL,'2026-07-18 12:24:04',NULL,NULL,NULL,'2026-10-06 12:24:04',NULL,'2026-07-08 12:24:04','2026-09-26 12:24:04');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-26 23:38:20

-- Move every date to today's school calendar (Asia/Manila), keeping the gaps between them. The school was built
-- late on 2026-10-26 (its newest records are from about 10:45 PM), so before 11 PM everything moves one day less:
-- what was made "just now" then lands yesterday evening, never later today (a quiz opening at 6:24 PM, say).
SET @local_now = CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+08:00');
SET @shift_days = DATEDIFF(DATE(@local_now), '2026-10-26') - IF(TIME(@local_now) < '23:00:00', 1, 0);
SET FOREIGN_KEY_CHECKS = 0;
UPDATE `academic_periods` SET `starts_on` = `starts_on` + INTERVAL @shift_days DAY, `ends_on` = `ends_on` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `ai_citations` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `ai_conversations` SET `archived_at` = `archived_at` + INTERVAL @shift_days DAY, `last_message_at` = `last_message_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `ai_grading_suggestions` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `ai_messages` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `ai_reports` SET `reviewed_at` = `reviewed_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `ai_usage_events` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `announcements` SET `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `assignments` SET `opens_at` = `opens_at` + INTERVAL @shift_days DAY, `due_at` = `due_at` + INTERVAL @shift_days DAY, `answers_released_at` = `answers_released_at` + INTERVAL @shift_days DAY, `published_at` = `published_at` + INTERVAL @shift_days DAY, `closed_at` = `closed_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `assignment_overrides` SET `due_at` = `due_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `assignment_versions` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `audit_logs` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `classes` SET `join_code_expires_at` = `join_code_expires_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `class_memberships` SET `joined_at` = `joined_at` + INTERVAL @shift_days DAY, `withdrawn_at` = `withdrawn_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `comment_bank` SET `last_used_at` = `last_used_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `competencies` SET `archived_at` = `archived_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `content_views` SET `first_viewed_at` = `first_viewed_at` + INTERVAL @shift_days DAY, `last_viewed_at` = `last_viewed_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `grading_history` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `grading_periods` SET `starts_on` = `starts_on` + INTERVAL @shift_days DAY, `ends_on` = `ends_on` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `guardian_consents` SET `answered_on` = `answered_on` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `learning_materials` SET `release_at` = `release_at` + INTERVAL @shift_days DAY, `published_at` = `published_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `lessons` SET `published_at` = `published_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `material_chunks` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `material_gaps` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `material_versions` SET `processed_at` = `processed_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `modules` SET `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `notice_acknowledgments` SET `acknowledged_at` = `acknowledged_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `notifications` SET `read_at` = `read_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `organizations` SET `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `organization_settings` SET `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `practice_sets` SET `completed_at` = `completed_at` + INTERVAL @shift_days DAY, `last_reviewed_at` = `last_reviewed_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `privacy_requests` SET `export_expires_at` = `export_expires_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY, `resolved_at` = `resolved_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `questions` SET `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `reading_versions` SET `approved_at` = `approved_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `recovery_codes` SET `used_at` = `used_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `review_items` SET `due_on` = `due_on` + INTERVAL @shift_days DAY, `last_reviewed_on` = `last_reviewed_on` + INTERVAL @shift_days DAY, `done_at` = `done_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `sections` SET `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `shared_excerpts` SET `seen_at` = `seen_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `site_feedback` SET `replied_at` = `replied_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `study_sessions` SET `starts_at` = `starts_at` + INTERVAL @shift_days DAY, `completed_at` = `completed_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `subjects` SET `archived_at` = `archived_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `submissions` SET `started_at` = `started_at` + INTERVAL @shift_days DAY, `deadline_at` = `deadline_at` + INTERVAL @shift_days DAY, `submitted_at` = `submitted_at` + INTERVAL @shift_days DAY, `released_at` = `released_at` + INTERVAL @shift_days DAY, `graded_at` = `graded_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `submission_answers` SET `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `submission_files` SET `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `users` SET `last_login_at` = `last_login_at` + INTERVAL @shift_days DAY, `password_changed_at` = `password_changed_at` + INTERVAL @shift_days DAY, `totp_enabled_at` = `totp_enabled_at` + INTERVAL @shift_days DAY, `deactivated_at` = `deactivated_at` + INTERVAL @shift_days DAY, `anonymized_at` = `anonymized_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY, `updated_at` = `updated_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
UPDATE `user_tokens` SET `expires_at` = `expires_at` + INTERVAL @shift_days DAY, `used_at` = `used_at` + INTERVAL @shift_days DAY, `revoked_at` = `revoked_at` + INTERVAL @shift_days DAY, `created_at` = `created_at` + INTERVAL @shift_days DAY WHERE @shift_days <> 0;
SET FOREIGN_KEY_CHECKS = 1;
