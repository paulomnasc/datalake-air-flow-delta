-- MySQL dump 10.13  Distrib 8.2.0, for Win64 (x86_64)
--
-- Host: 46.224.156.251    Database: fiscal
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `area_atuacao`
--

DROP TABLE IF EXISTS `area_atuacao`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `area_atuacao` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_catalogo_servicos` int DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `descricao` (`descricao`),
  KEY `id_catalogo_servicos` (`id_catalogo_servicos`),
  CONSTRAINT `area_atuacao_ibfk_1` FOREIGN KEY (`id_catalogo_servicos`) REFERENCES `catalogo_servicos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `area_atuacao`
--

LOCK TABLES `area_atuacao` WRITE;
/*!40000 ALTER TABLE `area_atuacao` DISABLE KEYS */;
INSERT INTO `area_atuacao` VALUES (1,1,'1. Apoio a Gestão de TIC '),(2,1,'2. Apoio Técnico de TIC'),(3,1,'3. Outros Serviços Relacionados a Apoio à Gestão e Técnico de TIC');
/*!40000 ALTER TABLE `area_atuacao` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `atividade_macro`
--

DROP TABLE IF EXISTS `atividade_macro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `atividade_macro` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_area_atuacao` int DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `descricao` (`descricao`),
  KEY `id_area_atuacao` (`id_area_atuacao`),
  CONSTRAINT `atividade_macro_ibfk_1` FOREIGN KEY (`id_area_atuacao`) REFERENCES `area_atuacao` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `atividade_macro`
--

LOCK TABLES `atividade_macro` WRITE;
/*!40000 ALTER TABLE `atividade_macro` DISABLE KEYS */;
INSERT INTO `atividade_macro` VALUES (1,1,' 1: Métricas de Software '),(2,1,'2: Gestão de Projetos'),(3,1,'3: Apoio a Governança de TIC'),(4,1,'4: Apoio a Inspeção e Conformidade '),(5,1,'5: Apoio a Gestão de Serviços - Infraestrutura'),(6,1,'6: Apoio a Gestão de Serviços - Sistemas '),(7,2,'10: Conteúdo Web'),(8,2,'11: Apoio a Segurança da Informação e Comunicações '),(9,2,'7: Arquitetura de Software '),(10,2,'8: Teste e Qualidade de Software '),(11,2,'9: Dados e Informações'),(12,3,'12: Outros Serviços');
/*!40000 ALTER TABLE `atividade_macro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `avaliacao_qualidade_sla`
--

DROP TABLE IF EXISTS `avaliacao_qualidade_sla`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `avaliacao_qualidade_sla` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_documento_recebimento` int DEFAULT NULL,
  `Nota_INS1_Pontualidade` float NOT NULL,
  `Nota_INS2_Qualidade` float NOT NULL,
  `Percentual_Glosa` float NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_documento_recebimento` (`id_documento_recebimento`),
  CONSTRAINT `avaliacao_qualidade_sla_ibfk_1` FOREIGN KEY (`id_documento_recebimento`) REFERENCES `documento_recebimento` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `avaliacao_qualidade_sla`
--

LOCK TABLES `avaliacao_qualidade_sla` WRITE;
/*!40000 ALTER TABLE `avaliacao_qualidade_sla` DISABLE KEYS */;
/*!40000 ALTER TABLE `avaliacao_qualidade_sla` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `catalogo_servicos`
--

DROP TABLE IF EXISTS `catalogo_servicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catalogo_servicos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_item_contrato` int DEFAULT NULL,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_item_contrato` (`id_item_contrato`),
  CONSTRAINT `catalogo_servicos_ibfk_1` FOREIGN KEY (`id_item_contrato`) REFERENCES `item_contrato` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `catalogo_servicos`
--

LOCK TABLES `catalogo_servicos` WRITE;
/*!40000 ALTER TABLE `catalogo_servicos` DISABLE KEYS */;
INSERT INTO `catalogo_servicos` VALUES (1,1,'Catalogo Item 2 - Consultoria de TIC');
/*!40000 ALTER TABLE `catalogo_servicos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documento_recebimento`
--

DROP TABLE IF EXISTS `documento_recebimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documento_recebimento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_os` int DEFAULT NULL,
  `Data_Assinatura` datetime DEFAULT NULL,
  `nup_sei` varchar(100) NOT NULL,
  `id_tipo_documento` int DEFAULT NULL,
  `id_usuario_fiscal_tecnico` int DEFAULT NULL,
  `id_usuario_fiscal_requisitante` int DEFAULT NULL,
  `id_usuario_gestor` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_os` (`id_os`),
  KEY `id_tipo_documento` (`id_tipo_documento`),
  KEY `id_usuario_fiscal_tecnico` (`id_usuario_fiscal_tecnico`),
  KEY `id_usuario_fiscal_requisitante` (`id_usuario_fiscal_requisitante`),
  KEY `id_usuario_gestor` (`id_usuario_gestor`),
  CONSTRAINT `documento_recebimento_ibfk_1` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`),
  CONSTRAINT `documento_recebimento_ibfk_2` FOREIGN KEY (`id_tipo_documento`) REFERENCES `tipo_documento` (`id`),
  CONSTRAINT `documento_recebimento_ibfk_3` FOREIGN KEY (`id_usuario_fiscal_tecnico`) REFERENCES `usuario` (`id`),
  CONSTRAINT `documento_recebimento_ibfk_4` FOREIGN KEY (`id_usuario_fiscal_requisitante`) REFERENCES `usuario` (`id`),
  CONSTRAINT `documento_recebimento_ibfk_5` FOREIGN KEY (`id_usuario_gestor`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documento_recebimento`
--

LOCK TABLES `documento_recebimento` WRITE;
/*!40000 ALTER TABLE `documento_recebimento` DISABLE KEYS */;
/*!40000 ALTER TABLE `documento_recebimento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_contrato`
--

DROP TABLE IF EXISTS `item_contrato`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_contrato` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gestor_substituto` varchar(255) NOT NULL,
  `Numero_Contrato` varchar(100) NOT NULL,
  `Objeto` varchar(255) NOT NULL,
  `Total_Horas_Contratadas` float NOT NULL,
  `Saldo_Horas` float NOT NULL,
  `Data_Inicio` datetime NOT NULL,
  `Data_Fim` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_contrato`
--

LOCK TABLES `item_contrato` WRITE;
/*!40000 ALTER TABLE `item_contrato` DISABLE KEYS */;
INSERT INTO `item_contrato` VALUES (1,'Não sei','06/2022','ITEM 2 - Serviços de consultoria técnica especializada em Tecnologia da Informação e Comunicação (TIC)',233329,0,'2025-08-20 01:00:00','2026-08-20 22:00:00');
/*!40000 ALTER TABLE `item_contrato` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_os`
--

DROP TABLE IF EXISTS `item_os`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_os` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Quantidade_Horas` float NOT NULL,
  `Profissional_Alocado` varchar(255) NOT NULL DEFAULT 'Nenhum',
  `id_servico` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_servico` (`id_servico`),
  CONSTRAINT `item_os_ibfk_1` FOREIGN KEY (`id_servico`) REFERENCES `servico` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_os`
--

LOCK TABLES `item_os` WRITE;
/*!40000 ALTER TABLE `item_os` DISABLE KEYS */;
/*!40000 ALTER TABLE `item_os` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordem_servico`
--

DROP TABLE IF EXISTS `ordem_servico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordem_servico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Horas_Alocadas` float NOT NULL,
  `nup_sei` varchar(100) NOT NULL,
  `Data_Emissao` datetime NOT NULL,
  `Data_Aceite` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordem_servico`
--

LOCK TABLES `ordem_servico` WRITE;
/*!40000 ALTER TABLE `ordem_servico` DISABLE KEYS */;
/*!40000 ALTER TABLE `ordem_servico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_item_os`
--

DROP TABLE IF EXISTS `os_item_os`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_item_os` (
  `id_os` int NOT NULL,
  `id_item_os` int NOT NULL,
  PRIMARY KEY (`id_os`,`id_item_os`),
  KEY `id_item_os` (`id_item_os`),
  CONSTRAINT `os_item_os_ibfk_1` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`),
  CONSTRAINT `os_item_os_ibfk_2` FOREIGN KEY (`id_item_os`) REFERENCES `item_os` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_item_os`
--

LOCK TABLES `os_item_os` WRITE;
/*!40000 ALTER TABLE `os_item_os` DISABLE KEYS */;
/*!40000 ALTER TABLE `os_item_os` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_status_recebimento`
--

DROP TABLE IF EXISTS `os_status_recebimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `os_status_recebimento` (
  `id_os` int NOT NULL,
  `id_status_recebimento` int NOT NULL,
  PRIMARY KEY (`id_os`,`id_status_recebimento`),
  KEY `id_status_recebimento` (`id_status_recebimento`),
  CONSTRAINT `os_status_recebimento_ibfk_1` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`),
  CONSTRAINT `os_status_recebimento_ibfk_2` FOREIGN KEY (`id_status_recebimento`) REFERENCES `status_recebimento` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_status_recebimento`
--

LOCK TABLES `os_status_recebimento` WRITE;
/*!40000 ALTER TABLE `os_status_recebimento` DISABLE KEYS */;
/*!40000 ALTER TABLE `os_status_recebimento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perfil`
--

DROP TABLE IF EXISTS `perfil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfil` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perfil`
--

LOCK TABLES `perfil` WRITE;
/*!40000 ALTER TABLE `perfil` DISABLE KEYS */;
INSERT INTO `perfil` VALUES (24,'Admin'),(25,'Teste');
/*!40000 ALTER TABLE `perfil` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servico`
--

DROP TABLE IF EXISTS `servico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `remuneracao` float NOT NULL,
  `base_horas_mes` float NOT NULL,
  `base_horas_complexidade` float NOT NULL,
  `sla_dias` int NOT NULL,
  `estim_max_ano` float NOT NULL,
  `id_atividade_macro` int DEFAULT NULL,
  `numero_item` varchar(100) NOT NULL,
  `descricao` varchar(100) NOT NULL,
  `entregaveis` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `servico_unique` (`numero_item`),
  UNIQUE KEY `servico_descr_unique_1` (`descricao`),
  KEY `id_atividade_macro` (`id_atividade_macro`),
  CONSTRAINT `servico_ibfk_1` FOREIGN KEY (`id_atividade_macro`) REFERENCES `atividade_macro` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servico`
--

LOCK TABLES `servico` WRITE;
/*!40000 ALTER TABLE `servico` DISABLE KEYS */;
INSERT INTO `servico` VALUES (1,140.8,176,140.8,18,1689.6,1,'1.1','1.1. Mensuração de software: novo desenvolvimento de sistema de informação, manutenção (evolutiva/ad','\"Relatório técnico sobre mensuração de softwares utilizando a técnica de análise por pontos por função. Identificar o propósito da contagem, tipo de contagem, fronteira da aplicação e escopo da contagem. Descrever quais são as funções dos tipos de dados: '),(2,140.8,176,140.8,18,140.8,1,'1.2','1.2. Elaborar  metodologia  para  mensuração  de software baseada nos métodos padrões de medição de ','Metodologia de mensuração de software.'),(3,5.87,176,35.2,1,422.4,2,'2.2','2.2. Apoiar na elaboração  relatório  de  acompanhamento  de atividades realizadas no período por pr','Relatório técnico contendo descritivo de atividades realizadas em determinado projeto. '),(4,88,176,88,11,1056,2,'2.6','2.6. Apoiar mensalmente no gerenciamento de projetos de aquisições,  desenvolvimento/evolução  de  s','\"Relatório da gerência de projetos (Gráfico de Gantt, Índices de valor agregado, dentre outros); Burn down do projeto, se for o caso; Entregáveis definidos na Metodologia de Gestão de Projetos da CONTRATANTE; Ordens de serviço elaborado no padrão da CONTR'),(5,4.22,176,35.2,1,422.4,2,'2.7','2.7. Orientação especializada sobre tecnologias relacionadas a infraestrutura, desenvolvimento, ferr','Orientação qualificada e fundamentada sobre escopo de soluções de TIC na área de integrações de soluções.'),(6,35.2,176,140.8,4,1689.6,2,'2.8','2.8. Apoiar a avaliação de cenários para interoperação ou integração de informações;','Resultado da avaliação com parecer técnico sobre cenário.'),(7,35.2,176,140.8,4,1689.6,2,'2.9','2.9. Diagnóstico de sistemas, base de dados ou arquiteturas para interoperação ou integração de info','Relatório com diagnóstico.'),(8,35.2,176,140.8,4,1689.6,2,'2.10','2.10. Análise de requisitos.','Requisitos definidos, documentados e priorizados.'),(9,35.2,176,140.8,4,1689.6,11,'9.1','9.1. Apoiar na análise e modelagem dos dados das aplicações sob desenvolvimento e manutenção;','\"Modelo Entidade Relacionamento - MER; Scripts de banco de dados; Relatório Técnico;\"'),(10,22,176,88,3,1056,11,'9.2','9.2. Propor e manter os normativos relativos aos padrões de criação de objetos de banco de dados imp','\"Minuta de Normativos; Relatórios Técnicos;\"'),(11,35.2,176,140.8,4,1689.6,11,'9.3','9.3. Propor, implementar e manter o modelo global de dados da CONTRATANTE;','\"Minuta de Documento de Referência do modelo global de dados; Relatório técnico.\"'),(12,35.2,176,140.8,4,1689.6,11,'9.4','9.4. Planejar e acompanhar os processos de replicação de dados;','Relatório técnico.'),(20,35.2,176,140.8,4,1689.59,11,'9.5','9.5. Realizar a análise de desempenho das aplicações e otimização das transações com o banco e dados','Relatório técnico'),(21,22,176,88,3,1056,11,'9.6','9.6. Realizar pesquisas, estudos e provas de conceito para a implementação de melhores práticas e te','Relatório técnico'),(22,17.6,176,88,2,1056,11,'9.7','9.7. Realizar serviços de apuração especial. (Executar rotinas em banco de dados como: inclusão, alt','\"Script de banco de dados; Relatório técnico;\"'),(23,17.6,176,88,2,1056,11,'9.8','9.8. Elaborar relatórios técnicos referentes à estrutura de dados da CONTRATANTE;','Relatório técnico contendo o mapeamento da estrutura de dados da CONTRATANTE.'),(24,70.4,176,140.8,9,1689.6,11,'9.9','9.9. Realizar projetos de administração de dados;','Plano de Administração de Dados.'),(25,70.4,176,140.8,9,1689.6,11,'9.10','9.10. Elaboração de modelo de dados e dicionário de dados;','Diagramas de Modelo de Dados Físicos e Lógicos.'),(26,35.2,176,140.8,4,1689.6,11,'9.11','9.11. Alteração de modelo de dados e dicionário de dados;','Scripts.'),(27,35.2,176,140.8,4,1689.6,11,'9.12','9.12. Validação de modelo de dados e dicionário de dados;','Parecer técnico.Parecer técnico.'),(28,35.2,176,140.8,4,1689.6,11,'9.13','9.13. Manutenção de dicionário de dados;','Scripts e artefatos.'),(29,46.93,176,140.8,6,1689.6,11,'9.14','9.14. Elaboração e execução de scripts;','Release de execução e Log´s  resultantes.'),(30,70.4,176,140.8,9,1689.6,11,'9.15','9.15. Geração de modelo de dados físico (engenharia reversa);','Scripts e artefatos.'),(31,44,176,88,6,1056,11,'9.16','9.16. Elaboração de procedimento de automatização para carga ou para extração de dados;','Scripts e artefatos.'),(32,29.33,176,88,4,1056,11,'9.17','9.17. Alteração de procedimento de automatização para carga ou para extração de dados;','Fluxos, scripts e dados.'),(33,29.33,176,88,4,1056,11,'9.18','9.18. Extração de dados;','Dados em pelo menos 02 formatos abertos.'),(34,29.33,176,88,4,1056,11,'9.19','9.19. Melhoria de desempenho em procedimentos e transação no SGBD;','Scripts, artefatos e Log\'s.'),(35,70.4,176,140.8,9,1689.6,11,'9.20','9.20. Integração de dados;','Fluxos, scripts e dados.'),(36,70.4,176,140.8,9,1689.6,11,'9.21','9.21. Construção de modelo multidimensional;','Diagrama do modelo, artefatos e scripts.'),(37,46.93,176,140.8,6,1689.6,11,'9.22','9.22. Alteração de modelo multidimensional;','Diagrama do modelo, artefatos e scripts.'),(38,23.47,176,140.8,3,1689.6,11,'9.23','9.23. Criação de relatório analítico;','Relatório analítico.'),(39,14.67,176,88,2,1056,11,'9.24','9.24. Alteração de relatório analítico','Diagrama do modelo, artefatos e scripts'),(40,70.4,176,140.8,99,1689.6,11,'9.25','9.25. Administração e configuração de Ferramentas de BI;','Artefatos, scripts, Log\'s.'),(41,2.89,176,88,1,1056,11,'9.26','9.26. Verificação do funcionamento dos ambientes de BI da CONTRATANTE;','Relatório técnico.'),(42,35.2,176,140.8,4,1689.59,11,'9.27','9.27. Análise e elaboração de modelos de dados, dentre os quais os modelos dimensionais e relacionai','Artefatos, scripts, Log\'s.'),(43,35.2,176,140.8,4,1689.6,11,'9.28','9.28. Planejamento e construção de processo de ETL (extração, transformação e carga de dados) nas ba','Plano de trabalho, cronograma, diagramas. '),(44,35.2,176,140.8,4,1689.6,11,'9.29','9.29. Construção de relatórios estáticos e dinâmicos, podendo conter interface interativa, com tabel','Relatório, artefatos, scripts, Log\'s.'),(45,35.2,176,140.8,4,1689.6,11,'9.30','9.30. Implementação de cubos de dados;','Artefatos, scripts, Log\'s.'),(46,5.87,176,35.2,1,422.4,11,'9.31','9.31. Documentação de ativos de BI;','Relatório técnico.'),(47,1.06,176,88,1,1056,11,'9.32','9.32. Suporte ao usuário para atividades e aplicações de BI. a) Exemplos de suporte: verificação e r','Chamado Atendido com feedback do usuário.'),(48,1.06,176,88,1,1056,11,'9.33','9.33. Solução de dúvidas técnicas dos usuários e gestores;','Chamado Atendido com feedback do usuário.'),(50,1.69,176,140.8,1,1689.6,11,'9.34','9.34. Apoio aos gestores nas diversas fases dos projetos, soluções e outras demandas correlatas com ','Chamado Atendido com feedback do usuário.'),(51,1.69,176,140.8,1,1689.6,11,'9.35','9.35. Apoio a projetos que tenham correlação com as ferramentas e soluções de BI disponíveis na CONT','Chamado Atendido com feedback do usuário.'),(52,46.93,176,140.8,6,1689.6,11,'9.36','9.36. Planejamento, gerenciamento e execução de projetos de BI;','Plano de trabalho, cronograma, diagramas, diagrama do modelo, artefatos e scripts.'),(53,46.93,176,140.8,6,1689.6,11,'9.37','9.37. Criação de projeto de data warehouse sob orientação da CONTRATANTE;','Plano de trabalho, cronograma, diagramas, diagrama do modelo, artefatos e scripts.'),(54,70.4,176,140.8,9,1689.6,11,'9.38','9.38. Migração de soluções de BI entre ambientes;','Plano de trabalho, cronograma, diagramas, diagrama do modelo, artefatos e scripts.'),(55,35.2,176,140.8,4,1689.6,11,'9.39','9.39. Ajustes, correções e melhorias sobre os ativos de BI, tais como projetos, documentações e solu','Plano de trabalho, cronograma, diagramas, diagrama do modelo, artefatos e scripts.'),(56,1.69,176,140.8,1,1689.6,11,'9.40','9.40. Operacionalização e monitoramento dos diversos produtos tecnológicos de BI da CONTRATANTE e pr','Chamado Atendido com feedback do usuário.'),(57,1.06,176,88,1,1056,11,'9.41','9.41. Colaboração com a coleta, a organização, a análise, o compartilhamento e o monitoramento de in','Chamado Atendido com feedback do usuário.'),(58,1.69,176,140.8,1,1689.6,11,'9.42','9.42. Aplicação de modelos analíticos, como regressão cluster, árvore de decisão e demais técnicas;','Chamado Atendido com feedback do usuário.'),(59,70.4,176,140.8,9,1689.6,11,'9.43','9.43. Migração de dados e estruturas de dados entre bases de plataformas heterogêneas;','Plano de trabalho, cronograma, diagramas, diagrama do modelo, artefatos e scripts.');
/*!40000 ALTER TABLE `servico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status`
--

DROP TABLE IF EXISTS `status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Status` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `Status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status`
--

LOCK TABLES `status` WRITE;
/*!40000 ALTER TABLE `status` DISABLE KEYS */;
/*!40000 ALTER TABLE `status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status_recebimento`
--

DROP TABLE IF EXISTS `status_recebimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_recebimento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status_recebimento`
--

LOCK TABLES `status_recebimento` WRITE;
/*!40000 ALTER TABLE `status_recebimento` DISABLE KEYS */;
INSERT INTO `status_recebimento` VALUES (1,'Aguardando Aceite'),(2,'Em Execução'),(3,'Em Validação'),(4,'TRP Emitido'),(5,'TRD Emitido');
/*!40000 ALTER TABLE `status_recebimento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipo_documento`
--

DROP TABLE IF EXISTS `tipo_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipo_documento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_documento`
--

LOCK TABLES `tipo_documento` WRITE;
/*!40000 ALTER TABLE `tipo_documento` DISABLE KEYS */;
INSERT INTO `tipo_documento` VALUES (1,'TRP'),(2,'TRD');
/*!40000 ALTER TABLE `tipo_documento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_perfil` int DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(100) NOT NULL,
  `email_confirmado` tinyint(1) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização do registro',
  `data_ultimo_pagamento` date DEFAULT NULL COMMENT 'Data do último pagamento realizado',
  `data_vencimento_assinatura` date DEFAULT NULL COMMENT 'Data de vencimento da assinatura',
  `status_assinatura` enum('trial','active','expired','cancelled') DEFAULT 'trial' COMMENT 'Status da assinatura: trial (30 dias grátis), active (paga), expired (vencida), cancelled (cancelada)',
  `data_inicio_trial` date DEFAULT NULL COMMENT 'Data de início do período de trial (30 dias)',
  `google_id` varchar(255) DEFAULT NULL COMMENT 'ID único do usuário no Google',
  `google_token` longtext COMMENT 'Token de acesso do Google (criptografado)',
  `google_refresh_token` longtext COMMENT 'Refresh token do Google (criptografado)',
  `auth_provider` varchar(50) DEFAULT NULL COMMENT 'Provedor de autenticação: google, email, etc',
  `auth_updated_at` timestamp NULL DEFAULT NULL COMMENT 'Data da última atualização de autenticação',
  `pagamento_inicial` tinyint(1) DEFAULT '0' COMMENT 'Pagamento inicial de $2,00 USD realizado',
  `perfil_comportamental` varchar(50) DEFAULT NULL,
  `data_ult_maladir` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_unique` (`email`),
  UNIQUE KEY `google_id` (`google_id`),
  KEY `idx_data_vencimento` (`data_vencimento_assinatura`),
  KEY `idx_status_assinatura` (`status_assinatura`),
  KEY `idx_google_id` (`google_id`),
  KEY `idx_auth_provider` (`auth_provider`),
  KEY `fk_usuario_perfil` (`id_perfil`),
  CONSTRAINT `fk_usuario_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=483 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (481,NULL,'Admin','admin@gmail.com','123',1,'2026-05-01 17:20:36','2026-05-01 18:15:28',NULL,NULL,'trial',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL),(482,NULL,'Test','test@test.com','123',1,'2026-05-02 16:02:03','2026-05-02 16:02:03',NULL,NULL,'trial',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `trg_atualiza_vencimento` BEFORE UPDATE ON `usuario` FOR EACH ROW BEGIN
    IF OLD.pagamento_inicial = 0 AND NEW.pagamento_inicial = 1 THEN
        SET NEW.data_vencimento_assinatura = DATE_ADD(CURDATE(), INTERVAL 60 DAY);
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `usuario_os`
--

DROP TABLE IF EXISTS `usuario_os`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_os` (
  `id_os` int NOT NULL,
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id_os`,`id_usuario`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `usuario_os_ibfk_1` FOREIGN KEY (`id_os`) REFERENCES `ordem_servico` (`id`),
  CONSTRAINT `usuario_os_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_os`
--

LOCK TABLES `usuario_os` WRITE;
/*!40000 ALTER TABLE `usuario_os` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_os` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_perfil`
--

DROP TABLE IF EXISTS `usuario_perfil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_perfil` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `id_perfil` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_perfil_unique` (`id_usuario`,`id_perfil`),
  KEY `fk_usuario_perfil_usuario_idx` (`id_usuario`),
  KEY `fk_usuario_perfil_perfil_idx` (`id_perfil`),
  CONSTRAINT `fk_usuario_perfil_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfil` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_perfil_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=279 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_perfil`
--

LOCK TABLES `usuario_perfil` WRITE;
/*!40000 ALTER TABLE `usuario_perfil` DISABLE KEYS */;
INSERT INTO `usuario_perfil` VALUES (278,481,24);
/*!40000 ALTER TABLE `usuario_perfil` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_recebimento`
--

DROP TABLE IF EXISTS `usuario_recebimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario_recebimento` (
  `id_recebimento` int NOT NULL,
  `id_usuario` int NOT NULL,
  PRIMARY KEY (`id_recebimento`,`id_usuario`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `usuario_recebimento_ibfk_1` FOREIGN KEY (`id_recebimento`) REFERENCES `documento_recebimento` (`id`),
  CONSTRAINT `usuario_recebimento_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_recebimento`
--

LOCK TABLES `usuario_recebimento` WRITE;
/*!40000 ALTER TABLE `usuario_recebimento` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_recebimento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'fiscal'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-09 10:16:26
