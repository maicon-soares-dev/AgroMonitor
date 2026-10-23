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

