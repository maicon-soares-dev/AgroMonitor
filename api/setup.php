<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db = 'agromonitor';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    // Conectar ao MySQL
    $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Criar banco de dados
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");

    echo json_encode(['status' => 'progress', 'message' => 'Banco de dados criado/verificado']);

    // TABELA: SAFRAS
    $pdo->exec("CREATE TABLE IF NOT EXISTS safras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(50) NOT NULL UNIQUE,
        ano_inicio INT NOT NULL,
        ano_fim INT NOT NULL,
        ativa TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ativa (ativa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: CULTURAS
    $pdo->exec("CREATE TABLE IF NOT EXISTS culturas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(50) NOT NULL UNIQUE,
        descricao TEXT,
        ativa TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ativa (ativa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: VARIEDADES
    $pdo->exec("CREATE TABLE IF NOT EXISTS variedades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cultura_id INT NOT NULL,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        ativa TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cultura_id) REFERENCES culturas(id) ON DELETE CASCADE,
        UNIQUE KEY uk_cultura_variedade (cultura_id, nome),
        INDEX idx_ativa (ativa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: ADUBOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS adubos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL UNIQUE,
        tipo VARCHAR(50),
        npk VARCHAR(20),
        descricao TEXT,
        ativa TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ativa (ativa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: PRAGAS
    $pdo->exec("CREATE TABLE IF NOT EXISTS pragas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL UNIQUE,
        cultura VARCHAR(50),
        nivel_risco VARCHAR(20) DEFAULT 'medio',
        descricao TEXT,
        ativa TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ativa (ativa),
        INDEX idx_cultura (cultura)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: AREAS - ✅ safra_id AGORA É OPCIONAL
    $pdo->exec("CREATE TABLE IF NOT EXISTS areas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        safra_id INT,
        nome VARCHAR(150) NOT NULL,
        nome_cliente VARCHAR(150) NOT NULL,
        cultura VARCHAR(20) NOT NULL,
        hectares DECIMAL(10,2) NOT NULL,
        alqueires DECIMAL(10,2) GENERATED ALWAYS AS (hectares / 2.42) STORED,
        qtd_pontos INT NOT NULL DEFAULT 5,
        observacoes TEXT,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (safra_id) REFERENCES safras(id) ON DELETE SET NULL,
        INDEX idx_safra (safra_id),
        INDEX idx_cultura (cultura),
        INDEX idx_cliente (nome_cliente)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: TALHÕES
    $pdo->exec("CREATE TABLE IF NOT EXISTS talhoes (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: PLANTIOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS plantios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        area_id INT NOT NULL,
        talhao_id INT,
        data_plantio DATE NOT NULL,
        cultura VARCHAR(30) NOT NULL,
        variedade_id INT,
        populacao_semente DECIMAL(8,2),
        adubo_tipo VARCHAR(100),
        adubo_id INT,
        adubo_qtd_kg DECIMAL(8,2),
        condicao_plantio VARCHAR(20) DEFAULT 'apos_chuva',
        condicao_solo VARCHAR(20) DEFAULT 'boa',
        observacoes TEXT,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
        FOREIGN KEY (talhao_id) REFERENCES talhoes(id) ON DELETE SET NULL,
        FOREIGN KEY (variedade_id) REFERENCES variedades(id) ON DELETE SET NULL,
        FOREIGN KEY (adubo_id) REFERENCES adubos(id) ON DELETE SET NULL,
        INDEX idx_area (area_id),
        INDEX idx_data (data_plantio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: PONTOS_MONITORAMENTO
    $pdo->exec("CREATE TABLE IF NOT EXISTS pontos_monitoramento (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: MONITORAMENTOS
    $pdo->exec("CREATE TABLE IF NOT EXISTS monitoramentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ponto_id INT NOT NULL,
        data_monitoramento DATE NOT NULL,
        cultura VARCHAR(20) NOT NULL,
        milho_plantas_avaliadas INT DEFAULT 20,
        milho_plantas_praga INT DEFAULT 0,
        milho_percentual DECIMAL(5,2),
        soja_pragas_encontradas INT DEFAULT 0,
        soja_metros_lineares DECIMAL(4,1) DEFAULT 2.0,
        soja_pragas_por_m2 DECIMAL(6,2),
        tipo_praga VARCHAR(100),
        resultado_final DECIMAL(8,2),
        unidade_resultado VARCHAR(20),
        nivel_controle VARCHAR(20),
        observacoes TEXT,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ponto_id) REFERENCES pontos_monitoramento(id) ON DELETE CASCADE,
        INDEX idx_ponto (ponto_id),
        INDEX idx_data (data_monitoramento),
        INDEX idx_nivel (nivel_controle)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // TABELA: OCORRÊNCIAS
    $pdo->exec("CREATE TABLE IF NOT EXISTS ocorrencias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ponto_id INT NOT NULL,
        monitoramento_id INT,
        data_ocorrencia DATE NOT NULL,
        tipo_praga VARCHAR(150) NOT NULL,
        quantidade INT NOT NULL DEFAULT 0,
        latitude DECIMAL(10,8),
        longitude DECIMAL(11,8),
        observacoes TEXT,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ponto_id) REFERENCES pontos_monitoramento(id) ON DELETE CASCADE,
        FOREIGN KEY (monitoramento_id) REFERENCES monitoramentos(id) ON DELETE SET NULL,
        INDEX idx_ponto (ponto_id),
        INDEX idx_data (data_ocorrencia),
        INDEX idx_praga (tipo_praga)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ✅ INSERIR DADOS INICIAIS
    $pdo->exec("INSERT IGNORE INTO culturas (nome, descricao, ativa) VALUES 
        ('Milho', 'Milho para grãos', 1),
        ('Soja', 'Soja para grãos', 1)");

    $pdo->exec("INSERT IGNORE INTO variedades (cultura_id, nome, descricao, ativa) VALUES 
        (1, 'AG1051', 'Ciclo precoce', 1),
        (1, 'AG7010', 'Ciclo normal', 1),
        (1, 'P30F35', 'Ciclo normal', 1),
        (1, '30F35', 'Ciclo super precoce', 1),
        (1, 'DKB230', 'Ciclo semi-tardio', 1),
        (2, 'M6211', 'Grupo 6.1', 1),
        (2, 'M7371', 'Grupo 7.3', 1),
        (2, 'BM3975', 'Grupo 3.9', 1),
        (2, 'NK7059', 'Grupo 7.0', 1),
        (2, 'NIDERA5909', 'Grupo 5.9', 1)");

    $pdo->exec("INSERT IGNORE INTO adubos (nome, tipo, npk, descricao, ativa) VALUES 
        ('NPK 20-20-20', 'Fertilizante Misto', '20-20-20', 'Adubo formulado balanceado', 1),
        ('Uréia', 'Nitrogenado', '46-0-0', 'Fonte de nitrogênio', 1),
        ('MAP', 'Fosfatado', '0-46-0', 'Monoamônio fosfato', 1),
        ('KCl', 'Potássio', '0-0-60', 'Cloreto de potássio', 1),
        ('Ureia + Polímero', 'Nitrogenado', '46-0-0', 'Ureia revestida', 1),
        ('Superfosfato Simples', 'Fosfatado', '0-18-0', 'Fertilizante tradicional', 1),
        ('Cloreto de Potássio Granulado', 'Potássio', '0-0-60', 'Formulação granular', 1)");

    $pdo->exec("INSERT IGNORE INTO pragas (nome, cultura, nivel_risco, descricao, ativa) VALUES 
        ('Lagarta-do-cartucho', 'Milho', 'alto', 'Praga importante', 1),
        ('Broca-do-colmo', 'Milho', 'medio', 'Perfura o colmo', 1),
        ('Largata-militar', 'Milho', 'medio', 'Alimenta-se das folhas', 1),
        ('Mosca-branca', 'Milho', 'baixo', 'Vetor de viroses', 1),
        ('Ácaro-rajado', 'Milho', 'baixo', 'Dano às folhas', 1),
        ('Percevejo-marrom', 'Soja', 'alto', 'Principal praga', 1),
        ('Mosca-branca', 'Soja', 'medio', 'Vetor de viroses', 1),
        ('Ácaro-rajado', 'Soja', 'medio', 'Reduz folhas', 1),
        ('Lagarta-falsa-medideira', 'Soja', 'alto', 'Desfolhadora', 1),
        ('Vaquinha', 'Soja', 'medio', 'Praga polífaga', 1)");

    echo json_encode(['sucesso' => true, 'mensagem' => '✅ Banco criado com sucesso! 11 tabelas criadas.']);

} catch (PDOException $e) {
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
