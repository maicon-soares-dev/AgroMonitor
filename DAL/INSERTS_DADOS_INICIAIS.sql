-- ============================================================
-- AGROMONITOR - INSERT DE DADOS INICIAIS
-- ============================================================
-- Execure APÓS criar as tabelas
-- ============================================================

USE agromonitor;

-- ============================================================
-- INSERIR SAFRAS
-- ============================================================
INSERT INTO safras (nome, ano_inicio, ano_fim, ativa) VALUES
('Safra 2025/2026', 2025, 2026, 1),
('Safra 2024/2025', 2024, 2025, 0);

-- ============================================================
-- INSERIR CULTURAS
-- ============================================================
INSERT INTO culturas (nome, descricao, ativa) VALUES
('Milho', 'Milho para grãos', 1),
('Soja', 'Soja para grãos', 1);

-- ============================================================
-- INSERIR VARIEDADES - MILHO
-- ============================================================
INSERT INTO variedades (cultura_id, nome, descricao, ativa) VALUES
(1, 'AG1051', 'Ciclo precoce, alto potencial produtivo', 1),
(1, 'AG7010', 'Ciclo normal, resistência a doenças', 1),
(1, 'P30F35', 'Ciclo normal, excelente sanidade', 1),
(1, '30F35', 'Ciclo super precoce', 1),
(1, 'DKB230', 'Ciclo semi-tardio, alto vigor', 1);

-- ============================================================
-- INSERIR VARIEDADES - SOJA
-- ============================================================
INSERT INTO variedades (cultura_id, nome, descricao, ativa) VALUES
(2, 'M6211', 'Grupo 6.1, ciclo médio, alta produtividade', 1),
(2, 'M7371', 'Grupo 7.3, ciclo tardio, excelente grão', 1),
(2, 'BM3975', 'Grupo 3.9, ciclo precoce, resistente', 1),
(2, 'NK7059', 'Grupo 7.0, ciclo tardio, grande grão', 1),
(2, 'NIDERA5909', 'Grupo 5.9, ciclo médio, versátil', 1);

-- ============================================================
-- INSERIR ADUBOS
-- ============================================================
INSERT INTO adubos (nome, tipo, npk, descricao, ativa) VALUES
('NPK 20-20-20', 'Fertilizante Misto', '20-20-20', 'Adubo formulado balanceado', 1),
('Uréia', 'Nitrogenado', '46-0-0', 'Fonte de nitrogênio altamente solúvel', 1),
('MAP', 'Fosfatado', '0-46-0', 'Monoamônio fosfato, alta disponibilidade', 1),
('KCl', 'Potássio', '0-0-60', 'Cloreto de potássio, fonte potássica', 1),
('Ureia + Polímero', 'Nitrogenado', '46-0-0', 'Ureia com revestimento polimérico', 1),
('Superfosfato Simples', 'Fosfatado', '0-18-0', 'Fertilizante tradicional', 1),
('Cloreto de Potássio Granulado', 'Potássio', '0-0-60', 'Formulação granular', 1);

-- ============================================================
-- INSERIR PRAGAS - MILHO
-- ============================================================
INSERT INTO pragas (nome, cultura, nivel_risco, descricao, ativa) VALUES
('Lagarta-do-cartucho', 'Milho', 'alto', 'Praga importante que causa dano ao cartucho', 1),
('Broca-do-colmo', 'Milho', 'medio', 'Inseto que perfura o colmo reduzindo a resistência', 1),
('Largata-militar', 'Milho', 'medio', 'Lagarta que se alimenta das folhas', 1),
('Mosca-branca', 'Milho', 'baixo', 'Vetor de viroses, dano secundário', 1),
('Ácaro-rajado', 'Milho', 'baixo', 'Causa folhas com aspecto acinzentado', 1);

-- ============================================================
-- INSERIR PRAGAS - SOJA
-- ============================================================
INSERT INTO pragas (nome, cultura, nivel_risco, descricao, ativa) VALUES
('Percevejo-marrom', 'Soja', 'alto', 'Principal praga da soja, alimenta-se de grãos', 1),
('Mosca-branca', 'Soja', 'medio', 'Vetor de viroses em soja', 1),
('Ácaro-rajado', 'Soja', 'medio', 'Reduz a área foliar disponível', 1),
('Lagarta-falsa-medideira', 'Soja', 'alto', 'Desfolhadora importante em soja', 1),
('Vaquinha', 'Soja', 'medio', 'Praga polífaga que causa danos variáveis', 1);

-- ============================================================
-- VERIFICAÇÃO
-- ============================================================
SELECT COUNT(*) AS 'Total Safras' FROM safras;
SELECT COUNT(*) AS 'Total Culturas' FROM culturas;
SELECT COUNT(*) AS 'Total Variedades' FROM variedades;
SELECT COUNT(*) AS 'Total Adubos' FROM adubos;
SELECT COUNT(*) AS 'Total Pragas' FROM pragas;

