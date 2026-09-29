-- ============================================================================
-- AGROMONITOR — BANCO DE DADOS (VERSÃO FINALIZADA v2)
-- ============================================================================
-- Muda em relação à v1 (agromonitor_schema_final.sql):
--
--   1. `areas` perde `safra_id` e `cultura_id` — a área é cadastro fixo
--      (o imóvel/talhão físico); safra e cultura variam por PLANTIO, não
--      pela área. Uma mesma área pode ter plantios de safras/culturas
--      diferentes ao longo do tempo (ex: soja out/2026 → milho safrinha
--      mar/2027, ambos dentro da Safra 2026/2027).
--
--   2. `plantios` ganha `safra_id NOT NULL` — cada plantio pertence a
--      exatamente uma safra. Uma safra pode ter vários plantios (ex: soja
--      + milho safrinha na mesma área, no mesmo ano-safra).
--
--   3. `monitoramentos` ganha `plantio_id NOT NULL` — cada monitoramento é
--      feito em cima de um plantio específico (não da área "em geral").
--      A cultura e a safra do monitoramento passam a vir do plantio
--      (join), então `monitoramentos.cultura_id` foi removido.
--
--   4. `monitoramentos` perde `adubo_id`/`adubo_qtd_kg` — adubação de base
--      já está em `plantios`; adubação de cobertura decidida durante um
--      monitoramento já tem casa própria em `aplicacoes`.
--
--   5. `monitoramentos.quantidade_folhas` vira `estagio_fenologico
--      VARCHAR(20)` — texto livre (o app sugere VE/V4/V6.../R1/R2... via
--      JavaScript conforme a cultura do plantio, mas aceita qualquer
--      valor digitado pelo técnico/agrônomo).
--
-- Estrutura geral (herdada da v1): usuarios/clientes como atores; safras,
-- culturas, variedades, adubos, pragas como catálogos; areas → talhoes →
-- plantios como estrutura física; pontos_monitoramento → monitoramentos →
-- monitoramento_itens como cadeia de monitoramento; recomendacoes,
-- aplicacoes, relatorios como decisão/ação. `ocorrencias` continua
-- eliminada (absorvida por monitoramento_itens).
-- ============================================================================

CREATE DATABASE IF NOT EXISTS agromonitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agromonitor;

-- ============================================================================
-- TABELAS — ACESSO E ATORES
-- ============================================================================

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  login VARCHAR(50) NOT NULL UNIQUE,
  senha VARCHAR(255) NOT NULL COMMENT 'hash gerado via password_hash() no PHP, nunca texto puro',
  perfil VARCHAR(20) NOT NULL DEFAULT 'tecnico' COMMENT 'gestor | agronomo | tecnico | cliente',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_perfil (perfil),
  INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT,
  nome VARCHAR(150) NOT NULL,
  documento VARCHAR(20) COMMENT 'CPF ou CNPJ',
  telefone VARCHAR(20),
  email VARCHAR(150),
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELAS — CATÁLOGOS
-- ============================================================================

CREATE TABLE IF NOT EXISTS safras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL UNIQUE COMMENT 'texto livre, ex: "Safra 2026/2027"',
  mes_inicio TINYINT NOT NULL DEFAULT 1,
  ano_inicio INT NOT NULL,
  mes_fim TINYINT NOT NULL DEFAULT 12,
  ano_fim INT NOT NULL,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS culturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL UNIQUE,
  descricao TEXT,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS variedades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cultura_id INT NOT NULL,
  nome VARCHAR(100) NOT NULL,
  descricao TEXT,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cultura_id) REFERENCES culturas(id) ON DELETE CASCADE,
  UNIQUE KEY uk_cultura_variedade (cultura_id, nome),
  INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adubos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL UNIQUE,
  tipo VARCHAR(50),
  npk VARCHAR(20),
  descricao TEXT,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pragas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL UNIQUE,
  tipo VARCHAR(20) NOT NULL DEFAULT 'praga' COMMENT 'praga | doenca | planta_daninha | clima',
  cultura_id INT COMMENT 'NULL = aplica-se a qualquer cultura (ex: eventos climáticos)',
  nivel_risco VARCHAR(20) DEFAULT 'medio',
  descricao TEXT,
  ativa TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cultura_id) REFERENCES culturas(id) ON DELETE SET NULL,
  INDEX idx_ativa (ativa),
  INDEX idx_cultura (cultura_id),
  INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELAS — ÁREAS E PLANTIO
