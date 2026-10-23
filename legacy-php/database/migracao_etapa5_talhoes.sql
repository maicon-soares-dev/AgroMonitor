-- Etapa 5 — talhões com contorno no mapa + variedade por talhão em cada plantio
-- Rodar uma vez no phpMyAdmin (banco agromonitor). Pode rodar de novo sem erro.

ALTER TABLE talhoes
  ADD COLUMN IF NOT EXISTS geometria LONGTEXT NULL AFTER cor_hex;

CREATE TABLE IF NOT EXISTS plantio_talhoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plantio_id INT NOT NULL,
  talhao_id INT NOT NULL,
  variedade_id INT NULL,
  FOREIGN KEY (plantio_id) REFERENCES plantios(id) ON DELETE CASCADE,
  FOREIGN KEY (talhao_id) REFERENCES talhoes(id) ON DELETE CASCADE,
  FOREIGN KEY (variedade_id) REFERENCES variedades(id) ON DELETE SET NULL,
  UNIQUE KEY uk_plantio_talhao (plantio_id, talhao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
