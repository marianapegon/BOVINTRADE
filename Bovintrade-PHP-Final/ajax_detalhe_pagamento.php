<?php
// ajax_detalhe_pagamento.php
ob_start(); // evita "lixo" antes do JSON
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

// Relatório de erros em exceções (sem vazar HTML no output)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', '0');

function out($arr) {
  // limpa qualquer whitespace acidental e solta JSON
  while (ob_get_level()) ob_end_clean();
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $usuario = $_SESSION['usuario'] ?? [];
  // Alguns projetos guardam id da fazenda como 'fazenda_id' ou semelhante
  $fazendaId = (int)($usuario['id'] ?? $usuario['fazenda_id'] ?? 0);
  $tipo = strtoupper(trim((string)($usuario['tipo_usuario'] ?? $usuario['tipo'] ?? '')));

  $pagamentoId = (int)($_GET['id'] ?? 0);
  if ($pagamentoId <= 0) out(['erro'=>'ID inválido.']);

  if ($fazendaId <= 0) out(['erro'=>'Sessão inválida (fazenda não identificada).']);

  // ============ HEADER (pagamento + pedido + frigorífico) ============
  $sqlHeader = "
  SELECT
    pg.id                  AS pagamento_id,
    pg.pedido_id,
    pg.metodo,
    pg.status              AS status_pg,
    pg.valor,
    pg.moeda,
    pg.referencia_externa,
    pg.created_at,
    pg.confirmado_em,
    p.status               AS status_pedido,
    p.frigorifico_id,

    u.nome_razao           AS frig_nome,
    u.email                AS frig_email,
    u.cnpj                 AS frig_cnpj,
    u.telefone             AS frig_tel,
    u.rua, u.numero, u.bairro, u.cidade, u.estado, u.cep,

    f.responsavel_legal, f.cpf_responsavel, f.cargo_responsavel

  FROM pagamentos pg
  JOIN pedidos p
    ON p.id = pg.pedido_id

  /* IMPORTANTE: LEFT JOINs ANTES do WHERE */
  LEFT JOIN usuarios u
    ON u.id = p.frigorifico_id
  LEFT JOIN frigorifico f
    ON f.usuario_id = p.frigorifico_id

  /* Filtro vem DEPOIS dos JOINs */
  WHERE pg.id = ?
    AND EXISTS (
      SELECT 1
      FROM pedido_itens x
      WHERE x.pedido_id = pg.pedido_id
        AND x.fazenda_id = ?
    )
  LIMIT 1
";

  $stmt = $conn->prepare($sqlHeader);
  $stmt->bind_param('ii', $pagamentoId, $fazendaId);
  $stmt->execute();
  $H = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$H) out(['erro'=>'Pagamento não encontrado para esta fazenda.']);

  // ============ Método específico ============
  $extra = null;
  if ($H['metodo'] === 'PIX') {
    $q = $conn->prepare("SELECT chave_destino, copia_cola FROM pagamentos_pix WHERE pagamento_id = ? LIMIT 1");
    $q->bind_param('i', $pagamentoId);
    $q->execute();
    $pix = $q->get_result()->fetch_assoc();
    $q->close();
    if ($pix) {
      $extra = [
        'titulo' => 'Detalhes PIX',
        'texto'  => "Chave destino: " . ($pix['chave_destino'] ?? '-') .
                    "\nCopia e Cola: " . ($pix['copia_cola'] ?? '-')
      ];
    }
  } elseif ($H['metodo'] === 'CARTAO') {
    $q = $conn->prepare("SELECT bandeira, last4, titular_nome, exp_mes, exp_ano, autorizacao_codigo FROM pagamentos_cartao WHERE pagamento_id = ? LIMIT 1");
    $q->bind_param('i', $pagamentoId);
    $q->execute();
    $c = $q->get_result()->fetch_assoc();
    $q->close();
    if ($c) {
      $extra = [
        'titulo' => 'Detalhes do Cartão',
        'texto'  => "Bandeira: " . ($c['bandeira'] ?? '-') .
                    "\nFinal: " . ($c['last4'] ?? '****') .
                    "\nTitular: " . ($c['titular_nome'] ?? '-') .
                    "\nValidade: " . (($c['exp_mes'] ?? '--') . '/' . ($c['exp_ano'] ?? '----')) .
                    "\nAutorização: " . ($c['autorizacao_codigo'] ?? '-')
      ];
    }
  }

  // ============ Itens por repasse (se existir) ============
  $sqlR = "
    SELECT
      rf.id AS repasse_id, rf.status AS repasse_status, rf.valor_bruto, rf.valor_taxa, rf.valor_liquido,
      rf.taxa_plataforma_percent, rf.previsto_em, rf.pago_em,
      pi.quantidade_cabecas, pi.preco_unitario_cab, pi.valor_total,
      lb.codigo_lote, lb.raca, lb.peso_medio_kg
    FROM repasses_fazenda rf
    JOIN pedido_itens pi ON pi.id = rf.pedido_item_id
    LEFT JOIN lote_bois lb ON lb.id = pi.lote_id
    WHERE rf.pagamento_id = ? AND rf.fazenda_id = ?
    ORDER BY rf.id ASC
  ";
  $stmt = $conn->prepare($sqlR);
  $stmt->bind_param('ii', $pagamentoId, $fazendaId);
  $stmt->execute();
  $resR = $stmt->get_result();

  $itens = [];
  $temRepasse = false;
  $totBruto = $totTaxa = $totLiq = 0.0;
  $totCabs  = 0;

  while ($r = $resR->fetch_assoc()) {
    $temRepasse = true;
    $totBruto += (float)$r['valor_bruto'];
    $totTaxa  += (float)$r['valor_taxa'];
    $totLiq   += (float)$r['valor_liquido'];
    $totCabs  += (int)$r['quantidade_cabecas'];

    $itens[] = [
      'repasse_id'    => (int)$r['repasse_id'],
      'status_repasse'=> $r['repasse_status'],
      'qtd'           => (int)$r['quantidade_cabecas'],
      'preco_unit'    => 'R$ '.number_format((float)$r['preco_unitario_cab'],2,',','.'),
      'valor_item'    => 'R$ '.number_format((float)$r['valor_total'],2,',','.'),
      'bruto'         => 'R$ '.number_format((float)$r['valor_bruto'],2,',','.'),
      'taxa_percent'  => number_format((float)$r['taxa_plataforma_percent'],2,',','.').'%',
      'taxa'          => 'R$ '.number_format((float)$r['valor_taxa'],2,',','.'),
      'liquido'       => 'R$ '.number_format((float)$r['valor_liquido'],2,',','.'),
      'previsto'      => $r['previsto_em'] ? date('d/m/Y H:i', strtotime($r['previsto_em'])) : '-',
      'pago'          => $r['pago_em'] ? date('d/m/Y H:i', strtotime($r['pago_em'])) : '-',
      'codigo_lote'   => $r['codigo_lote'] ?: null,
      'raca'          => $r['raca'] ?: null,
      'peso_medio'    => $r['peso_medio_kg'] !== null ? number_format((float)$r['peso_medio_kg'],2,',','.') : null
    ];
  }
  $stmt->close();

  // ============ Fallback: não há repasse ainda -> itens do pedido ============
  if (!$temRepasse) {
    $sqlI = "
      SELECT
        pi.id AS pedido_item_id, pi.quantidade_cabecas, pi.preco_unitario_cab, pi.valor_total,
        lb.codigo_lote, lb.raca, lb.peso_medio_kg
      FROM pedido_itens pi
      LEFT JOIN lote_bois lb ON lb.id = pi.lote_id
      WHERE pi.pedido_id = ? AND pi.fazenda_id = ?
      ORDER BY pi.id ASC
    ";
    $stmt = $conn->prepare($sqlI);
    $stmt->bind_param('ii', $H['pedido_id'], $fazendaId);
    $stmt->execute();
    $resI = $stmt->get_result();

    while ($r = $resI->fetch_assoc()) {
      $totBruto += (float)$r['valor_total'];
      $totCabs  += (int)$r['quantidade_cabecas'];

      $itens[] = [
        'repasse_id'    => (int)$r['pedido_item_id'],
        'status_repasse'=> '—',
        'qtd'           => (int)$r['quantidade_cabecas'],
        'preco_unit'    => 'R$ '.number_format((float)$r['preco_unitario_cab'],2,',','.'),
        'valor_item'    => 'R$ '.number_format((float)$r['valor_total'],2,',','.'),
        'bruto'         => 'R$ '.number_format((float)$r['valor_total'],2,',','.'),
        'taxa_percent'  => '—',
        'taxa'          => '—',
        'liquido'       => '—',
        'previsto'      => '—',
        'pago'          => '—',
        'codigo_lote'   => $r['codigo_lote'] ?: null,
        'raca'          => $r['raca'] ?: null,
        'peso_medio'    => $r['peso_medio_kg'] !== null ? number_format((float)$r['peso_medio_kg'],2,',','.') : null
      ];
    }
    $stmt->close();
  }

  // ============ Totais ============
  $totais = [
    'bruto'   => 'R$ ' . number_format($totBruto,2,',','.'),
    'taxa'    => $temRepasse ? ('R$ ' . number_format($totTaxa,2,',','.')) : '—',
    'liquido' => $temRepasse ? ('R$ ' . number_format($totLiq,2,',','.'))  : '—',
    'cabecas' => (string)$totCabs,
  ];

  // ============ Endereço frigorífico ============
  $linha1 = implode(', ', array_filter([$H['rua'], $H['numero'], $H['bairro']]));
  $linha2 = implode(' - ', array_filter([$H['cidade'], $H['estado']]));
  $end = trim($linha1 . ($linha1&&$linha2?' • ':'') . $linha2 . ($H['cep'] ? (' • ' . $H['cep']) : ''));

  $frigNome = $H['frig_nome'] ?: ('Frigorífico #'.$H['frigorifico_id']);
  $frigCnpj = $H['frig_cnpj'] ?: ($H['cpf_responsavel'] ?? null);
  $frigTel  = $H['frig_tel'] ?: '—';

  out([
    'id'                => (int)$H['pagamento_id'],
    'pedido'            => (int)$H['pedido_id'],
    'status_raw'        => $H['status_pg'],
    'metodo'            => $H['metodo'],
    'valor'             => 'R$ ' . number_format((float)$H['valor'],2,',','.'),
    'moeda'             => $H['moeda'] ?: 'BRL',
    'referencia_externa'=> $H['referencia_externa'] ?: '-',
    'criado_em'         => $H['created_at'] ? date('d/m/Y H:i', strtotime($H['created_at'])) : '-',
    'confirmado_em'     => $H['confirmado_em'] ? date('d/m/Y H:i', strtotime($H['confirmado_em'])) : '-',

    'frigorifico' => [
      'nome'     => $frigNome,
      'email'    => $H['frig_email'] ?: '—',
      'cnpj'     => $frigCnpj ?: '—',
      'telefone' => $frigTel,
      'endereco' => $end ?: '—',
      'responsavel_legal' => $H['responsavel_legal'] ?: null,
      'cargo_responsavel' => $H['cargo_responsavel'] ?: null
    ],

    'totais' => $totais,
    'extra'  => $extra,
    'itens'  => $itens
  ]);

} catch (Throwable $e) {
  out(['erro' => 'Exceção: '.$e->getMessage()]);
}
