<?php
// ============================================================
//  AgroMonitor SafraFort — Setup do Banco de Dados COMPLETO
//  Acesse: http://localhost/agromonitor/api/setup.php
//  Cria todas as tabelas e dados iniciais no MySQL
// ============================================================
// v2: em vez de manter as definições de tabela duplicadas aqui dentro,
// este script lê e executa database/agromonitor.sql diretamente — assim
// só existe UM lugar (o .sql) definindo a estrutura do banco, e setup.php
// nunca fica desatualizado em relação a ele.
// ============================================================

require_once 'database.php';

header('Content-Type: text/html; charset=utf-8');

$db = getDB();
$log = [];

// ── LÊ E EXECUTA O SCHEMA SQL, INSTRUÇÃO POR INSTRUÇÃO ────
$schemaPath = __DIR__ . '/../database/agromonitor.sql';

if (!file_exists($schemaPath)) {
    http_response_code(500);
    die("<p style='color:red'>Arquivo não encontrado: $schemaPath</p>");
}

$sql = file_get_contents($schemaPath);

// Remove CREATE DATABASE / USE — a conexão já foi aberta no banco certo
// (definido em config.php), não precisa (nem pode, com o usuário mysql
// às vezes sem privilégio de CREATE DATABASE) recriar o banco aqui.
$sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
$sql = preg_replace('/USE\s+\w+\s*;/is', '', $sql);

// Remove comentários de linha (-- ...) antes de dividir em instruções,
// pra um ';' dentro de um comentário não quebrar a divisão
$linhas = explode("\n", $sql);
$linhas = array_filter($linhas, fn($l) => !preg_match('/^\s*--/', $l));
$sql = implode("\n", $linhas);

// Divide em instruções individuais pelo ';' de fim de linha (cuidando
// pra não cortar no meio de um ';' que esteja dentro de uma string, o
// que não acontece neste schema — não há ';' dentro de valores)
$statements = array_filter(array_map('trim', preg_split('/;\s*(?=\n|$)/', $sql)));

foreach ($statements as $stmt) {
    if ($stmt === '' || $stmt === ';') continue;

    // Descrição amigável pro log, baseada no tipo de comando
    if (preg_match('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?(\w+)`?/i', $stmt, $m)) {
        $desc = "Tabela `{$m[1]}` criada (ou já existia)";
    } elseif (preg_match('/INSERT (?:IGNORE )?INTO\s+`?(\w+)`?/i', $stmt, $m)) {
        $desc = "Dados iniciais inseridos em `{$m[1]}`";
    } else {
        $desc = 'Instrução executada';
    }

    try {
        $db->exec($stmt);
        $log[] = "✅ $desc";
    } catch (PDOException $e) {
        $log[] = "⚠️ $desc — " . $e->getMessage();
    }
}

// ── INTERFACE HTML ───────────────────────────────────────

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
<title>AgroMonitor — Setup</title>
<style>
body{font-family:'Plus Jakarta Sans', sans-serif;background:#0d1f10;color:#a8dbb5;padding:40px;max-width:700px;margin:0 auto}
h1{color:#4db368;margin-bottom:24px;font-size:28px}
h2{color:#4db368;margin-top:30px;margin-bottom:15px;font-size:18px;border-top:1px solid rgba(77,179,104,0.3);padding-top:15px}
.log{background:#0a1510;border-left:3px solid #4db368;padding:8px 12px;margin:5px 0;font-size:13px;border-radius:3px}
.ok{color:#4db368;border-left-color:#4db368}
.warn{color:#f59e0b;border-left-color:#f59e0b}
.success{background:#0d2b17;border:1px solid #4db368;padding:15px;margin:20px 0;border-radius:6px;color:#4db368;text-align:center;font-weight:500}
a{color:#4db368;margin-top:24px;display:inline-block;font-size:14px;padding:10px 20px;background:#0a1510;border-radius:4px;text-decoration:none;transition:all 0.2s}
a:hover{background:#0d2b17;transform:translateX(4px)}
</style></head><body>
<h1>🌱 AgroMonitor SafraFort — Setup Completo</h1>
<p>Criando banco de dados e tabelas a partir de database/agromonitor.sql...</p>

<h2>Tabelas e dados</h2>";

foreach ($log as $l) {
    $class = str_starts_with($l, '✅') ? 'ok' : 'warn';
    echo "<div class='log $class'>$l</div>";
}

echo "<div class='success'>✅ Setup concluído! Banco de dados pronto para usar.</div>
<a href='../index.html'>→ Acessar o Sistema</a>
</body></html>";