-- ============================================================================

-- Área = cadastro fixo (imóvel/talhão físico do cliente). Não tem safra
-- nem cultura própria — essas variam por PLANTIO (ver abaixo).
CREATE TABLE IF NOT EXISTS areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  nome VARCHAR(150) NOT NULL,
  hectares DECIMAL(10,2) NOT NULL,
  alqueires DECIMAL(10,2) GENERATED ALWAYS AS (hectares / 2.42) STORED,
  qtd_pontos INT NOT NULL DEFAULT 5,
  observacoes TEXT,
  geometria LONGTEXT,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
  INDEX idx_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS talhoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_id INT NOT NULL,
  nome VARCHAR(100) NOT NULL,
  variedade VARCHAR(100),
  tipo VARCHAR(20) DEFAULT 'producao',
  hectares DECIMAL(8,2),
  cor_hex VARCHAR(7) DEFAULT '#4caf50',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  INDEX idx_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantio = um ciclo de cultivo (uma cultura, numa safra, numa área).
-- É aqui que safra e cultura "acontecem" — uma área pode ter vários
-- plantios ao longo do tempo, inclusive mais de um dentro da mesma safra
-- (ex: soja + milho safrinha).
CREATE TABLE IF NOT EXISTS plantios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_id INT NOT NULL,
  talhao_id INT,
  safra_id INT NOT NULL,
  data_plantio DATE NOT NULL,
  cultura_id INT NOT NULL,
  variedade_id INT,
  populacao_semente DECIMAL(8,2),
  adubo_id INT,
  adubo_qtd_kg DECIMAL(8,2),
  condicao_plantio VARCHAR(20) DEFAULT 'apos_chuva',
  condicao_solo VARCHAR(20) DEFAULT 'boa',
  observacoes TEXT,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  FOREIGN KEY (talhao_id) REFERENCES talhoes(id) ON DELETE SET NULL,
  FOREIGN KEY (safra_id) REFERENCES safras(id) ON DELETE RESTRICT,
  FOREIGN KEY (cultura_id) REFERENCES culturas(id) ON DELETE RESTRICT,
  FOREIGN KEY (variedade_id) REFERENCES variedades(id) ON DELETE SET NULL,
  FOREIGN KEY (adubo_id) REFERENCES adubos(id) ON DELETE SET NULL,
  INDEX idx_area (area_id),
  INDEX idx_safra (safra_id),
  INDEX idx_data (data_plantio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELAS — MONITORAMENTO
-- ============================================================================

-- Pontos de amostragem ficam presos à ÁREA (posição física fixa no
-- terreno), não ao plantio — os mesmos pontos são reaproveitados
-- monitoramento após monitoramento, safra após safra.
CREATE TABLE IF NOT EXISTS pontos_monitoramento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_id INT NOT NULL,
  talhao_id INT,
  numero_ponto INT NOT NULL,
  latitude DECIMAL(10,8),
  longitude DECIMAL(11,8),
  data_registro DATE NOT NULL,
  observacoes TEXT,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  FOREIGN KEY (talhao_id) REFERENCES talhoes(id) ON DELETE SET NULL,
  UNIQUE KEY uk_area_ponto (area_id, numero_ponto),
  INDEX idx_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada monitoramento é feito num PONTO físico, mas em referência a um
-- PLANTIO específico (é o plantio que diz qual cultura e qual safra —
-- por isso não há mais campo de cultura solto aqui).
CREATE TABLE IF NOT EXISTS monitoramentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ponto_id INT NOT NULL,
  plantio_id INT NOT NULL,
  data_monitoramento DATE NOT NULL,
  estagio_fenologico VARCHAR(20) COMMENT 'texto livre; o app sugere VE/V4/V6/R1... conforme a cultura do plantio',
  milho_plantas_avaliadas INT,
  milho_plantas_praga INT,
  milho_percentual DECIMAL(5,2),
  soja_pragas_encontradas INT,
  soja_metros_lineares DECIMAL(4,1),
  soja_pragas_por_m2 DECIMAL(6,2),
  resultado_final DECIMAL(8,2),
  unidade_resultado VARCHAR(20),
  nivel_controle VARCHAR(20),
  observacoes TEXT,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ponto_id) REFERENCES pontos_monitoramento(id) ON DELETE CASCADE,
  FOREIGN KEY (plantio_id) REFERENCES plantios(id) ON DELETE CASCADE,
  INDEX idx_ponto (ponto_id),
  INDEX idx_plantio (plantio_id),
  INDEX idx_data (data_monitoramento),
  INDEX idx_nivel (nivel_controle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens registrados dentro de um monitoramento (cada praga/doença/planta
-- daninha/condição climática encontrada vira uma linha aqui). Absorve o
-- papel que antes era da tabela `ocorrencias` (eliminada).
CREATE TABLE IF NOT EXISTS monitoramento_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  monitoramento_id INT NOT NULL,
  tipo VARCHAR(20) NOT NULL DEFAULT 'praga' COMMENT 'praga | doenca | planta_daninha | clima',
  nome VARCHAR(150) NOT NULL,
  quantidade INT COMMENT 'contagem, quando aplicável (pragas). NULL para itens sem contagem (ex: clima)',
  severidade VARCHAR(20) COMMENT 'leve | moderada | severa — usado principalmente para doenca e clima',
  latitude DECIMAL(10,8) COMMENT 'sobrescreve a coordenada do ponto, se a ocorrência for pontual',
  longitude DECIMAL(11,8),
  observacoes TEXT,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (monitoramento_id) REFERENCES monitoramentos(id) ON DELETE CASCADE,
  INDEX idx_monitoramento (monitoramento_id),
  INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fotos anexadas a um monitoramento (foto da planta/ponto avaliado). Os
-- arquivos ficam salvos em disco (uploads/monitoramento_fotos/), aqui só
-- guardamos o nome do arquivo.
CREATE TABLE IF NOT EXISTS monitoramento_fotos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  monitoramento_id INT NOT NULL,
  arquivo VARCHAR(255) NOT NULL,
  descricao VARCHAR(255),
  favorita TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = foto escolhida pelo agrônomo para o relatório',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (monitoramento_id) REFERENCES monitoramentos(id) ON DELETE CASCADE,
  INDEX idx_monitoramento (monitoramento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELA — COLHEITA (1 por plantio, lançada no fim da safra)
-- ============================================================================
CREATE TABLE IF NOT EXISTS colheitas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plantio_id INT NOT NULL,
  data_colheita DATE NOT NULL,
  produtividade_sc_ha DECIMAL(8,2) NOT NULL COMMENT 'sacas por hectare',
  umidade_pct DECIMAL(5,2) NULL COMMENT 'umidade do grão na colheita (%)',
  observacoes TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_colheita_plantio (plantio_id),
  FOREIGN KEY (plantio_id) REFERENCES plantios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELAS — RECOMENDAÇÃO E APLICAÇÃO (RF-13 / RF-14)
-- ============================================================================

CREATE TABLE IF NOT EXISTS recomendacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  monitoramento_id INT NOT NULL,
  usuario_id INT COMMENT 'agrônomo responsável pela recomendação',
  texto TEXT NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (monitoramento_id) REFERENCES monitoramentos(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_monitoramento (monitoramento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aplicacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_id INT NOT NULL,
  talhao_id INT,
  plantio_id INT COMMENT 'plantio ao qual a aplicação se refere, se houver',
  monitoramento_id INT COMMENT 'monitoramento que motivou a aplicação, se houver',
  recomendacao_id INT COMMENT 'recomendação técnica que originou a aplicação, se houver',
  produto VARCHAR(150) NOT NULL,
  tipo_produto VARCHAR(30) DEFAULT 'defensivo' COMMENT 'defensivo | adubo | corretivo',
  dose DECIMAL(10,2),
  unidade_dose VARCHAR(20),
  data_aplicacao DATE NOT NULL,
  usuario_id INT COMMENT 'responsável pela aplicação',
  observacoes TEXT,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  FOREIGN KEY (talhao_id) REFERENCES talhoes(id) ON DELETE SET NULL,
  FOREIGN KEY (plantio_id) REFERENCES plantios(id) ON DELETE SET NULL,
  FOREIGN KEY (monitoramento_id) REFERENCES monitoramentos(id) ON DELETE SET NULL,
  FOREIGN KEY (recomendacao_id) REFERENCES recomendacoes(id) ON DELETE SET NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_area (area_id),
  INDEX idx_data (data_aplicacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELA — HISTÓRICO DE RELATÓRIOS (RF-15) — opcional
-- ============================================================================

CREATE TABLE IF NOT EXISTS relatorios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_id INT NOT NULL,
  safra_id INT COMMENT 'relatório resumido de uma safra inteira (todos os plantios dela)',
  plantio_id INT COMMENT 'relatório de um plantio específico, se for o caso',
  tipo VARCHAR(30) DEFAULT 'safra' COMMENT 'safra | plantio | individual (um monitoramento só)',
  arquivo_path VARCHAR(255),
  gerado_por INT,
  gerado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  FOREIGN KEY (safra_id) REFERENCES safras(id) ON DELETE SET NULL,
  FOREIGN KEY (plantio_id) REFERENCES plantios(id) ON DELETE SET NULL,
  FOREIGN KEY (gerado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DADOS INICIAIS (catálogos)
-- ============================================================================

INSERT IGNORE INTO safras (nome, mes_inicio, ano_inicio, mes_fim, ano_fim, ativa) VALUES
('Safra 2026/2027', 9, 2026, 9, 2027, 1);

INSERT IGNORE INTO culturas (nome, descricao, ativa) VALUES
('Milho', 'Milho para grãos', 1),
('Soja', 'Soja para grãos', 1);

INSERT IGNORE INTO variedades (cultura_id, nome, descricao, ativa) VALUES
(1, 'AG1051', 'Ciclo precoce', 1),
(1, 'AG7010', 'Ciclo normal', 1),
(1, 'P30F35', 'Ciclo normal', 1),
(1, '30F35', 'Ciclo super precoce', 1),
(1, 'DKB230', 'Ciclo semi-tardio', 1),
(2, 'M6211', 'Grupo 6.1', 1),
(2, 'M7371', 'Grupo 7.3', 1),
(2, 'BM3975', 'Grupo 3.9', 1),
(2, 'NK7059', 'Grupo 7.0', 1),
(2, 'NIDERA5909', 'Grupo 5.9', 1);

INSERT IGNORE INTO adubos (nome, tipo, npk, descricao, ativa) VALUES
('NPK 20-20-20', 'Fertilizante Misto', '20-20-20', 'Adubo formulado balanceado', 1),
('Uréia', 'Nitrogenado', '46-0-0', 'Fonte de nitrogênio', 1),
('MAP', 'Fosfatado', '0-46-0', 'Monoamônio fosfato', 1),
('KCl', 'Potássio', '0-0-60', 'Cloreto de potássio', 1),
('Ureia + Polímero', 'Nitrogenado', '46-0-0', 'Ureia revestida', 1),
('Superfosfato Simples', 'Fosfatado', '0-18-0', 'Fertilizante tradicional', 1),
('Cloreto de Potássio Granulado', 'Potássio', '0-0-60', 'Formulação granular', 1);

-- pragas/doenças: cultura_id 1 = Milho, 2 = Soja (conforme inseridos acima)
INSERT IGNORE INTO pragas (nome, tipo, cultura_id, nivel_risco, descricao, ativa) VALUES
('Lagarta-do-cartucho', 'praga', 1, 'alto', 'Praga importante', 1),
('Broca-do-colmo', 'praga', 1, 'medio', 'Perfura o colmo', 1),
('Largata-militar', 'praga', 1, 'medio', 'Alimenta-se das folhas', 1),
('Mosca-branca (Milho)', 'praga', 1, 'baixo', 'Vetor de viroses', 1),
('Ácaro-rajado (Milho)', 'praga', 1, 'baixo', 'Dano às folhas', 1),
('Percevejo-marrom', 'praga', 2, 'alto', 'Principal praga', 1),
('Mosca-branca (Soja)', 'praga', 2, 'medio', 'Vetor de viroses', 1),
('Ácaro-rajado (Soja)', 'praga', 2, 'medio', 'Reduz folhas', 1),
('Lagarta-falsa-medideira', 'praga', 2, 'alto', 'Desfolhadora', 1),
('Vaquinha', 'praga', 2, 'medio', 'Praga polífaga', 1),
('Ferrugem-comum', 'doenca', 1, 'medio', 'Fungo que forma pústulas nas folhas', 1),
('Cercosporiose', 'doenca', 1, 'medio', 'Mancha foliar que reduz área fotossintética', 1),
('Podridão-do-colmo', 'doenca', 1, 'alto', 'Apodrecimento do colmo, risco de tombamento', 1),
('Ferrugem-asiática', 'doenca', 2, 'alto', 'Principal doença da soja, perdas severas', 1),
('Mofo-branco', 'doenca', 2, 'alto', 'Fungo que ataca em condições de alta umidade', 1),
('Oídio', 'doenca', 2, 'medio', 'Pó branco nas folhas', 1),
('Antracnose', 'doenca', 2, 'medio', 'Manchas necróticas em hastes e vagens', 1),
-- plantas daninhas (tipo='planta_daninha', cultura_id NULL = aplica a qualquer cultura)
('Buva', 'planta_daninha', NULL, 'alto', 'Resistente a glifosato, difícil controle', 1),
('Capim-amargoso', 'planta_daninha', NULL, 'alto', 'Resistente a glifosato, forma touceiras', 1),
('Pé-de-galinha', 'planta_daninha', NULL, 'medio', 'Gramínea comum em bordaduras', 1),
('Caruru', 'planta_daninha', NULL, 'medio', 'Folha larga de crescimento rápido', 1),
('Corda-de-viola', 'planta_daninha', NULL, 'medio', 'Trepadeira que dificulta a colheita', 1),
('Trapoeraba', 'planta_daninha', NULL, 'medio', 'Tolerante a glifosato', 1),
('Tiguera (milho voluntário)', 'planta_daninha', NULL, 'medio', 'Milho da safra anterior que germina na soja', 1),
-- condições climáticas adversas (tipo='clima', cultura_id NULL = aplica a qualquer cultura)
('Geada', 'clima', NULL, 'alto', 'Queima de tecidos por temperatura abaixo de zero', 1),
('Seca Prolongada', 'clima', NULL, 'alto', 'Déficit hídrico prolongado, estresse na cultura', 1),
('Granizo', 'clima', NULL, 'alto', 'Dano mecânico direto às plantas', 1),
('Excesso de Chuva', 'clima', NULL, 'medio', 'Encharcamento do solo, risco de doenças fúngicas', 1);

-- ============================================================================
-- FIM DO SCRIPT
-- ============================================================================
