<?php
// ============================================================
//  AgroMonitor SafraFort — API REST
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/validators.php';

$pdo = getDB();

$method = $_SERVER['REQUEST_METHOD'];

// Extrai o endpoint de forma dinâmica (não depende do caminho fixo /agromonitor/...)
if (!empty($_SERVER['PATH_INFO'])) {
    // Caminho padrão: o próprio PHP já separa o que vem depois de index.php
    $endpoint = trim($_SERVER['PATH_INFO'], '/');
} else {
    // Fallback para servidores que não populam PATH_INFO
    $scriptName = $_SERVER['SCRIPT_NAME']; // ex: /agromonitor/api/index.php
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $endpoint = (strpos($path, $scriptName) === 0)
        ? trim(substr($path, strlen($scriptName)), '/')
        : trim($path, '/');
}
$partes = explode('/', $endpoint);
$recurso = $partes[0] ?? '';
$id = isset($partes[1]) && ctype_digit($partes[1]) ? (int)$partes[1] : null;

function erro($msg, $codigo = 400) {
    http_response_code($codigo);
    echo json_encode(['sucesso' => false, 'erro' => $msg]);
    exit;
}

function corpo() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

// Índice de uma praga no dia (Relatórios 1 e 2)
// Milho: plantas com a praga ÷ plantas avaliadas × 100 | Soja: média por ponto de pano (metros / 2)
function indicePraga($total, $cultura, $plantasAvaliadas, $divisorSoja) {
    if ($total === null) return [null, null, null];
    if ($cultura === 'milho' && $plantasAvaliadas > 0) {
        $i = round($total / $plantasAvaliadas * 100, 1);
        return [$i, '%', $i > 10 ? 'critico' : 'normal'];
    }
    if ($cultura === 'soja' && $divisorSoja > 0) {
        $i = round($total / $divisorSoja, 2);
        return [$i, '/m²', $i >= 3.0 ? 'critico' : 'normal'];
    }
    return [null, null, null];
}

// Nome da(s) variedade(s) de um plantio: a variedade única do plantio ou,
// quando ele foi dividido por talhões, as variedades dos talhões (ex: "NS71, P3322PWU")
function sqlVariedadeNome($aliasPlantio, $aliasVariedade = 'v') {
    return "COALESCE($aliasVariedade.nome, (SELECT GROUP_CONCAT(DISTINCT vt.nome ORDER BY vt.nome SEPARATOR ', ')
              FROM plantio_talhoes ptv JOIN variedades vt ON vt.id = ptv.variedade_id
              WHERE ptv.plantio_id = $aliasPlantio.id))";
}

