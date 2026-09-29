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
// v2: área não tem mais safra/cultura própria (isso agora vive em
// Plantio) — em compensação, cliente_id passou a ser obrigatório.
function validarArea($data) {
    $erros = [];
    if (empty($data['nome'])) $erros[] = 'Nome da área é obrigatório';
    if (empty($data['cliente_id'])) $erros[] = 'Cliente é obrigatório';
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
// v2: cada plantio pertence a uma safra específica (safra_id
// obrigatório) — é o plantio que "carrega" safra e cultura agora,
// não a área.
function validarPlantio($data) {
    $erros = [];
    if (empty($data['area_id'])) $erros[] = 'Área é obrigatória';
    if (empty($data['safra_id'])) $erros[] = 'Safra é obrigatória';
    if (empty($data['data_plantio'])) $erros[] = 'Data do plantio é obrigatória';
    if (empty($data['cultura_id'])) $erros[] = 'Cultura é obrigatória';
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
// v2: cultura não é mais escolhida no monitoramento — vem do plantio
// selecionado (plantio_id obrigatório). Por isso não dá mais pra
// exigir campos específicos de milho/soja aqui feito antes (era
// exatamente essa amarração por string que causava bug quando surgia
// uma 3ª cultura); o frontend decide quais campos mostrar/enviar
// conforme a cultura do plantio escolhido.
function validarMonitoramento($data) {
    $erros = [];
    if (empty($data['ponto_id'])) $erros[] = 'Ponto de monitoramento é obrigatório';
    if (empty($data['plantio_id'])) $erros[] = 'Plantio é obrigatório';
    if (empty($data['data_monitoramento'])) $erros[] = 'Data do monitoramento é obrigatória';
    return $erros;
}

// ── VALIDAR CLIENTE ──────────────────────────────────────
function validarCliente($data) {
    $erros = [];
    if (empty($data['nome'])) $erros[] = 'Nome do cliente é obrigatório';
    if (strlen($data['nome'] ?? '') > 150) $erros[] = 'Nome muito longo (máx 150 caracteres)';
    return $erros;
}
