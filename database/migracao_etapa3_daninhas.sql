-- Etapa 3 — cadastra plantas daninhas comuns no catálogo (rodar uma vez)
INSERT IGNORE INTO pragas (nome, tipo, cultura_id, nivel_risco, descricao, ativa) VALUES
('Buva', 'planta_daninha', NULL, 'alto', 'Resistente a glifosato, difícil controle', 1),
('Capim-amargoso', 'planta_daninha', NULL, 'alto', 'Resistente a glifosato, forma touceiras', 1),
('Pé-de-galinha', 'planta_daninha', NULL, 'medio', 'Gramínea comum em bordaduras', 1),
('Caruru', 'planta_daninha', NULL, 'medio', 'Folha larga de crescimento rápido', 1),
('Corda-de-viola', 'planta_daninha', NULL, 'medio', 'Trepadeira que dificulta a colheita', 1),
('Trapoeraba', 'planta_daninha', NULL, 'medio', 'Tolerante a glifosato', 1),
('Tiguera (milho voluntário)', 'planta_daninha', NULL, 'medio', 'Milho da safra anterior que germina na soja', 1);