// Insere um talhão (usado no cadastro da área e no editor do mapa)
function inserirTalhao($pdo, $areaId, $t) {
    $stmt = $pdo->prepare("INSERT INTO talhoes (area_id, nome, tipo, hectares, cor_hex, geometria)
                           VALUES (:area, :nome, 'producao', :ha, :cor, :geo)");
    $stmt->execute([
        ':area' => $areaId,
        ':nome' => trim($t['nome'] ?? '') !== '' ? trim($t['nome']) : 'Talhão',
        ':ha'   => $t['hectares'] ?? null,
        ':cor'  => $t['cor_hex'] ?? '#4caf50',
        ':geo'  => isset($t['geometria']) ? json_encode($t['geometria']) : null
    ]);
    return $pdo->lastInsertId();
}

// Grava a variedade de cada talhão num plantio (substitui a lista inteira)
function salvarTalhoesPlantio($pdo, $plantioId, $talhoes) {
    $pdo->prepare("DELETE FROM plantio_talhoes WHERE plantio_id = :id")->execute([':id' => $plantioId]);
    if (!is_array($talhoes)) return;
    $ins = $pdo->prepare("INSERT INTO plantio_talhoes (plantio_id, talhao_id, variedade_id) VALUES (:pl, :t, :v)");
    foreach ($talhoes as $t) {
        if (empty($t['talhao_id'])) continue;
        $ins->execute([':pl' => $plantioId, ':t' => $t['talhao_id'], ':v' => ($t['variedade_id'] ?? '') === '' ? null : $t['variedade_id']]);
    }
}

// Talhões de um plantio com contorno e variedade (relatórios)
function talhoesDoPlantio($pdo, $plantioId) {
    $stmt = $pdo->prepare("SELECT t.id, t.nome, t.cor_hex, t.hectares, t.geometria, v.nome AS variedade_nome
                           FROM plantio_talhoes pt
                           JOIN talhoes t ON t.id = pt.talhao_id
                           LEFT JOIN variedades v ON v.id = pt.variedade_id
                           WHERE pt.plantio_id = :pl ORDER BY t.nome, t.id");
    $stmt->execute([':pl' => $plantioId]);
    return $stmt->fetchAll();
}

// Resumo dos monitoramentos de um plantio (Relatórios 2 e 3):
// índice das pragas por dia + consolidado de ocorrências da safra
function resumoMonitoramentosPlantio($pdo, $plantioId, $cultura) {
    $stmt = $pdo->prepare("SELECT m.*, p.numero_ponto FROM monitoramentos m
                           JOIN pontos_monitoramento p ON p.id = m.ponto_id
                           WHERE m.plantio_id = :pl ORDER BY m.data_monitoramento, p.numero_ponto");
    $stmt->execute([':pl' => $plantioId]);
    $mons = $stmt->fetchAll();

    $itensPorMon = [];
    if ($mons) {
        $ids = array_column($mons, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM monitoramento_itens WHERE monitoramento_id IN ($ph)");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $it) $itensPorMon[$it['monitoramento_id']][] = $it;
    }

    // --- Agrupa os pontos por dia ---
    $dias = [];
    foreach ($mons as $m) {
        $d = $m['data_monitoramento'];
        if (!isset($dias[$d])) $dias[$d] = ['pontos' => 0, 'plantas' => 0, 'divisor' => 0, 'estadios' => [], 'itens' => []];
        $dias[$d]['pontos']++;
        $dias[$d]['plantas'] += (int)($m['milho_plantas_avaliadas'] ?? 0);
        $dias[$d]['divisor'] += ((float)($m['soja_metros_lineares'] ?: 2)) / 2;
        $e = strtoupper(trim($m['estagio_fenologico'] ?? ''));
        if ($e !== '' && !in_array($e, $dias[$d]['estadios'])) $dias[$d]['estadios'][] = $e;
        foreach ($itensPorMon[$m['id']] ?? [] as $it) {
            $chave = $it['tipo'] . '|' . strtolower(trim($it['nome']));
            if (!isset($dias[$d]['itens'][$chave])) $dias[$d]['itens'][$chave] = ['tipo' => $it['tipo'], 'nome' => trim($it['nome']), 'total' => null];
            if ($it['quantidade'] !== null) $dias[$d]['itens'][$chave]['total'] = ($dias[$d]['itens'][$chave]['total'] ?? 0) + (int)$it['quantidade'];
        }
    }

    // --- Índice de cada praga por dia + consolidado da safra ---
    $diasOut = [];
    $ocorrencias = [];
    foreach ($dias as $data => $dia) {
        $pragasDia = [];
        foreach ($dia['itens'] as $chave => $it) {
            [$indice, $unidade] = $it['tipo'] === 'praga' && $it['total'] > 0
                ? indicePraga($it['total'], $cultura, $dia['plantas'], $dia['divisor'])
                : [null, null];
            if ($indice !== null) $pragasDia[$it['nome']] = $indice;

            if (!isset($ocorrencias[$chave])) {
                $ocorrencias[$chave] = ['tipo' => $it['tipo'], 'nome' => $it['nome'], 'dias_com' => 0,
                                        'pico_indice' => null, 'pico_data' => null, 'unidade' => null,
                                        'primeira_data' => $data, 'ultima_data' => $data];
            }
            $o = &$ocorrencias[$chave];
            $o['dias_com']++;
            $o['ultima_data'] = $data;
            if ($indice !== null && ($o['pico_indice'] === null || $indice > $o['pico_indice'])) {
                $o['pico_indice'] = $indice; $o['pico_data'] = $data; $o['unidade'] = $unidade;
            }
            unset($o);
        }
        $diasOut[] = ['data' => $data, 'qtd_pontos' => $dia['pontos'], 'estadios' => $dia['estadios'], 'pragas' => $pragasDia];
    }
    $ocorrencias = array_values($ocorrencias);
    usort($ocorrencias, function ($a, $b) {
        return [$a['tipo'] !== 'praga', -($a['pico_indice'] ?? -1), -$a['dias_com']]
           <=> [$b['tipo'] !== 'praga', -($b['pico_indice'] ?? -1), -$b['dias_com']];
    });
    return [$diasOut, $ocorrencias, count($mons)];
}

// Plantios (com colheita, aplicações e resumo das ocorrências) — Relatórios 3 e 4
// $condicao filtra por área ('pl.area_id = :filtro') ou por cliente ('a.cliente_id = :filtro')
function plantiosComResumo($pdo, $condicao, $valor) {
    $stmt = $pdo->prepare("SELECT pl.id AS plantio_id, pl.data_plantio, pl.area_id, a.nome AS area_nome, a.hectares,
                                  c.nome AS cultura_nome, s.nome AS safra_nome, s.ano_inicio, s.mes_inicio,
                                  " . sqlVariedadeNome('pl') . " AS variedade_nome, co.data_colheita, co.produtividade_sc_ha, co.umidade_pct,
                                  (SELECT COUNT(*) FROM aplicacoes ap WHERE ap.plantio_id = pl.id) AS qtd_aplicacoes
                           FROM plantios pl
                           JOIN areas a    ON a.id = pl.area_id
                           JOIN culturas c ON c.id = pl.cultura_id
                           JOIN safras s   ON s.id = pl.safra_id
                           LEFT JOIN variedades v ON v.id = pl.variedade_id
                           LEFT JOIN colheitas co ON co.plantio_id = pl.id
                           WHERE $condicao
                           ORDER BY pl.data_plantio, a.nome");
    $stmt->execute([':filtro' => $valor]);
    $plantios = $stmt->fetchAll();

    foreach ($plantios as &$p) {
        $cultura = strtolower(trim($p['cultura_nome']));
        [$diasOut, $ocorrencias, $totalPontos] = resumoMonitoramentosPlantio($pdo, $p['plantio_id'], $cultura);
        $p['dias_monitorados'] = count($diasOut);
        $p['pontos_avaliados'] = $totalPontos;
        $p['qtd_pragas']   = count(array_filter($ocorrencias, function ($o) { return $o['tipo'] === 'praga'; }));
        $p['qtd_doencas']  = count(array_filter($ocorrencias, function ($o) { return $o['tipo'] === 'doenca'; }));
        $p['qtd_daninhas'] = count(array_filter($ocorrencias, function ($o) { return $o['tipo'] === 'planta_daninha'; }));
        // Pragas que passaram do nível de controle em algum monitoramento
        $p['pragas_criticas'] = count(array_filter($ocorrencias, function ($o) use ($cultura) {
            return $o['pico_indice'] !== null
                && (($cultura === 'milho' && $o['pico_indice'] > 10) || ($cultura === 'soja' && $o['pico_indice'] >= 3.0));
        }));
        // As 3 principais pragas (maior pico)
        $p['principais_pragas'] = array_slice(array_values(array_filter($ocorrencias, function ($o) {
            return $o['tipo'] === 'praga' && $o['pico_indice'] !== null;
        })), 0, 3);
        // Tudo o que apareceu no ciclo (tipo, nome, pico) — usado no consolidado do cliente
        $p['ocorrencias'] = array_map(function ($o) {
            return ['tipo' => $o['tipo'], 'nome' => $o['nome'], 'pico_indice' => $o['pico_indice'], 'unidade' => $o['unidade']];
        }, $ocorrencias);
    }
    unset($p);
    return $plantios;
}

// ========== DASHBOARD ==========
try {

if ($recurso === 'dashboard' && $method === 'GET') {
    $stmt = $pdo->query("SELECT COUNT(*) as totalAreas, COALESCE(SUM(hectares), 0) as totalHectares FROM areas");
    $result = $stmt->fetch();

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM plantios");
    $plantios = $stmt->fetch();

    // v2: não existe mais tabela ocorrencias — "ocorrências" agora é a
    // contagem de itens de monitoramento (pragas/doenças/clima encontrados)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM monitoramento_itens");
    $ocorrenciasTotal = $stmt->fetch();

    $stmt = $pdo->query("SELECT COUNT(*) as criticos FROM monitoramentos WHERE nivel_controle = 'critico'");
    $criticos = $stmt->fetch();

    // Distribuição de hectares por cultura (para o gráfico de pizza)
    // Área por cliente, em vez de por cultura: com milho/soja plantados em
    // sequência (não simultâneos), a cultura "atual" de todas as áreas tende
    // a ser a mesma na maior parte do tempo (pouco dinâmico pro gráfico).
    // Cliente é um agrupamento direto e mais estável.
    $stmt = $pdo->query("SELECT c.nome AS cliente, COALESCE(SUM(a.hectares),0) as hectares, COUNT(*) as qtd
                          FROM areas a
                          JOIN clientes c ON c.id = a.cliente_id
                          GROUP BY c.id, c.nome ORDER BY hectares DESC");
    $porCliente = $stmt->fetchAll();

    // Plantios por mês — período selecionável (3, 6, 12 meses, ou "todos")
    $periodo = $_GET['periodo'] ?? '6';
    if ($periodo === 'todos') {
        $stmt = $pdo->query("SELECT DATE_FORMAT(data_plantio, '%Y-%m') as mes, COUNT(*) as qtd
                              FROM plantios GROUP BY mes ORDER BY mes");
    } else {
        $meses = in_array($periodo, ['3', '6', '12']) ? (int)$periodo : 6;
        $stmt = $pdo->prepare("SELECT DATE_FORMAT(data_plantio, '%Y-%m') as mes, COUNT(*) as qtd
                                FROM plantios
                                WHERE data_plantio >= DATE_SUB(CURDATE(), INTERVAL :meses MONTH)
                                GROUP BY mes ORDER BY mes");
        $stmt->bindValue(':meses', $meses, PDO::PARAM_INT);
        $stmt->execute();
    }
    $plantiosPorMes = $stmt->fetchAll();

    // Top 5 pragas/doenças/condições mais registradas (agora só monitoramento_itens,
    // já que tipo_praga solto em monitoramentos e a tabela ocorrencias saíram no v2)
    $stmt = $pdo->query("SELECT nome AS tipo_praga, COUNT(*) as qtd
                          FROM monitoramento_itens
                          WHERE nome IS NOT NULL AND nome <> ''
                          GROUP BY nome ORDER BY qtd DESC LIMIT 5");
    $topPragas = $stmt->fetchAll();

    // Últimas 5 áreas cadastradas (cliente agora vem de JOIN, não é mais coluna solta)
    $stmt = $pdo->query("SELECT a.id, a.nome, c.nome AS nome_cliente, a.hectares, a.criado_em
                          FROM areas a JOIN clientes c ON c.id = a.cliente_id
                          ORDER BY a.criado_em DESC LIMIT 5");
    $areasRecentes = $stmt->fetchAll();

    echo json_encode([
        'sucesso' => true,
        'dados' => [
            'totalAreas'       => (int)$result['totalAreas'],
            'totalHectares'    => (float)$result['totalHectares'],
            'totalPlantios'    => (int)$plantios['total'],
            'totalOcorrencias' => (int)$ocorrenciasTotal['total'],
            'criticosCriticos' => (int)$criticos['criticos'],
            'porCliente'       => $porCliente,
            'plantiosPorMes'   => $plantiosPorMes,
            'topPragas'        => $topPragas,
            'areasRecentes'    => $areasRecentes
        ]
    ]);
}

// ========== ÁREAS ==========
else if ($recurso === 'areas') {

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare("SELECT a.*, c.nome AS nome_cliente,
                                       (SELECT COUNT(*) FROM talhoes t WHERE t.area_id = a.id) AS qtd_talhoes
                                FROM areas a JOIN clientes c ON c.id = a.cliente_id
                                WHERE a.id = :id");
        $stmt->execute([':id' => $id]);
        $area = $stmt->fetch();
        if (!$area) erro('Área não encontrada', 404);
        echo json_encode(['sucesso' => true, 'dados' => $area]);
    }

    else if ($method === 'GET') {
        // v2: área não tem mais safra_id direto (isso agora é por plantio),
        // então o filtro por safra passou a ser "áreas que têm pelo menos
        // um plantio naquela safra"
        $safra_id = $_GET['safra_id'] ?? null;
        $cliente_id = $_GET['cliente_id'] ?? null;
        $sql = "SELECT a.*, c.nome AS nome_cliente, (SELECT COUNT(*) FROM talhoes t WHERE t.area_id = a.id) AS qtd_talhoes
                FROM areas a JOIN clientes c ON c.id = a.cliente_id";
        $condicoes = [];
        $params = [];
        if ($safra_id) {
            $sql .= " WHERE EXISTS (SELECT 1 FROM plantios p WHERE p.area_id = a.id AND p.safra_id = :safra_id)";
            $params[':safra_id'] = $safra_id;
        }
        if ($cliente_id) {
            $sql .= ($params ? ' AND' : ' WHERE') . " a.cliente_id = :cliente_id";
            $params[':cliente_id'] = $cliente_id;
        }
        $sql .= " ORDER BY a.criado_em DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }

    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarArea($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO areas (cliente_id, nome, hectares, qtd_pontos, observacoes, geometria)
                                VALUES (:cliente_id, :nome, :hectares, :qtd_pontos, :obs, :geometria)");
        $stmt->execute([
            ':cliente_id'   => $data['cliente_id'],
            ':nome'         => $data['nome'],
            ':hectares'     => $data['hectares'],
            ':qtd_pontos'   => $data['qtd_pontos'] ?? 5,
            ':obs'          => $data['observacoes'] ?? null,
            ':geometria'    => isset($data['geometria']) ? json_encode($data['geometria']) : null
        ]);
        $areaId = $pdo->lastInsertId();

        // Cria de verdade os pontos de monitoramento no banco (antes só existia o número, não os registros).
        // Se o frontend mandou coordenadas calculadas dentro do polígono, usa elas; senão fica sem lat/lng.
        $qtdPontos = (int)($data['qtd_pontos'] ?? 5);
        $pontosCoords = $data['pontos_coords'] ?? [];
        $insPonto = $pdo->prepare("INSERT INTO pontos_monitoramento (area_id, numero_ponto, latitude, longitude, data_registro)
                                    VALUES (:area_id, :numero, :lat, :lng, CURDATE())");
        for ($i = 1; $i <= $qtdPontos; $i++) {
            $coord = $pontosCoords[$i - 1] ?? null;
            $insPonto->execute([
                ':area_id' => $areaId,
                ':numero'  => $i,
                ':lat'     => $coord[0] ?? null,
                ':lng'     => $coord[1] ?? null
            ]);
        }

        // Talhões definidos já no cadastro (opcional)
        if (!empty($data['talhoes']) && is_array($data['talhoes'])) {
            foreach ($data['talhoes'] as $t) inserirTalhao($pdo, $areaId, $t);
        }

        echo json_encode(['sucesso' => true, 'id' => $areaId]);
    }

    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $erros = validarArea($data);
        if ($erros) erro(implode('; ', $erros));

        // Só sobrescreve a geometria se o frontend mandou uma nova; senão preserva a que já existe
        if (array_key_exists('geometria', $data)) {
            $geometria = $data['geometria'] !== null ? json_encode($data['geometria']) : null;
        } else {
            $stmtAtual = $pdo->prepare("SELECT geometria FROM areas WHERE id = :id");
            $stmtAtual->execute([':id' => $id]);
            $geometria = $stmtAtual->fetchColumn();
        }

        $stmt = $pdo->prepare("UPDATE areas SET cliente_id=:cliente_id, nome=:nome,
                                hectares=:hectares, qtd_pontos=:qtd_pontos, observacoes=:obs, geometria=:geometria
                                WHERE id=:id");
        $stmt->execute([
            ':cliente_id'   => $data['cliente_id'],
            ':nome'         => $data['nome'],
            ':hectares'     => $data['hectares'],
            ':qtd_pontos'   => $data['qtd_pontos'] ?? 5,
            ':obs'          => $data['observacoes'] ?? null,
            ':geometria'    => $geometria,
            ':id'           => $id
        ]);

        // Se a nova quantidade de pontos for maior que a atual, cria só os que faltam.
        // Nunca apaga pontos existentes aqui, pra não perder histórico de monitoramento (FK ON DELETE CASCADE).
        $qtdPontosNova = (int)($data['qtd_pontos'] ?? 5);
        $stmtCount = $pdo->prepare("SELECT COUNT(*), COALESCE(MAX(numero_ponto),0) FROM pontos_monitoramento WHERE area_id = :id");
        $stmtCount->execute([':id' => $id]);
        [$existentes, $maxNumero] = $stmtCount->fetch(PDO::FETCH_NUM);
        if ($qtdPontosNova > $existentes) {
            $insPonto = $pdo->prepare("INSERT INTO pontos_monitoramento (area_id, numero_ponto, data_registro) VALUES (:area_id, :numero, CURDATE())");
            for ($i = 1; $i <= ($qtdPontosNova - $existentes); $i++) {
                $insPonto->execute([':area_id' => $id, ':numero' => $maxNumero + $i]);
            }
        }

        echo json_encode(['sucesso' => true]);
    }

    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM areas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }

    else {
        erro('Requisição inválida para /areas', 405);
    }
}

// ========== CLIENTES ==========
else if ($recurso === 'clientes') {

    if ($method === 'GET' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $cliente = $stmt->fetch();
        if (!$cliente) erro('Cliente não encontrado', 404);
        echo json_encode(['sucesso' => true, 'dados' => $cliente]);
    }

    else if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM clientes WHERE ativo = 1 ORDER BY nome");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }

    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarCliente($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO clientes (nome, documento, telefone, email) VALUES (:nome, :doc, :tel, :email)");
        $stmt->execute([
            ':nome'  => $data['nome'],
            ':doc'   => $data['documento'] ?? null,
            ':tel'   => $data['telefone'] ?? null,
            ':email' => $data['email'] ?? null
        ]);
        echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }

    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $erros = validarCliente($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("UPDATE clientes SET nome=:nome, documento=:doc, telefone=:tel, email=:email WHERE id=:id");
        $stmt->execute([
            ':nome'  => $data['nome'],
            ':doc'   => $data['documento'] ?? null,
            ':tel'   => $data['telefone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':id'    => $id
        ]);
        echo json_encode(['sucesso' => true]);
    }

    else if ($method === 'DELETE' && $id) {
        // Soft delete (ativo=0) em vez de apagar de vez — evita quebrar áreas
        // que já referenciam esse cliente (FK é RESTRICT, não CASCADE)
        $stmt = $pdo->prepare("UPDATE clientes SET ativo = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }

    else {
        erro('Requisição inválida para /clientes', 405);
    }
}

// ========== CULTURAS ==========
else if ($recurso === 'culturas') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM culturas WHERE ativa = 1 ORDER BY nome");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarCultura($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO culturas (nome, descricao, ativa) VALUES (:nome, :desc, 1)");
        $stmt->execute([':nome' => $data['nome'], ':desc' => $data['descricao'] ?? '']);
        echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $erros = validarCultura($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("UPDATE culturas SET nome=:nome, descricao=:desc WHERE id=:id");
        $stmt->execute([':nome' => $data['nome'], ':desc' => $data['descricao'] ?? '', ':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM culturas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /culturas', 405);
    }
}

// ========== VARIEDADES ==========
else if ($recurso === 'variedades') {
    if ($method === 'GET') {
        $cultura_id = $_GET['cultura_id'] ?? null;
        if ($cultura_id) {
            $stmt = $pdo->prepare("SELECT * FROM variedades WHERE cultura_id = :cultura_id AND ativa = 1 ORDER BY nome");
            $stmt->execute([':cultura_id' => $cultura_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM variedades WHERE ativa = 1 ORDER BY nome");
        }
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarVariedade($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO variedades (cultura_id, nome, descricao, ativa) VALUES (:cultura_id, :nome, :desc, 1)");
        $stmt->execute([
            ':cultura_id' => $data['cultura_id'],
            ':nome'       => $data['nome'],
            ':desc'       => $data['descricao'] ?? ''
        ]);
        echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $erros = validarVariedade($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("UPDATE variedades SET cultura_id=:cultura_id, nome=:nome, descricao=:desc WHERE id=:id");
        $stmt->execute([
            ':cultura_id' => $data['cultura_id'],
            ':nome'       => $data['nome'],
            ':desc'       => $data['descricao'] ?? '',
            ':id'         => $id
        ]);
        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM variedades WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /variedades', 405);
    }
}

// ========== ADUBOS ==========
else if ($recurso === 'adubos') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM adubos WHERE ativa = 1 ORDER BY nome");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarAdubo($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO adubos (nome, tipo, npk, descricao, ativa) VALUES (:nome, :tipo, :npk, :desc, 1)");
        $stmt->execute([
            ':nome' => $data['nome'],
            ':tipo' => $data['tipo'] ?? '',
            ':npk'  => $data['npk'] ?? '',
            ':desc' => $data['descricao'] ?? ''
        ]);
        echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $erros = validarAdubo($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("UPDATE adubos SET nome=:nome, tipo=:tipo, npk=:npk, descricao=:desc WHERE id=:id");
        $stmt->execute([
            ':nome' => $data['nome'],
            ':tipo' => $data['tipo'] ?? '',
            ':npk'  => $data['npk'] ?? '',
            ':desc' => $data['descricao'] ?? '',
            ':id'   => $id
        ]);
        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM adubos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /adubos', 405);
    }
}

// ========== PRAGAS ==========
else if ($recurso === 'pragas') {
    if ($method === 'GET') {
        $cultura_id = $_GET['cultura_id'] ?? null;
        $tipo = $_GET['tipo'] ?? null; // 'praga', 'doenca' ou 'clima', opcional

        $condicoes = ['p.ativa = 1'];
        $params = [];
        // cultura_id NULL na praga = "aplica a qualquer cultura" (ex: eventos
        // climáticos), então esses sempre aparecem, além dos da cultura pedida
        if ($cultura_id) { $condicoes[] = '(p.cultura_id = :cultura_id OR p.cultura_id IS NULL)'; $params[':cultura_id'] = $cultura_id; }
        if ($tipo)       { $condicoes[] = 'p.tipo = :tipo'; $params[':tipo'] = $tipo; }

        $sql = "SELECT p.*, c.nome AS cultura_nome FROM pragas p LEFT JOIN culturas c ON c.id = p.cultura_id
                WHERE " . implode(' AND ', $condicoes) . " ORDER BY p.nome";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarPraga($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO pragas (nome, tipo, cultura_id, nivel_risco, descricao, ativa) VALUES (:nome, :tipo, :cultura_id, :risco, :desc, 1)");
        $stmt->execute([
            ':nome'       => $data['nome'],
            ':tipo'       => $data['tipo'] ?? 'praga',
            ':cultura_id' => $data['cultura_id'] ?? null,
            ':risco'      => $data['nivel_risco'] ?? 'medio',
            ':desc'       => $data['descricao'] ?? ''
        ]);
        echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $erros = validarPraga($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("UPDATE pragas SET nome=:nome, tipo=:tipo, cultura_id=:cultura_id, nivel_risco=:risco, descricao=:desc WHERE id=:id");
        $stmt->execute([
            ':nome'       => $data['nome'],
            ':tipo'       => $data['tipo'] ?? 'praga',
            ':cultura_id' => $data['cultura_id'] ?? null,
            ':risco'      => $data['nivel_risco'] ?? 'medio',
            ':desc'       => $data['descricao'] ?? '',
            ':id'         => $id
        ]);
        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM pragas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /pragas', 405);
    }
}

// ========== TALHÕES ==========
// Talhão = divisão física fixa da área (nome, cor, contorno). A variedade
// não fica aqui: muda a cada safra e vive em plantio_talhoes.
// GET ?area_id=&plantio_id= → com plantio_id, traz a variedade de cada talhão naquele plantio
else if ($recurso === 'talhoes') {
    if ($method === 'GET') {
        $area_id = $_GET['area_id'] ?? null;
        $plantio_id = $_GET['plantio_id'] ?? null;
        $sql = "SELECT t.*, pt.variedade_id AS plantio_variedade_id, v.nome AS plantio_variedade_nome,
                       (pt.id IS NOT NULL) AS no_plantio
                FROM talhoes t
                LEFT JOIN plantio_talhoes pt ON pt.talhao_id = t.id AND pt.plantio_id = :plantio
                LEFT JOIN variedades v ON v.id = pt.variedade_id";
        $params = [':plantio' => $plantio_id ?: 0];
        if ($area_id) { $sql .= " WHERE t.area_id = :area_id"; $params[':area_id'] = $area_id; }
        $sql .= " ORDER BY t.nome, t.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarTalhao($data);
        if ($erros) erro(implode('; ', $erros));
        echo json_encode(['sucesso' => true, 'id' => inserirTalhao($pdo, $data['area_id'], $data)]);
    }
    else if ($method === 'PUT' && $id) {
        // Atualiza só o que vier no corpo (nome, cor, contorno/hectares)
        $data = corpo();
        $campos = [];
        $params = [':id' => $id];
        if (array_key_exists('nome', $data)) {
            if (trim($data['nome']) === '') erro('Nome do talhão é obrigatório');
            $campos[] = 'nome = :nome'; $params[':nome'] = trim($data['nome']);
        }
        if (array_key_exists('cor_hex', $data)) { $campos[] = 'cor_hex = :cor'; $params[':cor'] = $data['cor_hex'] ?: '#4caf50'; }
        if (array_key_exists('hectares', $data)) { $campos[] = 'hectares = :ha'; $params[':ha'] = $data['hectares']; }
        if (array_key_exists('geometria', $data)) {
            $campos[] = 'geometria = :geo';
            $params[':geo'] = $data['geometria'] !== null ? json_encode($data['geometria']) : null;
        }
        if (!$campos) erro('Nada para atualizar');
        $stmt = $pdo->prepare("UPDATE talhoes SET " . implode(', ', $campos) . " WHERE id = :id");
        $stmt->execute($params);
        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        // plantio_talhoes cai junto (ON DELETE CASCADE)
        $stmt = $pdo->prepare("DELETE FROM talhoes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /talhoes', 405);
    }
}

// ========== PLANTIOS ==========
else if ($recurso === 'plantios') {
    if ($method === 'GET') {
        $area_id = $_GET['area_id'] ?? null;
        $safra_id = $_GET['safra_id'] ?? null;
        $sql = "SELECT p.*, c.nome AS cultura_nome, s.nome AS safra_nome, " . sqlVariedadeNome('p') . " AS variedade_nome, ad.nome AS adubo_nome
                FROM plantios p
                JOIN culturas c ON c.id = p.cultura_id
                JOIN safras s ON s.id = p.safra_id
                LEFT JOIN variedades v ON v.id = p.variedade_id
                LEFT JOIN adubos ad ON ad.id = p.adubo_id";
        $condicoes = [];
        $params = [];
        if ($area_id)  { $condicoes[] = 'p.area_id = :area_id'; $params[':area_id'] = $area_id; }
        if ($safra_id) { $condicoes[] = 'p.safra_id = :safra_id'; $params[':safra_id'] = $safra_id; }
        if ($condicoes) $sql .= " WHERE " . implode(' AND ', $condicoes);
        $sql .= " ORDER BY p.data_plantio DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $plantios = $stmt->fetchAll();

        // Variedade de cada talhão (quando o plantio foi dividido por talhões)
        if ($plantios) {
            $ids = array_column($plantios, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT pt.plantio_id, pt.talhao_id, pt.variedade_id FROM plantio_talhoes pt WHERE pt.plantio_id IN ($ph)");
            $stmt->execute($ids);
            $porPlantio = [];
            foreach ($stmt->fetchAll() as $pt) $porPlantio[$pt['plantio_id']][] = $pt;
            foreach ($plantios as &$pl) $pl['talhoes'] = $porPlantio[$pl['id']] ?? [];
            unset($pl);
        }
        echo json_encode(['sucesso' => true, 'dados' => $plantios]);
    }
    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarPlantio($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO plantios (area_id, talhao_id, safra_id, data_plantio, cultura_id, variedade_id, populacao_semente, adubo_id, adubo_qtd_kg, condicao_plantio, condicao_solo, observacoes)
                                VALUES (:area_id, :talhao_id, :safra_id, :data, :cultura_id, :variedade_id, :populacao, :adubo_id, :qtd, :cond_plantio, :cond_solo, :obs)");
        $stmt->execute([
            ':area_id'      => $data['area_id'],
            ':talhao_id'    => $data['talhao_id'] ?? null,
            ':safra_id'     => $data['safra_id'],
            ':data'         => $data['data_plantio'],
            ':cultura_id'   => $data['cultura_id'],
            ':variedade_id' => $data['variedade_id'] ?? null,
            ':populacao'    => $data['populacao_semente'] ?? null,
            ':adubo_id'     => $data['adubo_id'] ?? null,
            ':qtd'          => $data['adubo_qtd_kg'] ?? null,
            ':cond_plantio' => $data['condicao_plantio'] ?? 'apos_chuva',
            ':cond_solo'    => $data['condicao_solo'] ?? 'boa',
            ':obs'          => $data['observacoes'] ?? null
        ]);
        $plantioId = $pdo->lastInsertId();
        if (array_key_exists('talhoes', $data)) salvarTalhoesPlantio($pdo, $plantioId, $data['talhoes']);
        echo json_encode(['sucesso' => true, 'id' => $plantioId]);
    }
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $erros = validarPlantio($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("UPDATE plantios SET area_id=:area_id, talhao_id=:talhao_id, safra_id=:safra_id, data_plantio=:data,
                                cultura_id=:cultura_id, variedade_id=:variedade_id, populacao_semente=:populacao,
                                adubo_id=:adubo_id, adubo_qtd_kg=:qtd, condicao_plantio=:cond_plantio, condicao_solo=:cond_solo,
                                observacoes=:obs WHERE id=:id");
        $stmt->execute([
            ':area_id'      => $data['area_id'],
            ':talhao_id'    => $data['talhao_id'] ?? null,
            ':safra_id'     => $data['safra_id'],
            ':data'         => $data['data_plantio'],
            ':cultura_id'   => $data['cultura_id'],
            ':variedade_id' => $data['variedade_id'] ?? null,
            ':populacao'    => $data['populacao_semente'] ?? null,
            ':adubo_id'     => $data['adubo_id'] ?? null,
            ':qtd'          => $data['adubo_qtd_kg'] ?? null,
            ':cond_plantio' => $data['condicao_plantio'] ?? 'apos_chuva',
            ':cond_solo'    => $data['condicao_solo'] ?? 'boa',
            ':obs'          => $data['observacoes'] ?? null,
            ':id'           => $id
        ]);
        if (array_key_exists('talhoes', $data)) salvarTalhoesPlantio($pdo, $id, $data['talhoes']);
        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM plantios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /plantios', 405);
    }
}

// ========== PONTOS DE MONITORAMENTO ==========
else if ($recurso === 'pontos') {
    if ($method === 'GET') {
        $area_id = $_GET['area_id'] ?? null;
        if ($area_id) {
            $stmt = $pdo->prepare("SELECT * FROM pontos_monitoramento WHERE area_id = :area_id ORDER BY numero_ponto");
            $stmt->execute([':area_id' => $area_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM pontos_monitoramento");
        }
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarPonto($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO pontos_monitoramento (area_id, talhao_id, numero_ponto, latitude, longitude, data_registro, observacoes)
                                VALUES (:area_id, :talhao_id, :numero, :lat, :lng, CURDATE(), :obs)");
        $stmt->execute([
            ':area_id'   => $data['area_id'],
            ':talhao_id' => $data['talhao_id'] ?? null,
            ':numero'    => $data['numero_ponto'],
            ':lat'       => $data['latitude'] ?? null,
            ':lng'       => $data['longitude'] ?? null,
            ':obs'       => $data['observacoes'] ?? null
        ]);
        echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        // Edição de ponto é parcial: só atualiza o que veio no corpo (posição, número ou observação)
        $campos = [];
        $params = [':id' => $id];
        if (array_key_exists('latitude', $data))    { $campos[] = 'latitude=:lat';   $params[':lat'] = $data['latitude']; }
        if (array_key_exists('longitude', $data))   { $campos[] = 'longitude=:lng';  $params[':lng'] = $data['longitude']; }
        if (array_key_exists('numero_ponto', $data)){ $campos[] = 'numero_ponto=:numero'; $params[':numero'] = $data['numero_ponto']; }
        if (array_key_exists('observacoes', $data)) { $campos[] = 'observacoes=:obs'; $params[':obs'] = $data['observacoes']; }

        if (!$campos) erro('Nada para atualizar');

        $stmt = $pdo->prepare("UPDATE pontos_monitoramento SET " . implode(', ', $campos) . " WHERE id=:id");
        $stmt->execute($params);
        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        // Remove o ponto e tudo que dependia dele (monitoramentos/ocorrências daquele ponto — FK ON DELETE CASCADE)
        $stmt = $pdo->prepare("DELETE FROM pontos_monitoramento WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /pontos', 405);
    }
}

// ========== MONITORAMENTOS ==========
else if ($recurso === 'monitoramentos') {
    if ($method === 'GET') {
        $area_id = $_GET['area_id'] ?? null;
        $plantio_id = $_GET['plantio_id'] ?? null;
        $safra_id = $_GET['safra_id'] ?? null;
        // v2: cultura/safra não estão mais em monitoramentos — vêm do
        // plantio (join). Não tem mais adubo aqui (removido, ver aplicacoes).
        $baseSql = "SELECT m.*, p.area_id AS area_id, p.numero_ponto AS numero_ponto, a.nome AS area_nome,
                            pl.data_plantio, pl.safra_id, s.nome AS safra_nome, c.nome AS cultura_nome
                    FROM monitoramentos m
                    INNER JOIN pontos_monitoramento p ON p.id = m.ponto_id
                    INNER JOIN areas a ON a.id = p.area_id
                    INNER JOIN plantios pl ON pl.id = m.plantio_id
                    INNER JOIN safras s ON s.id = pl.safra_id
                    INNER JOIN culturas c ON c.id = pl.cultura_id";
        $condicoes = [];
        $params = [];
        if ($id)         { $condicoes[] = 'm.id = :id'; $params[':id'] = $id; }
        if ($area_id)    { $condicoes[] = 'p.area_id = :area_id'; $params[':area_id'] = $area_id; }
        if ($plantio_id) { $condicoes[] = 'm.plantio_id = :plantio_id'; $params[':plantio_id'] = $plantio_id; }
        if ($safra_id)   { $condicoes[] = 'pl.safra_id = :safra_id'; $params[':safra_id'] = $safra_id; }
        if ($condicoes) $baseSql .= " WHERE " . implode(' AND ', $condicoes);
        $baseSql .= " ORDER BY m.data_monitoramento DESC";
        $stmt = $pdo->prepare($baseSql);
        $stmt->execute($params);
        $monitoramentos = $stmt->fetchAll();

        // Busca os itens (pragas/doenças) de todos os monitoramentos de uma vez só
        if ($monitoramentos) {
            $ids = array_column($monitoramentos, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtItens = $pdo->prepare("SELECT * FROM monitoramento_itens WHERE monitoramento_id IN ($placeholders) ORDER BY id");
            $stmtItens->execute($ids);
            $itensPorMonitoramento = [];
            foreach ($stmtItens->fetchAll() as $item) {
                $itensPorMonitoramento[$item['monitoramento_id']][] = $item;
            }

            // Fotos anexadas — já monta a URL pública pronta pra usar num <img src>
            $stmtFotos = $pdo->prepare("SELECT * FROM monitoramento_fotos WHERE monitoramento_id IN ($placeholders) ORDER BY id");
            $stmtFotos->execute($ids);
            $fotosPorMonitoramento = [];
            foreach ($stmtFotos->fetchAll() as $foto) {
                $foto['url'] = 'uploads/monitoramento_fotos/' . $foto['arquivo'];
                $fotosPorMonitoramento[$foto['monitoramento_id']][] = $foto;
            }

            foreach ($monitoramentos as &$m) {
                $m['itens'] = $itensPorMonitoramento[$m['id']] ?? [];
                $m['fotos'] = $fotosPorMonitoramento[$m['id']] ?? [];
            }
            unset($m);
        }

        echo json_encode(['sucesso' => true, 'dados' => $monitoramentos]);
    }

    else if ($method === 'POST') {
        $data = corpo();
        $erros = validarMonitoramento($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("INSERT INTO monitoramentos (ponto_id, plantio_id, data_monitoramento, estagio_fenologico,
                                milho_plantas_avaliadas, milho_plantas_praga, soja_pragas_encontradas, soja_metros_lineares,
                                resultado_final, unidade_resultado, nivel_controle, observacoes)
                                VALUES (:ponto_id, :plantio_id, :data, :estagio, :milho_aval, :milho_praga, :soja_pragas, :soja_metros,
                                :resultado, :unidade, :nivel, :obs)");
        $stmt->execute([
            ':ponto_id'    => $data['ponto_id'],
            ':plantio_id'  => $data['plantio_id'],
            ':data'        => $data['data_monitoramento'],
            ':estagio'     => $data['estagio_fenologico'] ?? null,
            // Sem valor-padrão fixo aqui: se a cultura do plantio não é
            // milho/soja, o frontend manda null e respeitamos — quem decide
            // o valor certo pra milho/soja é o frontend, conforme a cultura
            // do plantio escolhido.
            ':milho_aval'  => $data['milho_plantas_avaliadas'] ?? null,
            ':milho_praga' => $data['milho_plantas_praga'] ?? null,
            ':soja_pragas' => $data['soja_pragas_encontradas'] ?? null,
            ':soja_metros' => $data['soja_metros_lineares'] ?? null,
            ':resultado'   => $data['resultado_final'] ?? null,
            ':unidade'     => $data['unidade_resultado'] ?? null,
            ':nivel'       => $data['nivel_controle'] ?? null,
            ':obs'         => $data['observacoes'] ?? null
        ]);
        $monitoramentoId = $pdo->lastInsertId();

        // Registra as pragas/doenças/condições encontradas nesta visita
        if (!empty($data['itens']) && is_array($data['itens'])) {
            $insItem = $pdo->prepare("INSERT INTO monitoramento_itens (monitoramento_id, tipo, nome, quantidade, severidade, observacoes)
                                       VALUES (:mid, :tipo, :nome, :qtd, :sev, :obs)");
            foreach ($data['itens'] as $item) {
                if (empty($item['nome'])) continue;
                $insItem->execute([
                    ':mid'  => $monitoramentoId,
                    ':tipo' => $item['tipo'] ?? 'praga',
                    ':nome' => $item['nome'],
                    ':qtd'  => $item['quantidade'] ?? null,
                    ':sev'  => $item['severidade'] ?? null,
                    ':obs'  => $item['observacoes'] ?? null
                ]);
            }
        }

        echo json_encode(['sucesso' => true, 'id' => $monitoramentoId]);
    }
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $erros = validarMonitoramento($data);
        if ($erros) erro(implode('; ', $erros));

        $stmt = $pdo->prepare("UPDATE monitoramentos SET ponto_id=:ponto_id, plantio_id=:plantio_id, data_monitoramento=:data,
                                estagio_fenologico=:estagio, milho_plantas_avaliadas=:milho_aval, milho_plantas_praga=:milho_praga,
                                soja_pragas_encontradas=:soja_pragas, soja_metros_lineares=:soja_metros,
                                resultado_final=:resultado, unidade_resultado=:unidade, nivel_controle=:nivel,
                                observacoes=:obs WHERE id=:id");
        $stmt->execute([
            ':ponto_id'    => $data['ponto_id'],
            ':plantio_id'  => $data['plantio_id'],
            ':data'        => $data['data_monitoramento'],
            ':estagio'     => $data['estagio_fenologico'] ?? null,
            ':milho_aval'  => $data['milho_plantas_avaliadas'] ?? null,
            ':milho_praga' => $data['milho_plantas_praga'] ?? null,
            ':soja_pragas' => $data['soja_pragas_encontradas'] ?? null,
            ':soja_metros' => $data['soja_metros_lineares'] ?? null,
            ':resultado'   => $data['resultado_final'] ?? null,
            ':unidade'     => $data['unidade_resultado'] ?? null,
            ':nivel'       => $data['nivel_controle'] ?? null,
            ':obs'         => $data['observacoes'] ?? null,
            ':id'          => $id
        ]);

        // Substitui a lista de itens (pragas/doenças) por completo, se foi enviada
        if (array_key_exists('itens', $data)) {
            $pdo->prepare("DELETE FROM monitoramento_itens WHERE monitoramento_id = :mid")->execute([':mid' => $id]);
            if (is_array($data['itens'])) {
                $insItem = $pdo->prepare("INSERT INTO monitoramento_itens (monitoramento_id, tipo, nome, quantidade, severidade, observacoes)
                                           VALUES (:mid, :tipo, :nome, :qtd, :sev, :obs)");
                foreach ($data['itens'] as $item) {
                    if (empty($item['nome'])) continue;
                    $insItem->execute([
                        ':mid'  => $id,
                        ':tipo' => $item['tipo'] ?? 'praga',
                        ':nome' => $item['nome'],
                        ':qtd'  => $item['quantidade'] ?? null,
                        ':sev'  => $item['severidade'] ?? null,
                        ':obs'  => $item['observacoes'] ?? null
                    ]);
                }
            }
        }

        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM monitoramentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /monitoramentos', 405);
    }
}

// ========== FOTOS DO MONITORAMENTO ==========
// POST vem como multipart/form-data (upload de arquivo), não JSON — por
// isso não usa corpo(), usa $_FILES/$_POST direto, diferente do resto da API.
else if ($recurso === 'monitoramento-fotos') {

    if ($method === 'POST') {
        $monitoramentoId = $_POST['monitoramento_id'] ?? null;
        if (!$monitoramentoId) erro('monitoramento_id é obrigatório');
        if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
            erro('Nenhuma foto enviada');
        }
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            erro('Falha no envio da foto (código ' . $_FILES['foto']['error'] . ')');
        }

        // Confirma que o monitoramento existe antes de aceitar a foto
        $stmtCheck = $pdo->prepare("SELECT id FROM monitoramentos WHERE id = :id");
        $stmtCheck->execute([':id' => $monitoramentoId]);
        if (!$stmtCheck->fetch()) erro('Monitoramento não encontrado', 404);

        $tmpPath = $_FILES['foto']['tmp_name'];
        $tamanhoMax = 8 * 1024 * 1024; // 8 MB
        if ($_FILES['foto']['size'] > $tamanhoMax) {
            erro('Foto muito grande (máximo 8 MB)');
        }

        // Valida que é realmente uma imagem (não confia só na extensão/nome enviado)
        $infoImagem = @getimagesize($tmpPath);
        if ($infoImagem === false) {
            erro('Arquivo não é uma imagem válida');
        }
        $mimesAceitos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $infoImagem['mime'];
        if (!isset($mimesAceitos[$mime])) {
            erro('Formato de imagem não aceito (use JPG, PNG ou WEBP)');
        }
        $extensao = $mimesAceitos[$mime];

        $pastaUploads = __DIR__ . '/../uploads/monitoramento_fotos';
        if (!is_dir($pastaUploads)) {
            mkdir($pastaUploads, 0755, true);
        }

        $nomeArquivo = 'mon' . $monitoramentoId . '_' . uniqid() . '.' . $extensao;
        $destino = $pastaUploads . '/' . $nomeArquivo;

        $movido = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $destino)
            : copy($tmpPath, $destino); // fallback pra ambientes de teste/CLI

        if (!$movido) {
            erro('Não foi possível salvar a foto no servidor', 500);
        }

        $stmt = $pdo->prepare("INSERT INTO monitoramento_fotos (monitoramento_id, arquivo, descricao) VALUES (:mid, :arquivo, :desc)");
        $stmt->execute([
            ':mid'     => $monitoramentoId,
            ':arquivo' => $nomeArquivo,
            ':desc'    => trim($_POST['descricao'] ?? '') === '' ? null : trim($_POST['descricao'])
        ]);

        echo json_encode([
            'sucesso' => true,
            'id'      => $pdo->lastInsertId(),
            'arquivo' => $nomeArquivo,
            'url'     => 'uploads/monitoramento_fotos/' . $nomeArquivo
        ]);
    }

    // Atualiza a foto: favorita (entra no relatório do dia) e/ou legenda — só o que vier no corpo
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        $campos = [];
        $params = [':id' => $id];
        if (array_key_exists('favorita', $data)) {
            $campos[] = 'favorita = :fav';
            $params[':fav'] = !empty($data['favorita']) ? 1 : 0;
        }
        if (array_key_exists('descricao', $data)) {
            $campos[] = 'descricao = :desc';
            $params[':desc'] = trim($data['descricao'] ?? '') === '' ? null : trim($data['descricao']); // o campo da tela já limita a 255
        }
        if (!$campos) erro('Nada para atualizar');
        $stmt = $pdo->prepare("UPDATE monitoramento_fotos SET " . implode(', ', $campos) . " WHERE id = :id");
        $stmt->execute($params);
        echo json_encode(['sucesso' => true]);
    }

    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("SELECT arquivo FROM monitoramento_fotos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $foto = $stmt->fetch();
        if (!$foto) erro('Foto não encontrada', 404);

        $pdo->prepare("DELETE FROM monitoramento_fotos WHERE id = :id")->execute([':id' => $id]);

        $caminhoArquivo = __DIR__ . '/../uploads/monitoramento_fotos/' . $foto['arquivo'];
        if (file_exists($caminhoArquivo)) {
            @unlink($caminhoArquivo);
        }

        echo json_encode(['sucesso' => true]);
    }

    else {
        erro('Método não suportado para /monitoramento-fotos', 405);
    }
}

// ========== RESUMO (Área -> Safra -> Plantio, agregado) ==========
// Alimenta a tela de Monitoramento reorganizada: mostra a somatória de
// cada plantio antes do usuário entrar nos pontos/monitoramentos individuais.
else if ($recurso === 'resumo-monitoramento') {
    if ($method === 'GET') {
        $area_id = $_GET['area_id'] ?? null;
        $sql = "SELECT
                    a.id AS area_id, a.nome AS area_nome,
                    s.id AS safra_id, s.nome AS safra_nome,
                    p.id AS plantio_id, p.data_plantio, c.nome AS cultura_nome,
                    COUNT(m.id) AS qtd_monitoramentos,
                    SUM(CASE WHEN m.nivel_controle = 'critico' THEN 1 ELSE 0 END) AS qtd_criticos,
                    SUM(CASE WHEN m.nivel_controle = 'atencao' THEN 1 ELSE 0 END) AS qtd_atencao,
                    MAX(m.data_monitoramento) AS ultimo_monitoramento
                FROM plantios p
                JOIN areas a ON a.id = p.area_id
                JOIN safras s ON s.id = p.safra_id
                JOIN culturas c ON c.id = p.cultura_id
                LEFT JOIN monitoramentos m ON m.plantio_id = p.id";
        $params = [];
        if ($area_id) { $sql .= " WHERE p.area_id = :area_id"; $params[':area_id'] = $area_id; }
        $sql .= " GROUP BY p.id ORDER BY a.nome, s.ano_inicio DESC, s.mes_inicio DESC, p.data_plantio DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    } else {
        erro('Método não suportado para /resumo-monitoramento', 405);
    }
}

// ========== RELATÓRIOS ==========
// GET /relatorios/dias[?area_id=]           → dias (plantio + data) que têm monitoramento, pra escolher o relatório
// GET /relatorios/diario?plantio_id=&data=  → dados consolidados do Relatório 1 (monitoramento do dia)
// GET /relatorios/safra?plantio_id=         → dados do Relatório 2 (resumo da safra)
// GET /relatorios/comparativo?area_id=      → dados do Relatório 3 (histórico comparativo da área)
// GET /relatorios/cliente?cliente_id=       → dados do Relatório 4 (consolidado do cliente)
else if ($recurso === 'relatorios') {
    if ($method !== 'GET') erro('Método não suportado para /relatorios', 405);
    $tipoRelatorio = $partes[1] ?? '';

    if ($tipoRelatorio === 'dias') {
        $sql = "SELECT pl.id AS plantio_id, m.data_monitoramento AS data, COUNT(*) AS qtd_pontos,
                       a.id AS area_id, a.nome AS area_nome, cl.nome AS cliente_nome,
                       c.nome AS cultura_nome, s.nome AS safra_nome
                FROM monitoramentos m
                JOIN plantios pl ON pl.id = m.plantio_id
                JOIN areas a     ON a.id = pl.area_id
                JOIN clientes cl ON cl.id = a.cliente_id
                JOIN culturas c  ON c.id = pl.cultura_id
                JOIN safras s    ON s.id = pl.safra_id";
        $params = [];
        if (!empty($_GET['area_id'])) { $sql .= " WHERE a.id = :area_id"; $params[':area_id'] = $_GET['area_id']; }
        $sql .= " GROUP BY pl.id, m.data_monitoramento ORDER BY m.data_monitoramento DESC, a.nome";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }

    else if ($tipoRelatorio === 'diario') {
        $plantioId = $_GET['plantio_id'] ?? null;
        $data      = $_GET['data'] ?? null;
        if (!$plantioId || !$data) erro('plantio_id e data são obrigatórios');

        // --- Cabeçalho: cliente, área, cultura, safra, variedade ---
        $stmt = $pdo->prepare("SELECT pl.id AS plantio_id, pl.data_plantio,
                                      a.nome AS area_nome, a.hectares, a.alqueires, a.geometria AS area_geometria,
                                      cl.nome AS cliente_nome, c.nome AS cultura_nome,
                                      s.nome AS safra_nome, " . sqlVariedadeNome('pl') . " AS variedade_nome
                               FROM plantios pl
                               JOIN areas a     ON a.id = pl.area_id
                               JOIN clientes cl ON cl.id = a.cliente_id
                               JOIN culturas c  ON c.id = pl.cultura_id
                               JOIN safras s    ON s.id = pl.safra_id
                               LEFT JOIN variedades v ON v.id = pl.variedade_id
                               WHERE pl.id = :id");
        $stmt->execute([':id' => $plantioId]);
        $cabecalho = $stmt->fetch();
        if (!$cabecalho) erro('Plantio não encontrado', 404);
        $cultura = strtolower(trim($cabecalho['cultura_nome'])); // 'milho' | 'soja'

        // --- Monitoramentos (pontos) do dia ---
        $stmt = $pdo->prepare("SELECT m.*, p.numero_ponto, p.latitude AS ponto_lat, p.longitude AS ponto_lng
                               FROM monitoramentos m
                               JOIN pontos_monitoramento p ON p.id = m.ponto_id
                               WHERE m.plantio_id = :pl AND m.data_monitoramento = :data
                               ORDER BY p.numero_ponto");
        $stmt->execute([':pl' => $plantioId, ':data' => $data]);
        $mons = $stmt->fetchAll();
        if (!$mons) erro('Nenhum monitoramento nesse plantio e data', 404);

        $ids = array_column($mons, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare("SELECT * FROM monitoramento_itens WHERE monitoramento_id IN ($ph) ORDER BY id");
        $stmt->execute($ids);
        $itens = $stmt->fetchAll();

        // Só as fotos que o agrônomo favoritou entram no relatório
        $stmt = $pdo->prepare("SELECT * FROM monitoramento_fotos WHERE monitoramento_id IN ($ph) AND favorita = 1 ORDER BY id");
        $stmt->execute($ids);
        $fotosDb = $stmt->fetchAll();

        // --- Bases do cálculo ---
        // Milho: soma das plantas avaliadas no dia (ex: 4 pontos x 20 = 80)
        // Soja: mesma fórmula da tela de monitoramento (metros / 2 por ponto) → média por ponto de pano
        $plantasAvaliadas = 0;
        $divisorSoja = 0;
        foreach ($mons as $m) {
            $plantasAvaliadas += (int)($m['milho_plantas_avaliadas'] ?? 0);
            $divisorSoja      += ((float)($m['soja_metros_lineares'] ?: 2)) / 2;
        }

        // --- Consolida os itens de todos os pontos (mesmo tipo + nome = mesmo item) ---
        $ordemSev    = ['leve' => 1, 'moderada' => 2, 'severa' => 3];
        $consolidado = [];
        $itensPorMon = [];
        foreach ($itens as $it) {
            $itensPorMon[$it['monitoramento_id']][] = $it;
            $chave = $it['tipo'] . '|' . strtolower(trim($it['nome']));
            if (!isset($consolidado[$chave])) {
                $consolidado[$chave] = ['tipo' => $it['tipo'], 'nome' => trim($it['nome']), 'total' => null,
                                        'pontos' => [], 'severidade' => null, 'locais' => []];
            }
            $c = &$consolidado[$chave];
            if ($it['quantidade'] !== null) $c['total'] = ($c['total'] ?? 0) + (int)$it['quantidade'];
            $c['pontos'][$it['monitoramento_id']] = true;
            $sev = $it['severidade'] ?? null;
            if ($sev && ($ordemSev[$sev] ?? 0) > ($ordemSev[$c['severidade']] ?? 0)) $c['severidade'] = $sev;
            if (!empty($it['observacoes'])) $c['locais'][] = trim($it['observacoes']);
            unset($c);
        }

        $grupos = ['praga' => 'pragas', 'doenca' => 'doencas', 'planta_daninha' => 'daninhas', 'clima' => 'clima'];
        $resultado = ['pragas' => [], 'doencas' => [], 'daninhas' => [], 'clima' => []];
        foreach ($consolidado as $c) {
            $item = [
                'nome'       => $c['nome'],
                'total'      => $c['total'],
                'indice'     => null,
                'unidade'    => null,
                'nivel'      => null,
                'pontos_com' => count($c['pontos']),
                'severidade' => $c['severidade'],
                'locais'     => array_values(array_unique($c['locais']))
            ];
            // Índice só pra praga com contagem; itens sem contagem (ex: ovos) mostram "X de Y pontos"
            if ($c['tipo'] === 'praga' && $c['total'] !== null) {
                [$item['indice'], $item['unidade'], $item['nivel']] = indicePraga($c['total'], $cultura, $plantasAvaliadas, $divisorSoja);
            }
            $resultado[$grupos[$c['tipo']] ?? 'pragas'][] = $item;
        }
        // Pragas com maior índice primeiro; as sem contagem vão pro fim
        usort($resultado['pragas'], function ($a, $b) {
            return ($b['indice'] ?? -1) <=> ($a['indice'] ?? -1);
        });

        // --- Estádio(s) do dia: se os pontos divergirem vira faixa (ex: R2/R3) ---
        $estadios = [];
        foreach ($mons as $m) {
            $e = strtoupper(trim($m['estagio_fenologico'] ?? ''));
            if ($e !== '' && !in_array($e, $estadios)) $estadios[] = $e;
        }

        // --- Detalhe por ponto ---
        $pontos = [];
        foreach ($mons as $m) {
            $pontos[] = [
                'numero_ponto'      => $m['numero_ponto'],
                'latitude'          => $m['ponto_lat'],
                'longitude'         => $m['ponto_lng'],
                'estagio'           => $m['estagio_fenologico'],
                'plantas_avaliadas' => $m['milho_plantas_avaliadas'],
                'metros_lineares'   => $m['soja_metros_lineares'],
                'observacoes'       => $m['observacoes'],
                'itens'             => array_map(function ($it) {
                    return ['tipo' => $it['tipo'], 'nome' => $it['nome'], 'quantidade' => $it['quantidade'],
                            'severidade' => $it['severidade'], 'observacoes' => $it['observacoes']];
                }, $itensPorMon[$m['id']] ?? [])
            ];
        }

        // --- Fotos favoritas, com o ponto pra legenda ---
        $pontoPorMon = array_column($mons, 'numero_ponto', 'id');
        $fotos = [];
        foreach ($fotosDb as $f) {
            $fotos[] = [
                'url'          => 'uploads/monitoramento_fotos/' . $f['arquivo'],
                'numero_ponto' => $pontoPorMon[$f['monitoramento_id']] ?? null,
                'descricao'    => $f['descricao']
            ];
        }

        $cabecalho['data']              = $data;
        $cabecalho['qtd_pontos']        = count($mons);
        $cabecalho['plantas_avaliadas'] = $cultura === 'milho' ? $plantasAvaliadas : null;

        echo json_encode(['sucesso' => true, 'dados' => [
            'cabecalho' => $cabecalho,
            'estadios'  => $estadios,
            'pragas'    => $resultado['pragas'],
            'doencas'   => $resultado['doencas'],
            'daninhas'  => $resultado['daninhas'],
            'clima'     => $resultado['clima'],
            'pontos'    => $pontos,
            'fotos'     => $fotos,
            'talhoes'   => talhoesDoPlantio($pdo, $plantioId)
        ]]);
    }


    // Relatório 2: resumo da safra de um plantio (área + safra)
    else if ($tipoRelatorio === 'safra') {
        $plantioId = $_GET['plantio_id'] ?? null;
        if (!$plantioId) erro('plantio_id é obrigatório');

        $stmt = $pdo->prepare("SELECT pl.id AS plantio_id, pl.data_plantio, pl.populacao_semente, pl.adubo_qtd_kg,
                                      pl.condicao_plantio, pl.condicao_solo, pl.observacoes,
                                      a.nome AS area_nome, a.hectares, a.alqueires,
                                      cl.nome AS cliente_nome, c.nome AS cultura_nome,
                                      s.nome AS safra_nome, " . sqlVariedadeNome('pl') . " AS variedade_nome, a.geometria AS area_geometria,
                                      ad.nome AS adubo_nome, ad.npk AS adubo_npk,
                                      co.data_colheita, co.produtividade_sc_ha, co.umidade_pct, co.observacoes AS colheita_obs
                               FROM plantios pl
                               JOIN areas a     ON a.id = pl.area_id
                               JOIN clientes cl ON cl.id = a.cliente_id
                               JOIN culturas c  ON c.id = pl.cultura_id
                               JOIN safras s    ON s.id = pl.safra_id
                               LEFT JOIN variedades v ON v.id = pl.variedade_id
                               LEFT JOIN adubos ad    ON ad.id = pl.adubo_id
                               LEFT JOIN colheitas co ON co.plantio_id = pl.id
                               WHERE pl.id = :id");
        $stmt->execute([':id' => $plantioId]);
        $cabecalho = $stmt->fetch();
        if (!$cabecalho) erro('Plantio não encontrado', 404);
        $cultura = strtolower(trim($cabecalho['cultura_nome']));

        [$diasOut, $ocorrencias, $totalPontos] = resumoMonitoramentosPlantio($pdo, $plantioId, $cultura);

        $stmt = $pdo->prepare("SELECT data_aplicacao, produto, tipo_produto, dose, unidade_dose, observacoes
                               FROM aplicacoes WHERE plantio_id = :pl ORDER BY data_aplicacao");
        $stmt->execute([':pl' => $plantioId]);
        $aplicacoes = $stmt->fetchAll();

        echo json_encode(['sucesso' => true, 'dados' => [
            'cabecalho'   => $cabecalho,
            'dias'        => $diasOut,
            'ocorrencias' => $ocorrencias,
            'aplicacoes'  => $aplicacoes,
            'talhoes'     => talhoesDoPlantio($pdo, $plantioId),
            'totais'      => ['dias' => count($diasOut), 'pontos' => $totalPontos, 'aplicacoes' => count($aplicacoes)]
        ]]);
    }

    // Relatório 3: histórico comparativo de todas as safras/culturas de uma área
    else if ($tipoRelatorio === 'comparativo') {
        $areaId = $_GET['area_id'] ?? null;
        if (!$areaId) erro('area_id é obrigatório');

        $stmt = $pdo->prepare("SELECT a.id, a.nome AS area_nome, a.hectares, a.alqueires, cl.nome AS cliente_nome
                               FROM areas a JOIN clientes cl ON cl.id = a.cliente_id WHERE a.id = :id");
        $stmt->execute([':id' => $areaId]);
        $area = $stmt->fetch();
        if (!$area) erro('Área não encontrada', 404);

        $plantios = plantiosComResumo($pdo, 'pl.area_id = :filtro', $areaId);

        echo json_encode(['sucesso' => true, 'dados' => ['area' => $area, 'plantios' => $plantios]]);
    }


    // Relatório 4: consolidado de todas as áreas de um cliente
    else if ($tipoRelatorio === 'cliente') {
        $clienteId = $_GET['cliente_id'] ?? null;
        if (!$clienteId) erro('cliente_id é obrigatório');

        $stmt = $pdo->prepare("SELECT id, nome FROM clientes WHERE id = :id");
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch();
        if (!$cliente) erro('Cliente não encontrado', 404);

        $stmt = $pdo->prepare("SELECT id, nome, hectares FROM areas WHERE cliente_id = :id ORDER BY nome");
        $stmt->execute([':id' => $clienteId]);
        $areas = $stmt->fetchAll();

        $plantios = plantiosComResumo($pdo, 'a.cliente_id = :filtro', $clienteId);

        echo json_encode(['sucesso' => true, 'dados' => ['cliente' => $cliente, 'areas' => $areas, 'plantios' => $plantios]]);
    }

    else {
        erro('Relatório não encontrado', 404);
    }
}

// ocorrencias: removida no schema v2 — consolidada em monitoramento_itens
// (ver monitoramentos.itens no GET /monitoramentos)

// ========== SAFRAS ==========
// ========== APLICAÇÕES ==========
// Só produto, data e plantio são obrigatórios — dose, unidade, tipo e observações são opcionais
else if ($recurso === 'aplicacoes') {
    if ($method === 'GET') {
        $sql = "SELECT ap.*, a.nome AS area_nome, cl.nome AS cliente_nome, c.nome AS cultura_nome, s.nome AS safra_nome
                FROM aplicacoes ap
                JOIN areas a     ON a.id = ap.area_id
                JOIN clientes cl ON cl.id = a.cliente_id
                LEFT JOIN plantios pl ON pl.id = ap.plantio_id
                LEFT JOIN culturas c  ON c.id = pl.cultura_id
                LEFT JOIN safras s    ON s.id = pl.safra_id";
        $params = [];
        if (!empty($_GET['area_id'])) { $sql .= " WHERE ap.area_id = :area_id"; $params[':area_id'] = $_GET['area_id']; }
        $sql .= " ORDER BY ap.data_aplicacao DESC, ap.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST' || ($method === 'PUT' && $id)) {
        $data = corpo();
        if (empty($data['plantio_id']) || empty($data['data_aplicacao']) || trim($data['produto'] ?? '') === '') {
            erro('Plantio, data e produto são obrigatórios');
        }
        // A área vem do próprio plantio
        $stmt = $pdo->prepare("SELECT area_id FROM plantios WHERE id = :id");
        $stmt->execute([':id' => $data['plantio_id']]);
        $plantio = $stmt->fetch();
        if (!$plantio) erro('Plantio não encontrado', 404);

        $vazio = function ($v) { return ($v ?? '') === '' ? null : $v; };
        $params = [
            ':area'  => $plantio['area_id'],
            ':pl'    => $data['plantio_id'],
            ':prod'  => trim($data['produto']),
            ':tipo'  => $vazio($data['tipo_produto'] ?? null),
            ':dose'  => $vazio($data['dose'] ?? null),
            ':un'    => $vazio($data['unidade_dose'] ?? null),
            ':data'  => $data['data_aplicacao'],
            ':obs'   => $vazio($data['observacoes'] ?? null)
        ];
        if ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO aplicacoes (area_id, plantio_id, produto, tipo_produto, dose, unidade_dose, data_aplicacao, observacoes)
                                   VALUES (:area, :pl, :prod, :tipo, :dose, :un, :data, :obs)");
            $stmt->execute($params);
            echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            $params[':id'] = $id;
            $stmt = $pdo->prepare("UPDATE aplicacoes SET area_id=:area, plantio_id=:pl, produto=:prod, tipo_produto=:tipo, dose=:dose,
                                   unidade_dose=:un, data_aplicacao=:data, observacoes=:obs WHERE id=:id");
            $stmt->execute($params);
            echo json_encode(['sucesso' => true]);
        }
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM aplicacoes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /aplicacoes', 405);
    }
}

// ========== COLHEITAS ==========
// Uma colheita por plantio: data, produtividade (sc/ha), umidade e observações
else if ($recurso === 'colheitas') {
    if ($method === 'GET') {
        $sql = "SELECT co.*, pl.data_plantio, pl.area_id, a.nome AS area_nome, a.hectares,
                       cl.nome AS cliente_nome, c.nome AS cultura_nome, s.nome AS safra_nome, " . sqlVariedadeNome('pl') . " AS variedade_nome
                FROM colheitas co
                JOIN plantios pl ON pl.id = co.plantio_id
                JOIN areas a     ON a.id = pl.area_id
                JOIN clientes cl ON cl.id = a.cliente_id
                JOIN culturas c  ON c.id = pl.cultura_id
                JOIN safras s    ON s.id = pl.safra_id
                LEFT JOIN variedades v ON v.id = pl.variedade_id";
        $params = [];
        if (!empty($_GET['area_id'])) { $sql .= " WHERE pl.area_id = :area_id"; $params[':area_id'] = $_GET['area_id']; }
        $sql .= " ORDER BY co.data_colheita DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST' || ($method === 'PUT' && $id)) {
        $data = corpo();
        if (empty($data['plantio_id']) || empty($data['data_colheita'])) erro('Plantio e data da colheita são obrigatórios');
        if (!isset($data['produtividade_sc_ha']) || (float)$data['produtividade_sc_ha'] <= 0) erro('Informe a produtividade (sc/ha) maior que zero');

        // A colheita não pode ser antes do plantio
        $stmt = $pdo->prepare("SELECT data_plantio FROM plantios WHERE id = :id");
        $stmt->execute([':id' => $data['plantio_id']]);
        $plantio = $stmt->fetch();
        if (!$plantio) erro('Plantio não encontrado', 404);
        if ($data['data_colheita'] < $plantio['data_plantio']) erro('A data da colheita não pode ser anterior à data do plantio');

        // Só uma colheita por plantio
        $stmt = $pdo->prepare("SELECT id FROM colheitas WHERE plantio_id = :pl AND id <> :id");
        $stmt->execute([':pl' => $data['plantio_id'], ':id' => $id ?: 0]);
        if ($stmt->fetch()) erro('Esse plantio já tem colheita lançada — edite a existente');

        $params = [
            ':pl'   => $data['plantio_id'],
            ':data' => $data['data_colheita'],
            ':prod' => $data['produtividade_sc_ha'],
            ':umid' => ($data['umidade_pct'] ?? '') === '' ? null : $data['umidade_pct'],
            ':obs'  => ($data['observacoes'] ?? '') === '' ? null : $data['observacoes']
        ];
        if ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO colheitas (plantio_id, data_colheita, produtividade_sc_ha, umidade_pct, observacoes)
                                   VALUES (:pl, :data, :prod, :umid, :obs)");
            $stmt->execute($params);
            echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            $params[':id'] = $id;
            $stmt = $pdo->prepare("UPDATE colheitas SET plantio_id=:pl, data_colheita=:data, produtividade_sc_ha=:prod,
                                   umidade_pct=:umid, observacoes=:obs WHERE id=:id");
            $stmt->execute($params);
            echo json_encode(['sucesso' => true]);
        }
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM colheitas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /colheitas', 405);
    }
}

else if ($recurso === 'safras') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM safras ORDER BY ano_inicio DESC, mes_inicio DESC, nome");
        echo json_encode(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
    }
    else if ($method === 'POST') {
        $data = corpo();
        if (empty($data['nome']) || empty($data['ano_inicio']) || empty($data['ano_fim'])) {
            erro('Nome, ano de início e ano de fim são obrigatórios');
        }
        $mesInicio = isset($data['mes_inicio']) && $data['mes_inicio'] >= 1 && $data['mes_inicio'] <= 12 ? (int)$data['mes_inicio'] : 1;
        $mesFim    = isset($data['mes_fim']) && $data['mes_fim'] >= 1 && $data['mes_fim'] <= 12 ? (int)$data['mes_fim'] : 12;

        $stmt = $pdo->prepare("INSERT INTO safras (nome, mes_inicio, ano_inicio, mes_fim, ano_fim, ativa)
                                VALUES (:nome, :mes_inicio, :inicio, :mes_fim, :fim, :ativa)");
        $stmt->execute([
            ':nome'       => $data['nome'],
            ':mes_inicio' => $mesInicio,
            ':inicio'     => $data['ano_inicio'],
            ':mes_fim'    => $mesFim,
            ':fim'        => $data['ano_fim'],
            ':ativa'      => isset($data['ativa']) ? (int)$data['ativa'] : 1
        ]);
        echo json_encode(['sucesso' => true, 'id' => $pdo->lastInsertId()]);
    }
    else if ($method === 'PUT' && $id) {
        $data = corpo();
        if (empty($data['nome']) || empty($data['ano_inicio']) || empty($data['ano_fim'])) {
            erro('Nome, ano de início e ano de fim são obrigatórios');
        }
        $mesInicio = isset($data['mes_inicio']) && $data['mes_inicio'] >= 1 && $data['mes_inicio'] <= 12 ? (int)$data['mes_inicio'] : 1;
        $mesFim    = isset($data['mes_fim']) && $data['mes_fim'] >= 1 && $data['mes_fim'] <= 12 ? (int)$data['mes_fim'] : 12;

        $stmt = $pdo->prepare("UPDATE safras SET nome=:nome, mes_inicio=:mes_inicio, ano_inicio=:inicio,
                                mes_fim=:mes_fim, ano_fim=:fim, ativa=:ativa WHERE id=:id");
        $stmt->execute([
            ':nome'       => $data['nome'],
            ':mes_inicio' => $mesInicio,
            ':inicio'     => $data['ano_inicio'],
            ':mes_fim'    => $mesFim,
            ':fim'        => $data['ano_fim'],
            ':ativa'      => isset($data['ativa']) ? (int)$data['ativa'] : 1,
            ':id'         => $id
        ]);
        echo json_encode(['sucesso' => true]);
    }
    else if ($method === 'DELETE' && $id) {
        $stmt = $pdo->prepare("DELETE FROM safras WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['sucesso' => true]);
    }
    else {
        erro('Método não suportado para /safras', 405);
    }
}

else {
    erro('Endpoint não encontrado: ' . $recurso, 404);
}

} catch (PDOException $e) {
    // Erro de banco (coluna/tabela faltando, FK violada, etc.) — nunca deixa vazar HTML pro frontend
    http_response_code(500);
    $msg = $e->getMessage();
    $dica = '';
    if (stripos($msg, 'geometria') !== false || stripos($msg, "Unknown column") !== false) {
        $dica = ' — parece que o banco está desatualizado. Rode api/migrate.php uma vez pelo navegador.';
    }
    echo json_encode(['sucesso' => false, 'erro' => 'Erro de banco de dados: ' . $msg . $dica]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno: ' . $e->getMessage()]);
}
