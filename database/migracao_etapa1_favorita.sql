-- Etapa 1 — favoritar fotos (rodar uma vez em bancos já existentes)
ALTER TABLE monitoramento_fotos
  ADD COLUMN favorita TINYINT(1) NOT NULL DEFAULT 0 AFTER descricao;
