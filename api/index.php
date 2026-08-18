<?php
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost';
$db = 'agromonitor';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['sucesso' => false, 'erro' => 'Conexão falhou: ' . $e->getMessage()]));
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$endpoint = trim(str_replace('/agromonitor/api/index.php', '', $path), '/');

// ========== DASHBOARD ==========
if ($endpoint === 'dashboard' && $method === 'GET') {
    $stmt = $pdo->query("SELECT COUNT(*) as totalAreas, COALESCE(SUM(hectares), 0) as totalHectares FROM areas");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT COUNT(*) as criticosCriticos FROM monitoramentos WHERE nivel_controle = 'critico'");
    $criticos = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'sucesso' => true,
        'dados' => [
            'totalAreas' => $result['totalAreas'],
            'totalHectares' => $result['totalHectares'],
            'criticosCriticos' => $criticos['criticosCriticos']
        ]
    ]);
}

// ========== ÁREAS ==========
else if ($endpoint === 'areas') {
    if ($method === 'GET') {
        $safra_id = $_GET['safra_id'] ?? null;
        $sql = "SELECT * FROM areas";
        if ($safra_id) {
            $sql .= " WHERE safra_id = :safra_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':safra_id' => $safra_id]);
        } else {
            $stmt = $pdo->query($sql);
        }
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['sucesso' => true, 'dados' => $areas]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // ✅ safra_id AGORA É OPCIONAL
        $safra_id = $data['safra_id'] ?? null;
        $nome = $data['nome'] ?? null;
        $nome_cliente = $data['nome_cliente'] ?? null;
        $cultura = $data['cultura'] ?? 'milho';
        $hectares = $data['hectares'] ?? 0;

        if (!$nome || !$nome_cliente) {
            echo json_encode(['sucesso' => false, 'erro' => 'Nome e Cliente são obrigatórios']);
            exit;
        }

        $sql = "INSERT INTO areas (safra_id, nome, nome_cliente, cultura, hectares, qtd_pontos) 
                VALUES (:safra_id, :nome, :nome_cliente, :cultura, :hectares, :qtd_pontos)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':safra_id' => $safra_id,
            ':nome' => $nome,
            ':nome_cliente' => $nome_cliente,
            ':cultura' => $cultura,
            ':hectares' => $hectares,
            ':qtd_pontos' => $data['qtd_pontos'] ?? 5
        ]);

        if ($result) {
            echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['sucesso' => false, 'erro' => 'Erro ao criar área']);
        }
    }
    else if ($method === 'DELETE') {
        $id = (int)str_replace('/areareas/', '', $endpoint);
        $pattern = '/areas\/(\d+)/';
        if (preg_match($pattern, $_SERVER['REQUEST_URI'], $matches)) {
            $id = $matches[1];
            $stmt = $pdo->prepare("DELETE FROM areas WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['sucesso' => true]);
        }
    }
}

// ========== CULTURAS ==========
else if ($endpoint === 'culturas') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM culturas WHERE ativa = 1");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO culturas (nome, descricao, ativa) VALUES (:nome, :desc, 1)");
        $result = $stmt->execute([':nome' => $data['nome'], ':desc' => $data['descricao'] ?? '']);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

