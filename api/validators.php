<?php
// ============================================================
//  AgroMonitor SafraFort — Validators (Validações)
// ============================================================

// ── VALIDAR CULTURA ──────────────────────────────────────
function validarCultura($data) {
    $erros = [];
    if (empty($data['nome'])) $erros[] = 'Nome da cultura é obrigatório';
    if (strlen($data['nome'] ?? '') > 50) $erros[] = 'Nome muito longo (máx 50 caracteres)';
    return $erros;
}

// ── VALIDAR VARIEDADE ────────────────────────────────────
function validarVariedade($data) {
    $erros = [];
    if (empty($data['cultura_id'])) $erros[] = 'Cultura é obrigatória';
    if (empty($data['nome'])) $erros[] = 'Nome da variedade é obrigatório';
    if (strlen($data['nome'] ?? '') > 100) $erros[] = 'Nome muito longo (máx 100 caracteres)';
    return $erros;
}

// ── VALIDAR ADUBO ───────────────────────────────────────
function validarAdubo($data) {
    $erros = [];
    if (empty($data['nome'])) $erros[] = 'Nome do adubo é obrigatório';
    if (strlen($data['nome'] ?? '') > 100) $erros[] = 'Nome muito longo (máx 100 caracteres)';
    return $erros;
}

// ── VALIDAR PRAGA ───────────────────────────────────────
function validarPraga($data) {
    $erros = [];
    if (empty($data['nome'])) $erros[] = 'Nome da praga é obrigatório';
    if (strlen($data['nome'] ?? '') > 100) $erros[] = 'Nome muito longo (máx 100 caracteres)';
    return $erros;
}

// ── VALIDAR ÁREA ────────────────────────────────────────
function validarArea($data) {
    $erros = [];
    if (empty($data['nome'])) $erros[] = 'Nome da área é obrigatório';
    if (empty($data['nome_cliente'])) $erros[] = 'Nome do cliente é obrigatório';
    if (empty($data['cultura'])) $erros[] = 'Cultura é obrigatória';
    if (!isset($data['hectares']) || $data['hectares'] <= 0) $erros[] = 'Hectares deve ser maior que zero';
    return $erros;
}

// ── VALIDAR TALHÃO ──────────────────────────────────────
function validarTalhao($data) {
    $erros = [];
    if (empty($data['area_id'])) $erros[] = 'Área é obrigatória';
    if (empty($data['nome'])) $erros[] = 'Nome do talhão é obrigatório';
    return $erros;
}

// ── VALIDAR PLANTIO ─────────────────────────────────────
function validarPlantio($data) {
    $erros = [];
    if (empty($data['area_id'])) $erros[] = 'Área é obrigatória';
    if (empty($data['data_plantio'])) $erros[] = 'Data do plantio é obrigatória';
    if (empty($data['cultura'])) $erros[] = 'Cultura é obrigatória';
    return $erros;
}

// ── VALIDAR PONTO ───────────────────────────────────────
function validarPonto($data) {
    $erros = [];
    if (empty($data['area_id'])) $erros[] = 'Área é obrigatória';
    if (!isset($data['numero_ponto']) || $data['numero_ponto'] <= 0) $erros[] = 'Número do ponto deve ser maior que zero';
    return $erros;
}

// ── VALIDAR MONITORAMENTO ───────────────────────────────
function validarMonitoramento($data) {
    $erros = [];
    if (empty($data['ponto_id'])) $erros[] = 'Ponto de monitoramento é obrigatório';
    if (empty($data['cultura'])) $erros[] = 'Cultura é obrigatória';
    
    if ($data['cultura'] === 'milho') {
        if (!isset($data['milho_plantas_avaliadas'])) $erros[] = 'Plantas avaliadas é obrigatório para milho';
        if (!isset($data['milho_plantas_praga'])) $erros[] = 'Plantas com praga é obrigatório para milho';
    } else if ($data['cultura'] === 'soja') {
        if (!isset($data['soja_pragas_encontradas'])) $erros[] = 'Pragas encontradas é obrigatório para soja';
        if (!isset($data['soja_metros_lineares'])) $erros[] = 'Metros lineares é obrigatório para soja';
    }
    
    return $erros;
}

// ── VALIDAR OCORRÊNCIA ──────────────────────────────────
function validarOcorrencia($data) {
    $erros = [];
    if (empty($data['ponto_id'])) $erros[] = 'Ponto de monitoramento é obrigatório';
    if (empty($data['tipo_praga'])) $erros[] = 'Tipo de praga é obrigatório';
    if (!isset($data['quantidade']) || $data['quantidade'] < 0) $erros[] = 'Quantidade deve ser um número positivo';
    return $erros;
}
