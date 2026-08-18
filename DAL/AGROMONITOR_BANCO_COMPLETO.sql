-- ============================================================================
-- AGROMONITOR - BANCO DE DADOS COMPLETO (SETUP + DADOS INICIAIS)
-- ============================================================================
-- Para importar tudo de uma vez no phpMyAdmin
-- Arquivo: AGROMONITOR_BANCO_COMPLETO.sql
-- ============================================================================

-- Criar banco (se não existir)
CREATE DATABASE IF NOT EXISTS agromonitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agromonitor;

-- ============================================================================
-- TABELAS
-- ============================================================================

-- Tabela: safras
CREATE TABLE IF NOT EXISTS safras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) UNIQUE NOT NULL,
  ano_inicio INT,
  ano_fim INT,
  ativa TINYINT(1) DEFAULT 1,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: areas
CREATE TABLE IF NOT EXISTS areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  safra_id INT,
  nome VARCHAR(150) NOT NULL,
  nome_cliente VARCHAR(150) NOT NULL,
  cultura VARCHAR(20),
  hectares DECIMAL(10,2),
  alqueires DECIMAL(10,2) GENERATED ALWAYS AS (hectares / 2.42) STORED,
  qtd_pontos INT DEFAULT 5,
  observacoes TEXT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (safra_id) REFERENCES safras(id) ON DELETE SET NULL,
  INDEX idx_safra (safra_id),
  INDEX idx_cliente (nome_cliente),
  INDEX idx_cultura (cultura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: talhoes
CREATE TABLE IF NOT EXISTS talhoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_id INT NOT NULL,
  nome VARCHAR(100),
  variedade VARCHAR(100),
  tipo VARCHAR(20),
  hectares DECIMAL(8,2),
  cor_hex VARCHAR(7),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  INDEX idx_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: culturas
CREATE TABLE IF NOT EXISTS culturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) UNIQUE NOT NULL,
  descricao TEXT,
  ativa TINYINT(1) DEFAULT 1,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: variedades