// ========== VARIEDADES ==========
else if ($endpoint === 'variedades') {
    if ($method === 'GET') {
        $cultura_id = $_GET['cultura_id'] ?? null;
        if ($cultura_id) {
            $stmt = $pdo->prepare("SELECT * FROM variedades WHERE cultura_id = :cultura_id AND ativa = 1");
            $stmt->execute([':cultura_id' => $cultura_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM variedades WHERE ativa = 1");
        }
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO variedades (cultura_id, nome, descricao, ativa) VALUES (:cultura_id, :nome, :desc, 1)");
        $result = $stmt->execute([
            ':cultura_id' => $data['cultura_id'],
            ':nome' => $data['nome'],
            ':desc' => $data['descricao'] ?? ''
        ]);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

// ========== ADUBOS ==========
else if ($endpoint === 'adubos') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM adubos WHERE ativa = 1");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO adubos (nome, tipo, npk, descricao, ativa) VALUES (:nome, :tipo, :npk, :desc, 1)");
        $result = $stmt->execute([
            ':nome' => $data['nome'],
            ':tipo' => $data['tipo'] ?? '',
            ':npk' => $data['npk'] ?? '',
            ':desc' => $data['descricao'] ?? ''
        ]);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

// ========== PRAGAS ==========
else if ($endpoint === 'pragas') {
    if ($method === 'GET') {
        $cultura = $_GET['cultura'] ?? null;
        if ($cultura) {
            $stmt = $pdo->prepare("SELECT * FROM pragas WHERE cultura = :cultura AND ativa = 1");
            $stmt->execute([':cultura' => $cultura]);
        } else {
            $stmt = $pdo->query("SELECT * FROM pragas WHERE ativa = 1");
        }
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO pragas (nome, cultura, nivel_risco, descricao, ativa) VALUES (:nome, :cultura, :risco, :desc, 1)");
        $result = $stmt->execute([
            ':nome' => $data['nome'],
            ':cultura' => $data['cultura'] ?? '',
            ':risco' => $data['nivel_risco'] ?? 'medio',
            ':desc' => $data['descricao'] ?? ''
        ]);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

// ========== PLANTIOS ==========
else if ($endpoint === 'plantios') {
    if ($method === 'GET') {
        $area_id = $_GET['area_id'] ?? null;
        if ($area_id) {
            $stmt = $pdo->prepare("SELECT * FROM plantios WHERE area_id = :area_id");
            $stmt->execute([':area_id' => $area_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM plantios");
        }
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO plantios (area_id, talhao_id, data_plantio, cultura, variedade_id, adubo_id, adubo_qtd_kg) 
                              VALUES (:area_id, :talhao_id, :data, :cultura, :variedade_id, :adubo_id, :qtd)");
        $result = $stmt->execute([
            ':area_id' => $data['area_id'],
            ':talhao_id' => $data['talhao_id'] ?? null,
            ':data' => $data['data_plantio'],
            ':cultura' => $data['cultura'],
            ':variedade_id' => $data['variedade_id'] ?? null,
            ':adubo_id' => $data['adubo_id'] ?? null,
            ':qtd' => $data['adubo_qtd_kg'] ?? null
        ]);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

// ========== TALHÕES ==========
else if ($endpoint === 'talhoes') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM talhoes");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO talhoes (area_id, nome, variedade, tipo, hectares) 
                              VALUES (:area_id, :nome, :variedade, :tipo, :hectares)");
        $result = $stmt->execute([
            ':area_id' => $data['area_id'],
            ':nome' => $data['nome'],
            ':variedade' => $data['variedade'] ?? '',
            ':tipo' => $data['tipo'] ?? 'producao',
            ':hectares' => $data['hectares'] ?? null
        ]);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

// ========== PONTOS ==========
else if ($endpoint === 'pontos') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM pontos_monitoramento");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO pontos_monitoramento (area_id, talhao_id, numero_ponto, latitude, longitude, data_registro) 
                              VALUES (:area_id, :talhao_id, :numero, :lat, :lng, NOW())");
        $result = $stmt->execute([
            ':area_id' => $data['area_id'],
            ':talhao_id' => $data['talhao_id'] ?? null,
            ':numero' => $data['numero_ponto'],
            ':lat' => $data['latitude'] ?? null,
            ':lng' => $data['longitude'] ?? null
        ]);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

// ========== MONITORAMENTOS ==========
else if ($endpoint === 'monitoramentos') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM monitoramentos");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO monitoramentos (ponto_id, data_monitoramento, cultura, milho_plantas_avaliadas, milho_plantas_praga, soja_pragas_encontradas, soja_metros_lineares, tipo_praga) 
                              VALUES (:ponto_id, :data, :cultura, :milho_aval, :milho_praga, :soja_pragas, :soja_metros, :tipo_praga)");
        $result = $stmt->execute([
            ':ponto_id' => $data['ponto_id'],
            ':data' => $data['data_monitoramento'],
            ':cultura' => $data['cultura'],
            ':milho_aval' => $data['milho_plantas_avaliadas'] ?? 20,
            ':milho_praga' => $data['milho_plantas_praga'] ?? 0,
            ':soja_pragas' => $data['soja_pragas_encontradas'] ?? 0,
            ':soja_metros' => $data['soja_metros_lineares'] ?? 2.0,
            ':tipo_praga' => $data['tipo_praga'] ?? ''
        ]);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

// ========== OCORRÊNCIAS ==========
else if ($endpoint === 'ocorrencias') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM ocorrencias");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO ocorrencias (ponto_id, monitoramento_id, data_ocorrencia, tipo_praga, quantidade) 
                              VALUES (:ponto_id, :mon_id, :data, :praga, :qtd)");
        $result = $stmt->execute([
            ':ponto_id' => $data['ponto_id'],
            ':mon_id' => $data['monitoramento_id'] ?? null,
            ':data' => $data['data_ocorrencia'],
            ':praga' => $data['tipo_praga'],
            ':qtd' => $data['quantidade'] ?? 0
        ]);
        echo json_encode(['sucesso' => $result, 'id' => $pdo->lastInsertId()]);
    }
}

else {
    echo json_encode(['sucesso' => false, 'erro' => 'Endpoint não encontrado']);
}