CREATE TABLE IF NOT EXISTS variedades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cultura_id INT NOT NULL,
  nome VARCHAR(100) NOT NULL,
  descricao TEXT,
  ativa TINYINT(1) DEFAULT 1,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cultura_id) REFERENCES culturas(id) ON DELETE CASCADE,
  UNIQUE KEY uk_cultura_variedade (cultura_id, nome),
  INDEX idx_cultura (cultura_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: adubos
CREATE TABLE IF NOT EXISTS adubos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) UNIQUE NOT NULL,
  tipo VARCHAR(50),
  npk VARCHAR(20),
  descricao TEXT,
  ativa TINYINT(1) DEFAULT 1,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativa (ativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: pragas
CREATE TABLE IF NOT EXISTS pragas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) UNIQUE NOT NULL,
  cultura VARCHAR(50),
  nivel_risco VARCHAR(20),
  descricao TEXT,
  ativa TINYINT(1) DEFAULT 1,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ativa (ativa),
  INDEX idx_cultura (cultura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: pontos_monitoramento
CREATE TABLE IF NOT EXISTS pontos_monitoramento (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_id INT NOT NULL,
  numero_ponto INT,
  latitude DECIMAL(10,8),
  longitude DECIMAL(10,8),
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  INDEX idx_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: plantios
CREATE TABLE IF NOT EXISTS plantios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_id INT NOT NULL,
  talhao_id INT,
  data_plantio DATE,
  cultura VARCHAR(30),
  cultura_id INT,
  variedade_id INT,
  populacao_semente DECIMAL(8,2),
  adubo_tipo VARCHAR(100),
  adubo_id INT,
  adubo_qtd_kg DECIMAL(8,2),
  condicao_plantio VARCHAR(50),
  condicao_solo VARCHAR(50),
  observacoes TEXT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  FOREIGN KEY (talhao_id) REFERENCES talhoes(id) ON DELETE CASCADE,
  FOREIGN KEY (variedade_id) REFERENCES variedades(id) ON DELETE SET NULL,
  FOREIGN KEY (adubo_id) REFERENCES adubos(id) ON DELETE SET NULL,
  INDEX idx_area (area_id),
  INDEX idx_cultura (cultura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: monitoramentos
CREATE TABLE IF NOT EXISTS monitoramentos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ponto_id INT,
  data_monitoramento DATE,
  cultura VARCHAR(50),
  milho_plantas_avaliadas INT,
  milho_plantas_praga INT,
  soja_pragas_encontradas INT,
  soja_metros_lineares DECIMAL(8,2),
  tipo_praga VARCHAR(100),
  resultado_final DECIMAL(10,2),
  unidade_resultado VARCHAR(20),
  observacoes TEXT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ponto_id) REFERENCES pontos_monitoramento(id) ON DELETE CASCADE,
  INDEX idx_ponto (ponto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: ocorrencias
CREATE TABLE IF NOT EXISTS ocorrencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ponto_id INT,
  data_ocorrencia DATE,
  tipo_praga VARCHAR(100),
  quantidade INT,
  observacoes TEXT,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ponto_id) REFERENCES pontos_monitoramento(id) ON DELETE CASCADE,
  INDEX idx_ponto (ponto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DADOS INICIAIS
-- ============================================================================

-- Safra padrão
INSERT INTO safras (nome, ano_inicio, ano_fim, ativa) VALUES
('Safra 2024/2025', 2024, 2025, 1);

-- Culturas
INSERT INTO culturas (id, nome, descricao, ativa) VALUES
(1, 'Milho', 'Cultura de milho (grãos)', 1),
(2, 'Soja', 'Cultura de soja (grãos)', 1);

-- Variedades de Milho
INSERT INTO variedades (cultura_id, nome, descricao, ativa) VALUES
(1, 'AG1051', 'Híbrido simples de milho', 1),
(1, 'AG7010', 'Híbrido para cultivo em regiões de altitude', 1),
(1, 'P30F35', 'Milho com ciclo precoce', 1),
(1, '30F35', 'Variedade com boa produtividade', 1),
(1, 'DKB230', 'Híbrido triplo adaptado', 1);

-- Variedades de Soja
INSERT INTO variedades (cultura_id, nome, descricao, ativa) VALUES
(2, 'M6211', 'Soja com ciclo médio', 1),
(2, 'M7371', 'Soja com resistência a doenças', 1),
(2, 'BM3975', 'Variedade com excelente performance', 1),
(2, 'NK7059', 'Soja adaptada para diferentes regiões', 1),
(2, 'NIDERA5909', 'Soja com ciclo precoce', 1);

-- Adubos
INSERT INTO adubos (nome, tipo, npk, descricao, ativa) VALUES
('NPK 20-20-20', 'Fertilizante Misto', '20-20-20', 'Adubo de fórmula balanceada', 1),
('Uréia', 'Nitrogenado', '46-0-0', 'Fonte concentrada de nitrogênio', 1),
('MAP', 'Fosfatado', '10-52-0', 'Monoamônio fosfato', 1),
('KCl', 'Potássico', '0-0-58', 'Cloreto de potássio', 1),
('Ureia + Polímero', 'Nitrogenado', '46-0-0', 'Uréia com revestimento polimérico', 1),
('Superfosfato Simples', 'Fosfatado', '0-18-0', 'Fonte de fósforo', 1),
('Cloreto de Potássio Granulado', 'Potássico', '0-0-60', 'Potássio em forma granulada', 1);

-- Pragas de Milho
INSERT INTO pragas (nome, cultura, nivel_risco, descricao, ativa) VALUES
('Lagarta-do-cartucho', 'Milho', 'alto', 'Praga polífaga que ataca folhas e espigas', 1),
('Broca-do-colmo', 'Milho', 'médio', 'Inseto que danifica o colmo interno', 1),
('Lagarta-militar', 'Milho', 'médio', 'Lagarta que corta plântulas rentes ao solo', 1),
('Mosca-branca', 'Milho/Soja', 'baixo', 'Inseto sugador transmissor de viroses', 1),
('Ácaro-rajado', 'Milho/Soja', 'baixo', 'Ácaro que causa bronzeamento de folhas', 1);

-- Pragas de Soja
INSERT INTO pragas (nome, cultura, nivel_risco, descricao, ativa) VALUES
('Percevejo-marrom', 'Soja', 'alto', 'Praga chave da soja que causa redução de produção', 1),
('Lagarta-falsa-medideira', 'Soja', 'médio', 'Desfolhadora que reduz área foliar', 1),
('Vaquinha', 'Soja', 'baixo', 'Coleóptero que causa furos nas folhas', 1),
('Percevejo-verde', 'Soja', 'alto', 'Inseto sugador que reduz produção', 1),
('Mandarová-da-soja', 'Soja', 'médio', 'Lagarta que danifica vagens', 1);

-- ============================================================================
-- FIM DO SCRIPT
-- ============================================================================
