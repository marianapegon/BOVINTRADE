<?php
$current_page = basename($_SERVER['PHP_SELF']);
session_start();
require_once  'config.php'; // Ajuste o caminho se necessário

// --- Autenticação e Helpers ---
if (empty($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}
$u = $_SESSION['usuario'];
if (($u['tipo_usuario'] ?? '') !== 'TRANSPORTADORA') {
    if ($u['tipo_usuario'] === 'FAZENDA') { header('Location: 02-painel-fazenda.php'); exit; }
    if ($u['tipo_usuario'] === 'FRIGORIFICO') { header('Location: 07-painel-frigorifico.php'); exit; }
    header('Location: login.php');
    exit;
}
$nome_transportadora = htmlspecialchars($u['nome_razao'] ?? 'Transportadora');
$email = htmlspecialchars($u['email'] ?? '');
$transportadora_id = $u['id'];

// --- LÓGICA DE EXCLUSÃO (DESATIVAÇÃO) DE VEÍCULO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_veiculo') {
    $veiculo_id = (int)($_POST['veiculo_id'] ?? 0);
    
    // 1. Verifica se o veículo está em um transporte ativo
    $stmt_check_active = $pdo->prepare("
        SELECT 1 FROM transportes 
        WHERE veiculo_id = ? 
        AND transportadora_id = ?
        AND status NOT IN ('ENTREGUE', 'CANCELADO', 'FINALIZADO', 'RECUSADO')
        LIMIT 1
    ");
    $stmt_check_active->execute([$veiculo_id, $transportadora_id]);
    
    if ($stmt_check_active->fetch()) {
        $_SESSION['error_message'] = 'Não é possível excluir. Este veículo está atualmente em um transporte ativo.';
    } else {
        // 2. Procede com a desativação
        try {
            $pdo->beginTransaction();
            
            // Marca o veículo como inativo
            $stmt_veiculo = $pdo->prepare("UPDATE veiculo SET ativo = 0 WHERE id = ?");
            $stmt_veiculo->execute([$veiculo_id]);
            
            // Define a data de fim na tabela de vínculo
            $stmt_link = $pdo->prepare("
                UPDATE transportadora_veiculo 
                SET data_fim = CURDATE() 
                WHERE veiculo_id = ? AND transportadora_usuario_id = ?
            ");
            $stmt_link->execute([$veiculo_id, $transportadora_id]);
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Veículo desativado e removido da sua frota com sucesso!';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Erro ao excluir veículo: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Erro ao desativar o veículo. Tente novamente.';
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


// --- PROCESSAR EDIÇÃO (MESMO ARQUIVO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_veiculo'])) {
    $id = $_POST['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        $_SESSION['error_message'] = 'ID do veículo inválido.';
    } else {
        // Verifica se o veículo pertence à transportadora
        $stmt = $pdo->prepare("SELECT v.id FROM veiculo v 
                                  JOIN transportadora_veiculo tv ON v.id = tv.veiculo_id
                                  WHERE v.id = :id AND tv.transportadora_usuario_id = :tid LIMIT 1");
        $stmt->execute([':id' => $id, ':tid' => $transportadora_id]);
        
        if (!$stmt->fetch()) {
            $_SESSION['error_message'] = 'Veículo não encontrado ou sem permissão.';
        } else {
            // CAMPOS DO FORMULÁRIO (Corretos)
            $placa = trim($_POST['placa'] ?? '');
            $modelo = trim($_POST['modelo'] ?? '');
            $tipo = trim($_POST['tipo'] ?? '');
            $ano_fabricacao = (int)($_POST['ano_fabricacao'] ?? 0);
            $capacidade_min = (int)($_POST['capacidade_min'] ?? 0);
            $capacidade_max = (int)($_POST['capacidade_max'] ?? 0);
            $renavam = trim($_POST['renavam'] ?? '');
            $crlv_validade = empty($_POST['crlv_validade']) ? null : $_POST['crlv_validade'];

            if (empty($placa)) {
                $_SESSION['error_message'] = 'Placa é obrigatória.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Atualiza veículo
                    $sql = "UPDATE veiculo SET
                            placa = :placa, 
                            modelo = :modelo,
                            tipo = :tipo, 
                            ano_fabricacao = :ano_fabricacao,
                            capacidade_min = :capacidade_min,
                            capacidade_max = :capacidade_max,
                            renavam = :renavam,
                            crlv_validade = :crlv_validade
                            WHERE id = :id";
                    
                    $params = [
                        ':placa' => strtoupper($placa), // Padroniza para maiúscula
                        ':modelo' => $modelo,
                        ':tipo' => $tipo,
                        ':ano_fabricacao' => $ano_fabricacao,
                        ':capacidade_min' => $capacidade_min,
                        ':capacidade_max' => $capacidade_max,
                        ':renavam' => $renavam,
                        ':crlv_validade' => $crlv_validade,
                        ':id' => $id
                    ];

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // Processa semir reboques se tipo CARRETA
                    if ($tipo === 'CARRETA') {
                        // Deleta marcados
                        foreach ($_POST as $k => $v) {
                            if (preg_match('/^delete_sr_(\d+)$/', $k, $m)) {
                                $sr_id = (int)$m[1];
                                if (isset($_POST["delete_sr_{$sr_id}"]) && $_POST["delete_sr_{$sr_id}"] === 'on') {
                                    $stmt_del = $pdo->prepare("DELETE FROM semireboque WHERE id = ? AND veiculo_id = ?");
                                    $stmt_del->execute([$sr_id, $id]);
                                }
                            }
                        }

                        // Adiciona novos
                        for ($i = 1; $i <= 5; $i++) {
                            $new_sr_placa = strtoupper(trim($_POST["new_sr_placa_{$i}"] ?? ''));
                            $new_sr_modelo = trim($_POST["new_sr_modelo_{$i}"] ?? '');
                            if (!empty($new_sr_placa)) {
                                // Validação básica de placa
                                if (!preg_match('/^[A-Z]{3}[0-9][0-9A-Z][0-9]{2}$/', $new_sr_placa)) {
                                    throw new Exception("Placa de semir reboque inválida: {$new_sr_placa}");
                                }
                                // Verifica duplicidade global
                                $stmt_dup = $pdo->prepare("SELECT id FROM veiculo WHERE placa = ? UNION SELECT id FROM semireboque WHERE placa = ? LIMIT 1");
                                $stmt_dup->execute([$new_sr_placa, $new_sr_placa]);
                                if ($stmt_dup->fetch()) {
                                    throw new Exception("Placa de semir reboque já existe: {$new_sr_placa}");
                                }
                                // Insere
                                $stmt_ins = $pdo->prepare("INSERT INTO semireboque (veiculo_id, placa, modelo, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                                $stmt_ins->execute([$id, $new_sr_placa, $new_sr_modelo]);
                            }
                        }
                    }

                    $pdo->commit();
                    $_SESSION['success_message'] = 'Veículo e semir reboques atualizados com sucesso!';

                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log('Erro ao atualizar veículo: ' . $e->getMessage());
                    $_SESSION['error_message'] = 'Erro ao salvar os dados: ' . $e->getMessage();
                }
            }
        }
    }
    // Recarrega a página para evitar reenvio
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// --- Helpers ---
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function dtval($ts) {
    if (!$ts || strtotime($ts) === false || substr($ts, 0, 10) === '0000-00-00') return 'N/A';
    return date('d/m/Y', strtotime($ts));
}

// --- Busca de Dados ---
// Busca veículos da transportadora
$stmt_veiculos = $pdo->prepare("
    SELECT DISTINCT v.* FROM veiculo v
    JOIN transportadora_veiculo tv ON v.id = tv.veiculo_id
    WHERE tv.transportadora_usuario_id = :tid
    AND v.ativo = 1 -- Adicionado para não mostrar veículos desativados
    AND tv.data_fim IS NULL
    ORDER BY v.placa
");
$stmt_veiculos->execute([':tid' => $transportadora_id]);
$veiculos = $stmt_veiculos->fetchAll(PDO::FETCH_ASSOC);

// *** CORREÇÃO AQUI ***
// Busca veículos em transporte ativo
$stmt_ativos = $pdo->prepare("
    SELECT DISTINCT veiculo_id FROM transportes
    WHERE transportadora_id = :tid
    AND status NOT IN ('ENTREGUE', 'CANCELADO', 'FINALIZADO', 'RECUSADO') -- 'RECUSADO' ADICIONADO
    AND veiculo_id IS NOT NULL
");
$stmt_ativos->execute([':tid' => $transportadora_id]);
$active_vehicle_ids = $stmt_ativos->fetchAll(PDO::FETCH_COLUMN, 0);

// Busca de viagens recentes para aba Trips
$stmt_trips = $pdo->prepare("
    SELECT t.*, v.placa, m.nome as motorista_nome,
           f_origem.nome_razao as origem_nome, 
           fr_destino.nome_razao as destino_nome
    FROM transportes t
    JOIN veiculo v ON t.veiculo_id = v.id
    LEFT JOIN motorista m ON t.motorista_id = m.id
    LEFT JOIN usuarios f_origem ON t.fazenda_id = f_origem.id
    LEFT JOIN usuarios fr_destino ON t.frigorifico_id = fr_destino.id
    WHERE t.transportadora_id = :tid
    ORDER BY t.criado_em DESC
    LIMIT 10
");
$stmt_trips->execute([':tid' => $transportadora_id]);
$trips = $stmt_trips->fetchAll(PDO::FETCH_ASSOC);

// Buscar todos os motoristas ativos e seus veículos de UMA VEZ
$stmt_active_drivers = $pdo->prepare("
    SELECT 
        t.veiculo_id, 
        m.nome 
    FROM transportes t
    JOIN motorista m ON m.id = t.motorista_id
    WHERE t.transportadora_id = :tid
      AND t.status NOT IN ('ENTREGUE', 'CANCELADO', 'FINALIZADO', 'RECUSADO') -- 'RECUSADO' ADICIONADO
      AND t.veiculo_id IS NOT NULL
    GROUP BY t.veiculo_id  -- Garante um motorista por veículo
");
$stmt_active_drivers->execute([':tid' => $transportadora_id]);
// Cria um mapa: [veiculo_id => "Nome do Motorista"]
$active_drivers_map = $stmt_active_drivers->fetchAll(PDO::FETCH_KEY_PAIR);

// *** NOVO: Busca semireboques para veículos do tipo CARRETA ***
$semireboques_map = []; // Mapa: [veiculo_id => array de semireboques]
foreach ($veiculos as &$veiculo) {
    if ($veiculo['tipo'] === 'CARRETA') {
        $stmt_sr = $pdo->prepare("SELECT * FROM semireboque WHERE veiculo_id = ? ORDER BY created_at");
        $stmt_sr->execute([$veiculo['id']]);
        $semireboques = $stmt_sr->fetchAll(PDO::FETCH_ASSOC);
        $veiculo['semireboques'] = $semireboques;
        $semireboques_map[$veiculo['id']] = $semireboques;
    } else {
        $veiculo['semireboques'] = [];
    }
}
unset($veiculo); // Limpa a referência

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title>BovinTrade - Gerenciamento de Frota</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #a30000;
      --primary-light: #d43b3b;
      --primary-dark: #7a0000;
      --text: #333333;
      --text-light: #666666;
      --background: #ffffff;
      --border: #e0e0e0;
      --success: #4caf50;
      --warning: #ff9800;
      --info: #2196f3;
      --danger: #f44336;
      --danger-dark: #b00020;
      --bg-light: #f9f9f9;
    }
    *{ margin:0; padding:0; box-sizing:border-box; }
    body{ font-family:'Montserrat',sans-serif; background:#f9f9f9; color:var(--text); }
    header{ background:linear-gradient(135deg,var(--primary-dark),var(--primary)); color:white; padding:1.5rem 2rem; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    .logo{ font-size:1.8rem; font-weight:700; display:flex; align-items:center; gap:0.75rem; }
    .logo i{ font-size:1.6rem; }
    .hamburger { display: none; cursor: pointer; font-size: 1.5rem; color: white; }
    .user-menu{ display:flex; align-items:center; gap:1.5rem; }
    .user-menu span { color: white; font-weight: 500; font-size: 0.9rem; }
    .user-avatar{ width:40px; height:40px; border-radius:50%; background-color:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; }
    .container{ display:flex; min-height:calc(100vh - 76px); width: 100%; }
    .sidebar{ width:280px; background:var(--background); border-right:1px solid var(--border); padding:1.5rem 0; box-shadow:2px 0 8px rgba(0,0,0,0.05); flex-shrink:0; transition: transform 0.3s ease; }
    .resizer {
      width: 5px;
      background: var(--border);
      cursor: col-resize;
      height: 100%;
      display: flex;
      align-items: center;
    }
    .resizer:hover {
      background: var(--primary);
    }
    .sidebar-menu{ list-style:none; }
    .menu-item{ padding:0.8rem 1.5rem; display:flex; align-items:center; gap:0.75rem; color:var(--text); text-decoration:none; font-weight:500; border-left:3px solid transparent; transition:0.2s; }
    .menu-item i{ width:24px; text-align:center; color:var(--text-light); }
    .menu-item:hover{ background-color:rgba(163,0,0,0.05); color:var(--primary); border-left:3px solid var(--primary); }
    .menu-item.active{ background-color:rgba(163,0,0,0.1); color:var(--primary); border-left:3px solid var(--primary); }
    .main{ flex:1; padding:2.5rem; min-width:0; }
    .dashboard-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; flex-wrap: wrap;}
    .dashboard-title { font-size:1.8rem; font-weight:600; color:var(--text);}
    .dashboard-actions { display:flex; gap:1rem;}
    .btn { padding:0.75rem 1.5rem; border-radius:6px; font-weight:500; cursor:pointer; transition: all 0.2s; border:none; display:inline-flex; align-items:center; gap:0.5rem;}
    .btn-primary { background-color: var(--primary); color:white;}
    .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow:0 4px 8px rgba(163,0,0,0.2);}
    .btn-outline { background-color:transparent; color:var(--primary); border:1px solid var(--primary);}
    .btn-outline:hover { background-color: rgba(163,0,0,0.05);}
    .btn-danger{ background-color:var(--danger); color:white; }
    .btn-danger:hover{ background-color:var(--danger-dark); }
    .btn-sm{ padding:0.4rem 0.8rem; font-size:0.8rem; }
    .fleet-overview{ display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.2rem; margin-bottom:1.5rem; }
    .vehicle-card{ background-color:var(--background); border-radius:8px; overflow:hidden; box-shadow:0 3px 10px rgba(0,0,0,0.05); transition:transform 0.2s,box-shadow 0.2s; }
    .vehicle-card:hover{ transform:translateY(-3px); box-shadow:0 5px 12px rgba(0,0,0,0.1); }
    .vehicle-image{ height:100px; background-color:var(--bg-light); display:flex; align-items:center; justify-content:center; color:var(--text-light); position:relative; }
    .vehicle-status{ position:absolute; top:0.8rem; right:0.8rem; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:600; color:white; }
    .status-available{ background-color:var(--success); }
    .status-in-use{ background-color:var(--info); }
    .vehicle-details{ padding:1.2rem; }
    .vehicle-title{ font-size:1.1rem; font-weight:600; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center; }
    .vehicle-plate{ background-color:rgba(163,0,0,0.1); color:var(--primary); padding:0.2rem 0.6rem; border-radius:4px; font-weight:700; font-size:0.8rem; }
    .vehicle-specs{ display:flex; gap:0.8rem; margin-bottom:0.8rem; flex-wrap:wrap; }
    .vehicle-spec{ font-size:0.8rem; color:var(--text-light); }
    .vehicle-spec strong{ color:var(--text); font-weight:500; }
    .vehicle-driver{ margin-top:0.8rem; padding-top:0.8rem; border-top:1px solid var(--border); }
    .driver-info{ display:flex; align-items:center; gap:0.6rem; }
    .driver-avatar{ width:36px; height:36px; border-radius:50%; background-color:rgba(163,0,0,0.1); display:flex; align-items:center; justify-content:center; color:var(--primary); }
    .driver-name{ font-weight:500; font-size:0.9rem; }
    .driver-type{ font-size:0.7rem; color:var(--text-light); }
    .vehicle-actions{ display:flex; gap:0.5rem; margin-top:1rem; flex-wrap: wrap;}
    .tabs{ display:flex; border-bottom:1px solid var(--border); margin-bottom:1.5rem; }
    .tab{ padding:0.6rem 1.2rem; cursor:pointer; font-weight:500; font-size:0.9rem; color:var(--text-light); border-bottom:3px solid transparent; transition:all 0.2s; }
    .tab:hover{ color:var(--primary); }
    .tab.active{ color:var(--primary); border-bottom:3px solid var(--primary); }
    .tab-content{ display:none; }
    .tab-content.active{ display:block; }
    .documents-list{ display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:1.2rem; }
    .document-card{ background-color:var(--background); border-radius:8px; padding:1.2rem; box-shadow:0 3px 10px rgba(0,0,0,0.05); }
    .document-title{ font-weight:600; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center; }
    .document-expiry{ font-size:0.9rem; color:var(--text-light); margin-bottom:0.8rem; }
    .document-expiry strong{ color:var(--text); font-weight:500; }
    .document-status{ display:inline-block; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:600; text-transform:uppercase; }
    .status-valid{ background-color:rgba(76,175,80,0.1); color:var(--success); }
    .status-expired{ background-color:rgba(244,67,54,0.1); color:var(--danger); }
    .status-warning{ background-color:rgba(255,152,0,0.1); color:var(--warning); }
    .status-na{ background-color:#eee; color:var(--text-light); }
    .document-actions{ margin-top:1rem; display:flex; gap:0.5rem; }
    .trips-table{ width:100%; border-collapse:collapse; margin-top:1rem; }
    .trips-table th,.trips-table td{ padding:0.8rem; text-align:left; border-bottom:1px solid var(--border); }
    .trips-table th{ background-color:var(--bg-light); font-weight:600; }
    .trip-status{ padding:0.2rem 0.6rem; border-radius:20px; font-size:0.7rem; font-weight:600; color:white; }
    .status-entregue,.status-completed{ background:var(--success); }
    .status-em_transito_origem, .status-em_transito_destino, .status-chegou_na_fazenda, .status-chegou_no_frigorifico, .status-autorizado, .status-agendado, .status-confirmado { background:var(--info); }
    .status-cancelado{ background:var(--danger); }
    .empty-state{ text-align:center; padding:3rem; background:var(--background); border-radius:8px; box-shadow:0 3px 10px rgba(0,0,0,0.05); color:var(--text-light); }
    .empty-state i{ font-size:2rem; margin-bottom:1rem; }
    .alert{ padding:1rem; border-radius:5px; margin-bottom:1rem; }
    .alert-success{ background:#d4edda; color:#155724; }
    .alert-error{ background:#f8d7da; color:#721c24; }
    .semireboques-list { margin-top: 0.5rem; padding: 0.5rem; background: rgba(163,0,0,0.05); border-radius: 4px; }
    .semireboque-item { font-size: 0.8rem; margin-bottom: 0.25rem; display: flex; justify-content: space-between; align-items: center; }
    .semireboque-placa { font-weight: 600; color: var(--primary); }
    .semireboque-modelo { color: var(--text-light); }
    #secao-semireboques-edit { margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; border: 1px solid var(--border); display: none; }
    .sr-existing-item { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem; padding: 0.5rem; background: white; border: 1px solid var(--border); border-radius: 4px; }
    .sr-new-item { display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.5rem; margin-bottom: 0.5rem; padding: 0.5rem; background: white; border: 1px solid var(--border); border-radius: 4px; }
    .sr-new-item input { padding: 0.25rem; border: 1px solid var(--border); border-radius: 3px; }
    .sr-new-item button { background: var(--danger); color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 3px; cursor: pointer; font-size: 0.8rem; }
    @media (max-width: 768px) {
      .hamburger { display: block; }
      .user-menu { gap: 1rem; }
      .user-menu span { display: none; }
      .container {
        flex-direction: column;
      }
      .sidebar {
        width: 100%;
        transform: translateX(-100%);
        position: fixed;
        top: 76px;
        left: 0;
        height: calc(100vh - 76px);
        z-index: 1000;
        overflow-y: auto;
        box-shadow: none;
        border-right: none;
      }
      .sidebar.active {
        transform: translateX(0);
      }
      .resizer {
        display: none;
      }
      .main {
        padding: 1rem;
        width: 100%;
      }
      .fleet-overview,.documents-list{ grid-template-columns:1fr; }
      .dashboard-header{ flex-direction:column; align-items:flex-start; gap:1rem; }
      .trips-table{ display:block; overflow-x:auto; }
      .sr-new-item { grid-template-columns: 1fr; }
    }
    @media (max-width:480px){ header{ padding:1rem; } .logo{ font-size:1.5rem; } .user-menu span{ display:none; } .main{ padding:0.8rem; } .vehicle-actions,.document-actions{ flex-direction:column; } }
    .modal{ display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
    .modal-content{ background-color:var(--background); margin:5% auto; padding:0; border-radius:8px; width:90%; max-width:600px; max-height:80vh; overflow-y:auto; box-shadow:0 4px 20px rgba(0,0,0,0.3); }
    .modal-header{ padding:1rem; background-color:var(--primary); color:white; display:flex; justify-content:space-between; align-items:center; border-radius:8px 8px 0 0; }
    .modal-header-danger { background-color: var(--danger); }
    .close{ color:white; font-size:28px; font-weight:bold; cursor:pointer; }
    .close:hover{ opacity:0.7; }
    .modal-body{ padding:1.5rem; }
    .modal-body .form-group{ margin-bottom:1rem; }
    .modal-body label{ display:block; margin-bottom:0.5rem; font-weight:500; }
    .modal-body input,.modal-body textarea, .modal-body select { width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:4px; font-family: 'Montserrat', sans-serif; }
    .modal-body .form-row { display: flex; gap: 1rem; }
    .modal-body .form-row .form-group { flex: 1; }
    .modal-footer{ padding:1rem; text-align:right; border-top:1px solid var(--border); }
    .vehicle-details-modal .details-section{ margin-bottom:1.5rem; }
    .vehicle-details-modal .details-row{ display:flex; justify-content:space-between; margin-bottom:0.5rem; padding: 0.25rem 0; border-bottom: 1px solid #f0f0f0;}
    .vehicle-details-modal .details-row:last-child { border-bottom: none; }
    .vehicle-details-modal .details-label{ font-weight:500; color:var(--text-light); }
    .semireboques-modal { margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 6px; border-left: 3px solid var(--primary); }
    .semireboques-modal h4 { margin-bottom: 0.5rem; color: var(--primary); }
    .semireboque-modal-item { display: flex; justify-content: space-between; margin-bottom: 0.5rem; padding: 0.5rem; background: white; border-radius: 4px; border: 1px solid var(--border); font-size: 0.9rem; }
  </style>
</head>
<body>
<header>
  <div style="display: flex; align-items: center; gap: 1rem;">
    <div class="logo">
      🐄
      <span>BovinTrade • Transportadora</span>
    </div>
    <div class="hamburger" onclick="toggleSidebar()">
      <i class="fas fa-bars"></i>
    </div>
  </div>
  <div class="user-menu">
    <span><?php echo $email; ?></span>
    <form action="logout.php" method="post" style="display:inline;">
      <button type="submit" style="background:none; border:none; color:white; cursor:pointer;">Sair</button>
    </form>
    <div class="user-avatar"><i class="fas fa-user"></i></div>
  </div>
</header>
<div class="container">
  <aside class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
      <a href="14-painel-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === '14-painel-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Painel</span></a>
      <a href="cadastro-transporte.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'cadastro-transporte.php' ? 'active' : ''; ?>"><i class="fas fa-plus-square"></i><span>Cadastrar Transporte</span></a>
      <a href="cadastro-motorista.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'cadastro-motorista.php' ? 'active' : ''; ?>"><i class="fas fa-user"></i><span>Cadastrar Motorista</span></a>
       <a href="gerenciar-motoristas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'gerenciar-motoristas.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>Gerenciar Motoristas</span></a>
      <a href="gerenciar-transportes-transp.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'gerenciar-transportes-transp.php' ? 'active' : ''; ?>"><i class="fas fa-truck-front"></i><span>Gerenciar Frota</span></a>
      <a href="pedidos-transportes.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'pedidos-transportes.php' ? 'active' : ''; ?>"><i class="fas fa-handshake"></i><span>Negociações / Pedidos</span></a>
      <a href="coletas-agendadas.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'coletas-agendadas.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i><span>Coletas Agendadas</span></a>
      <a href="rastreamento-transporte-t.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'rastreamento-transporte-t.php' ? 'active' : ''; ?>"><i class="fas fa-truck-loading"></i><span>Rastreamento Transportes</span></a>
      <a href="historico-transporte-t.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'historico-transporte-t.php' ? 'active' : ''; ?>"><i class="fas fa-truck"></i><span>Histórico Transportes</span></a>
      <a href="notificacoes-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'notificacoes-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-bell"></i><span>Notificações</span></a>
      <a href="minhas-avaliacoes-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'minhas-avaliacoes-transportadora.php' ? 'active' : ''; ?>"><i class="fas fa-star"></i><span>Avaliações</span></a>
      <a href="17-ajudat.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === '17-ajudat.php' ? 'active' : ''; ?>"><i class="fas fa-question-circle"></i><span>Ajuda / Suporte</span></a>
      <a href="meu-perfil-transportadora.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) === 'meu-perfil-transportadora.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-circle"></i><span>Meu Perfil</span>
      </a>
    </ul>
  </aside>
  <div class="resizer"></div>
  <main class="main">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    <div class="dashboard-header">
      <h1 class="dashboard-title"><i class="fas fa-truck-front"></i> Gerenciamento de Frota</h1>
      <div class="dashboard-actions">
        <button class="btn btn-outline" onclick="exportarFrota()">
          <i class="fas fa-download"></i> Exportar
        </button>
        <a href="cadastro-transporte.php" class="btn btn-primary">
          <i class="fas fa-plus"></i> Adicionar Veículo
        </a>
      </div>
    </div>
    <div class="tabs">
      <div class="tab active" data-tab="overview">Visão Geral</div>
      <div class="tab" data-tab="documents">Documentação (CRLV)</div>
      <div class="tab" data-tab="trips">Viagens Recentes</div>
    </div>
    <div class="tab-content active" id="overview">
      <?php if (count($veiculos) === 0): ?>
          <div class="empty-state">
              <i class="fas fa-truck"></i>
              <p>Nenhum veículo cadastrado ainda.</p>
              <p><a href="cadastro-transpote.php">Clique aqui</a> para cadastrar seu primeiro veículo.</p>
          </div>
      <?php else: ?>
          <div class="fleet-overview">
          <?php 
          // O $active_drivers_map já foi carregado antes do HTML
          foreach($veiculos as $veiculo): 
              
              // *** CORREÇÃO AQUI: Busca o motorista no mapa ***
              $driver_name = $active_drivers_map[$veiculo['id']] ?? null; 
              
              // *** LÓGICA DE STATUS CORRIGIDA ***
              $is_active = in_array($veiculo['id'], $active_vehicle_ids);
              $status_class = $is_active ? 'status-in-use' : 'status-available';
              $status_label = $is_active ? 'Em uso' : 'Disponível';
              
              // Prepara o JSON para os modais, usando os nomes de coluna corretos
              $veiculo_display = $veiculo;
              $veiculo_display['driver_name'] = $driver_name; // Adiciona o nome do motorista ao JSON
              // (Os nomes das colunas já estão corretos vindos do DB)
              
              $veiculo_json = htmlspecialchars(json_encode($veiculo_display), ENT_QUOTES, 'UTF-8');
          ?>
              <div class="vehicle-card">
                <div class="vehicle-image">
                  <i class="fas fa-truck" style="font-size: 3rem; color: var(--primary-light);"></i>
                  <span class="vehicle-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                </div>
                <div class="vehicle-details">
                  <div class="vehicle-title">
                    <span><?php echo e($veiculo['modelo'] ?? $veiculo['placa']); ?></span>
                    <span class="vehicle-plate"><?php echo e($veiculo['placa']); ?></span>
                  </div>
                  <div class="vehicle-specs">
                    <div class="vehicle-spec">
                      <strong>Tipo:</strong> <?php echo e($veiculo['tipo']); ?>
                    </div>
                    <div class="vehicle-spec">
                      <strong>Ano:</strong> <?php echo e($veiculo['ano_fabricacao']); ?>
                    </div>
                  </div>
                  <div class="vehicle-spec">
                    <strong>Capacidade:</strong> <?php echo e($veiculo['capacidade_min']); ?> a <?php echo e($veiculo['capacidade_max']); ?> cabeças
                  </div>
                  <?php if (!empty($veiculo['semireboques'])): ?>
                  <div class="semireboques-list">
                    <strong>Semir reboques (<?php echo count($veiculo['semireboques']); ?>):</strong>
                    <?php foreach ($veiculo['semireboques'] as $sr): ?>
                      <div class="semireboque-item">
                        <span class="semireboque-placa"><?php echo e($sr['placa']); ?></span>
                        <span class="semireboque-modelo"><?php echo e($sr['modelo'] ?? 'N/A'); ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                  <div class="vehicle-driver">
                    <div class="driver-info">
                      <div class="driver-avatar">
                        <i class="fas fa-user"></i>
                      </div>
                      <div>
                        <div class="driver-name"><?php echo e($driver_name ?: '-'); ?></div>
                        <div class="driver-type"><?php echo $driver_name ? 'Motorista em Coleta' : 'Sem motorista'; ?></div>
                      </div>
                    </div>
                  </div>
                  <div class="vehicle-actions">
                    <button class="btn btn-outline btn-sm edit-btn" data-id="<?php echo $veiculo['id']; ?>" data-veiculo="<?php echo $veiculo_json; ?>">
                      <i class="fas fa-edit"></i> Editar
                    </button>
                    <button class="btn btn-primary btn-sm details-btn" data-veiculo="<?php echo $veiculo_json; ?>">
                      <i class="fas fa-info-circle"></i> Detalhes
                    </button>
                    <button class="btn btn-danger btn-sm delete-btn" data-id="<?php echo $veiculo['id']; ?>" data-veiculo="<?php echo $veiculo_json; ?>">
                      <i class="fas fa-trash"></i> Excluir
                    </button>
                  </div>
                </div>
              </div>
          <?php endforeach; ?>
          </div>
      <?php endif; ?>
    </div>
    <div class="tab-content" id="documents">
      <?php if (count($veiculos) === 0): ?>
           <div class="empty-state">
              <i class="fas fa-id-card"></i>
              <p>Nenhum veículo cadastrado para verificar a documentação.</p>
          </div>
      <?php else: ?>
          <div class="documents-list">
          <?php 
          foreach($veiculos as $veiculo): 
              $status_class = 'status-na';
              $status_label = 'Não Informada';
              $validade_label = 'N/A';
              
              $crlv_validade = $veiculo['crlv_validade'] ?? null; 

              if (!empty($crlv_validade) && substr($crlv_validade, 0, 10) !== '0000-00-00') {
                  $validade_dt = new DateTime(substr($crlv_validade, 0, 10));
                  $hoje = new DateTime(date('Y-m-d'));
                  $diff = $hoje->diff($validade_dt);
                  $dias_restantes = (int)$diff->format('%r%a');
                  $validade_label = $validade_dt->format('d/m/Y');
                  if ($dias_restantes < 0) {
                      $status_class = 'status-expired';
                      $status_label = 'Vencida';
                  } elseif ($dias_restantes <= 30) {
                      $status_class = 'status-warning';
                      $status_label = 'Vence em ' . $dias_restantes . ' dias';
                  } else {
                      $status_class = 'status-valid';
                      $status_label = 'Válida';
                  }
              }
              $veiculo_display = $veiculo; // Passa o $veiculo original
              $veiculo_json = htmlspecialchars(json_encode($veiculo_display), ENT_QUOTES, 'UTF-8');
              ?>
              <div class="document-card">
                <div class="document-title">
                  <span>CRLV - <?php echo e($veiculo['modelo'] ?? $veiculo['placa']); ?> (<?php echo e($veiculo['placa']); ?>)</span>
                  <span class="document-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                </div>
                <div class="document-expiry">
                  <strong>Validade:</strong> <?php echo $validade_label; ?>
                </div>
                 <div class="document-expiry">
                  <strong>Tipo:</strong> <?php echo e($veiculo['tipo']); ?> | <strong>Capacidade:</strong> <?php echo e($veiculo['capacidade_min']); ?> a <?php echo e($veiculo['capacidade_max']); ?> cabeças
                </div>
                <?php if (!empty($veiculo['semireboques'])): ?>
                <div class="semireboques-list">
                  <strong>Semir reboques (<?php echo count($veiculo['semireboques']); ?>):</strong>
                  <?php foreach ($veiculo['semireboques'] as $sr): ?>
                    <div class="semireboque-item">
                      <span class="semireboque-placa"><?php echo e($sr['placa']); ?></span>
                      <span class="semireboque-modelo"><?php echo e($sr['modelo'] ?? 'N/A'); ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="document-actions">
                  <button class="btn btn-primary btn-sm edit-btn" data-id="<?php echo $veiculo['id']; ?>" data-veiculo="<?php echo $veiculo_json; ?>">
                    <i class="fas fa-edit"></i> Atualizar / Renovar
                  </button>
                </div>
              </div>
          <?php endforeach; ?>
          </div>
      <?php endif; ?>
    </div>
    <div class="tab-content" id="trips">
      <?php if (count($trips) === 0): ?>
          <div class="empty-state">
              <i class="fas fa-road"></i>
              <p>Nenhuma viagem recente.</p>
          </div>
      <?php else: ?>
          <table class="trips-table">
            <thead>
              <tr>
                <th>Veículo</th>
                <th>Motorista</th>
                <th>Origem</th>
                <th>Destino</th>
                <th>Data</th>
                <th>Status</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($trips as $trip): ?>
              <tr>
                <td><?php echo e($trip['placa']); ?></td>
                <td><?php echo e($trip['motorista_nome'] ?? '-'); ?></td>
                <td><?php echo e($trip['origem_nome'] ?? 'N/A'); ?></td>
                <td><?php echo e($trip['destino_nome'] ?? 'N/A'); ?></td>
                <td><?php echo dtval($trip['criado_em']); ?></td> 
                <td><span class="trip-status status-<?php echo strtolower(str_replace(' ', '_', $trip['status'])); ?>"><?php echo e($trip['status']); ?></span></td>
                <td>
                  <?php $trip_json = htmlspecialchars(json_encode($trip), ENT_QUOTES, 'UTF-8'); ?>
                  <button class="btn btn-outline btn-sm trip-details-btn" data-trip="<?php echo $trip_json; ?>">
                    <i class="fas fa-eye"></i> Ver
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
      <?php endif; ?>
    </div>
  </main>
</div>
<div id="detailsModal" class="modal vehicle-details-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="detailsTitle">Detalhes do Veículo</h2>
      <span class="close" onclick="closeModal('detailsModal')">&times;</span>
    </div>
    <div class="modal-body">
      <div id="detailsContent"></div>
    </div>
  </div>
</div>
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="editTitle">Editar Veículo</h2>
      <span class="close" onclick="closeModal('editModal')">&times;</span>
    </div>
    <div class="modal-body">
      <form id="editForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <input type="hidden" name="edit_veiculo" value="1">
        <input type="hidden" id="editId" name="id">
        
        <div class="form-row">
            <div class="form-group">
                <label for="editPlaca">Placa:</label>
                <input type="text" id="editPlaca" name="placa" required>
            </div>
            <div class="form-group">
                <label for="editModelo">Modelo:</label>
                <input type="text" id="editModelo" name="modelo">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="editTipo">Tipo:</label>
                <select id="editTipo" name="tipo" onchange="toggleSemireboquesEdit()">
                    <option value="BOIADEIRO">Boiadeiro</option>
                    <option value="CARRETA">Carreta</option>
                    <option value="TRUCK">Truck</option>
                    <option value="CAMINHAO 3/4">Caminhão 3/4</option>
                    <option value="VAN">Van</option>
                    <option value="OUTRO">Outro</option>
                </select>
            </div>
            <div class="form-group">
                <label for="editAnoFabricacao">Ano Fabricação:</label>
                <input type="number" id="editAnoFabricacao" name="ano_fabricacao" min="1900" max="2030">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="editCapacidadeMin">Capacidade Mínima:</label>
                <input type="number" id="editCapacidadeMin" name="capacidade_min" min="0">
            </div>
            <div class="form-group">
                <label for="editCapacidadeMax">Capacidade Máxima:</label>
                <input type="number" id="editCapacidadeMax" name="capacidade_max" min="1">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="editRenavam">RENAVAM:</label>
                <input type="text" id="editRenavam" name="renavam">
            </div>
            <div class="form-group">
                <label for="editCrlvValidade">Validade CRLV:</label>
                <input type="date" id="editCrlvValidade" name="crlv_validade">
            </div>
        </div>

        <div id="secao-semireboques-edit">
            <h4><i class="fas fa-trailer"></i> Gerenciar Semir reboques</h4>
            <p style="color: var(--text-light); font-size: 0.9rem; margin-bottom: 1rem;">Marque para excluir existentes e adicione novos se necessário.</p>
            <div id="container-semireboques-edit"></div>
            <button type="button" class="btn btn-outline btn-sm" onclick="adicionarNovoSemireboqueEdit()" style="margin-top: 0.5rem;"><i class="fas fa-plus"></i> Adicionar Novo Semir reboque</button>
            <p style="font-size: 0.85rem; color: var(--text-light); margin-top: 0.5rem;">Máximo de 5 semir reboques.</p>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancelar</button>
      <button type="submit" form="editForm" class="btn btn-primary">Salvar Alterações</button>
    </div>
  </div>
</div>

<div id="tripDetailsModal" class="modal vehicle-details-modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="tripDetailsTitle">Detalhes da Viagem</h2>
      <span class="close" onclick="closeModal('tripDetailsModal')">&times;</span>
    </div>
    <div class="modal-body">
      <div id="tripDetailsContent"></div>
    </div>
  </div>
</div>

<div id="deleteVehicleModal" class="modal">
  <div class="modal-content">
    <div class="modal-header modal-header-danger">
      <h2>Confirmar Exclusão</h2>
      <span class="close" onclick="closeModal('deleteVehicleModal')">&times;</span>
    </div>
    <div class="modal-body">
      <p>Tem certeza que deseja desativar o veículo <strong id="deleteVehicleName"></strong>?</p>
      <p style="font-size: 0.9rem; color: var(--text-light); margin-top: 1rem;">
        Esta ação irá marcá-lo como inativo e removê-lo da sua frota. 
        Ele não poderá ser selecionado para novos transportes. O histórico dele será mantido.
      </p>
      <form id="deleteVehicleForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <input type="hidden" name="acao" value="excluir_veiculo">
        <input type="hidden" id="deleteVehicleId" name="veiculo_id">
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('deleteVehicleModal')">Cancelar</button>
      <button type="submit" form="deleteVehicleForm" class="btn btn-danger">Sim, Desativar</button>
    </div>
  </div>
</div>

<script>
  // Função para alternar a sidebar em dispositivos móveis
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', function() {
    // Lógica de fechamento do sidebar em mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const hamburger = document.querySelector('.hamburger');
        if (sidebar && hamburger && sidebar.classList.contains('active') && !hamburger.contains(event.target) && !sidebar.contains(event.target)) {
            sidebar.classList.remove('active');
        }
    });
    
    // Resizer functionality (copiado do exemplo para a barra lateral redimensionável)
    let isResizing = false;
    const resizer = document.querySelector('.resizer');
    const sidebar = document.querySelector('.sidebar');
    const container = document.querySelector('.container');
    
    // Só adiciona funcionalidade de redimensionamento em telas maiores
    if (window.innerWidth > 768 && resizer) {
        resizer.addEventListener('mousedown', function(e) {
            e.preventDefault();
            isResizing = true;
            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResize);
            container.style.cursor = 'col-resize';
        });
    }

    function resize(e) {
        if (!isResizing) return;
        let newWidth = e.clientX - sidebar.getBoundingClientRect().left;
        if (newWidth < 200) newWidth = 200;
        let maxWidth = window.innerWidth - 100;
        if (newWidth > maxWidth / 2) newWidth = maxWidth / 2; 
        sidebar.style.width = newWidth + 'px';
    }

    function stopResize() {
        isResizing = false;
        document.removeEventListener('mousemove', resize);
        document.removeEventListener('mouseup', stopResize);
        container.style.cursor = '';
    }
});
  // Exportar CSV (Corrigido)
  function exportarFrota() {
      const veiculos = <?php echo json_encode($veiculos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const activeIds = <?php echo json_encode($active_vehicle_ids); ?>;
      if (veiculos.length === 0) {
          alert('Nenhum veículo para exportar.');
          return;
      }
      let csv = 'Placa,Modelo,Tipo,Ano Fabricacao,Capacidade Min,Capacidade Max,Renavam,Validade CRLV,Status\n';
      
      veiculos.forEach(v => {
          const status = activeIds.includes(v.id) ? 'Em uso' : 'Disponível';
          const validade = v.crlv_validade && v.crlv_validade !== '0000-00-00'
              ? new Date(v.crlv_validade + 'T00:00:00').toLocaleDateString('pt-BR', {timeZone: 'UTC'})
              : 'N/A';
          
          csv += [
              `"${(v.placa || '').replace(/"/g, '""')}"`,
              `"${(v.modelo || 'N/A').replace(/"/g, '""')}"`,
              `"${(v.tipo || '').replace(/"/g, '""')}"`,
              `"${v.ano_fabricacao || ''}"`,
              `"${v.capacidade_min || ''}"`,
              `"${v.capacidade_max || ''}"`,
              `"${(v.renavam || 'N/A').replace(/"/g, '""')}"`,
              `"${validade}"`,
              `"${status}"`
          ].join(',') + '\n';
      });

      const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', 'frota_' + new Date().toISOString().slice(0,10) + '.csv');
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
  }
  
  // Tabs
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.tab).classList.add('active');
    });
  });
  
  // --- FUNÇÕES DE MODAL ---

  // Modal Detalhes do Veículo (Corrigido)
  function openDetailsModal(data) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');
    const title = document.getElementById('detailsTitle');
    
    let modalTitle = data.modelo || data.placa;
    title.textContent = 'Detalhes de ' + modalTitle;
    
    const validadeCRLV = data.crlv_validade ? new Date(data.crlv_validade + 'T00:00:00').toLocaleDateString('pt-BR', {timeZone: 'UTC'}) : 'N/A';

    let semireboquesHtml = '';
    if (data.semireboques && data.semireboques.length > 0) {
      semireboquesHtml = `
        <div class="semireboques-modal">
          <h4><i class="fas fa-trailer"></i> Semir reboques Vinculados</h4>
          ${data.semireboques.map(sr => `
            <div class="semireboque-modal-item">
              <span><strong>Placa:</strong> ${sr.placa}</span>
              <span><strong>Modelo:</strong> ${sr.modelo || 'N/A'}</span>
            </div>
          `).join('')}
        </div>
      `;
    }

    content.innerHTML = `
      <div class="details-section">
        <h3><i class="fas fa-truck"></i> Informações do Veículo</h3>
        <div class="details-row"><span class="details-label">Modelo:</span><span>${data.modelo || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Placa:</span><span>${data.placa || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Tipo:</span><span>${data.tipo || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Ano Fabricação:</span><span>${data.ano_fabricacao || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Capacidade Mín:</span><span>${data.capacidade_min || 'N/A'} cabeças</span></div>
        <div class="details-row"><span class="details-label">Capacidade Máx:</span><span>${data.capacidade_max || 'N/A'} cabeças</span></div>
        ${semireboquesHtml}
      </div>
      <div class="details-section">
        <h3><i class="fas fa-id-card"></i> Documentos</h3>
        <div class="details-row"><span class="details-label">Renavam:</span><span>${data.renavam || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Validade CRLV:</span><span>${validadeCRLV}</span></div>
      </div>
      <div class="details-section">
        <h3><i class="fas fa-user"></i> Motorista Atribuído</h3>
        <div class="details-row"><span class="details-label">Nome:</span><span>${data.driver_name || '-'}</span></div>
      </div>
    `;
    modal.style.display = 'block';
  }
  
  // Modal Editar Veículo (Corrigido com Semir reboques)
  function openEditModal(id, data) {
    const modal = document.getElementById('editModal');
    const title = document.getElementById('editTitle');
    
    let modalTitle = data.modelo || data.placa;
    title.textContent = 'Editar ' + modalTitle;
    
    document.getElementById('editId').value = id;
    document.getElementById('editPlaca').value = data.placa || '';
    document.getElementById('editModelo').value = data.modelo || '';
    document.getElementById('editTipo').value = data.tipo || '';
    document.getElementById('editAnoFabricacao').value = data.ano_fabricacao || '';
    document.getElementById('editCapacidadeMin').value = data.capacidade_min || '';
    document.getElementById('editCapacidadeMax').value = data.capacidade_max || '';
    document.getElementById('editRenavam').value = data.renavam || '';
    
    let validadeVal = data.crlv_validade ? data.crlv_validade.split(' ')[0] : '';
    document.getElementById('editCrlvValidade').value = validadeVal;
    
    // Gerencia semir reboques
    const secaoSr = document.getElementById('secao-semireboques-edit');
    const containerSr = document.getElementById('container-semireboques-edit');
    containerSr.innerHTML = '';
    let contadorNewSr = 0;
    
    if (data.tipo === 'CARRETA') {
        secaoSr.style.display = 'block';
        
        // Existing semireboques
        if (data.semireboques && data.semireboques.length > 0) {
            data.semireboques.forEach(sr => {
                const item = document.createElement('div');
                item.className = 'sr-existing-item';
                item.innerHTML = `
                    <input type="checkbox" name="delete_sr_${sr.id}" id="delete_${sr.id}">
                    <label for="delete_${sr.id}" style="margin-right: 0.5rem;">Excluir</label>
                    <span>Placa: <strong>${sr.placa}</strong> | Modelo: ${sr.modelo || 'N/A'}</span>
                `;
                containerSr.appendChild(item);
            });
        } else {
            adicionarNovoSemireboqueEdit();
        }
    } else {
        secaoSr.style.display = 'none';
    }
    
    modal.style.display = 'block';
  }

  function toggleSemireboquesEdit() {
    const tipo = document.getElementById('editTipo').value;
    const secao = document.getElementById('secao-semireboques-edit');
    const container = document.getElementById('container-semireboques-edit');
    
    if (tipo === 'CARRETA') {
        secao.style.display = 'block';
        if (container.children.length === 0) {
            adicionarNovoSemireboqueEdit();
        }
    } else {
        secao.style.display = 'none';
    }
  }

  let contadorNewSrEdit = 1;
  function adicionarNovoSemireboqueEdit() {
    if (contadorNewSrEdit >= 5) {
        alert('Máximo de 5 semir reboques permitidos.');
        return;
    }
    const container = document.getElementById('container-semireboques-edit');
    const item = document.createElement('div');
    item.className = 'sr-new-item';
    item.innerHTML = `
        <input type="text" name="new_sr_placa_${contadorNewSrEdit}" placeholder="Placa do Semir reboque" maxlength="7" required>
        <input type="text" name="new_sr_modelo_${contadorNewSrEdit}" placeholder="Modelo (opcional)">
        <button type="button" onclick="removerNovoSemireboqueEdit(this)" title="Remover"><i class="fas fa-trash"></i></button>
    `;
    container.appendChild(item);
    contadorNewSrEdit++;
  }

  function removerNovoSemireboqueEdit(btn) {
    btn.closest('.sr-new-item').remove();
    // Reindexa nomes se necessário, mas como são sequenciais, ok
  }

  // --- NOVA FUNÇÃO PARA MODAL DE EXCLUIR VEÍCULO ---
  function openDeleteVehicleModal(id, data) {
    document.getElementById('deleteVehicleName').textContent = (data.modelo || data.placa) + ' (Placa: ' + data.placa + ')';
    document.getElementById('deleteVehicleId').value = id;
    document.getElementById('deleteVehicleModal').style.display = 'block';
  }

  // =========================================================================
  // FUNÇÃO MODIFICADA - CÁLCULO DINÂMICO DO FRETE
  // =========================================================================
  function openTripDetailsModal(data) {
    const modal = document.getElementById('tripDetailsModal');
    const content = document.getElementById('tripDetailsContent');
    const title = document.getElementById('tripDetailsTitle');
    
    title.textContent = 'Detalhes da Viagem #' + (data.id || 'N/A');
    
    // Formatar datas
    const dataCriacao = data.criado_em ? new Date(data.criado_em).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A';
    const dataRetirada = data.data_retirada ? new Date(data.data_retirada + 'T00:00:00').toLocaleDateString('pt-BR', {timeZone: 'UTC'}) : 'N/A';
    const dataPrevEntrega = data.data_prevista_entrega ? new Date(data.data_prevista_entrega + 'T00:00:00').toLocaleDateString('pt-BR', {timeZone: 'UTC'}) : 'N/A';
    const dataEntregaReal = data.data_entrega_real ? new Date(data.data_entrega_real + 'T00:00:00').toLocaleDateString('pt-BR', {timeZone: 'UTC'}) : 'N/A';

    // --- CÓDIGO MODIFICADO ---
    // Calcula dinamicamente o frete com base na distância
    let valorFreteFormatado = 'N/A'; // Define um valor padrão
    const distancia = parseFloat(data.distancia_km);
    const valorPorKm = 5.50;

    // Verifica se a distância é um número válido e maior que zero
    if (distancia && distancia > 0) {
        const freteCalculado = distancia * valorPorKm;
        
        // Formata o valor para o padrão de moeda brasileiro (R$)
        valorFreteFormatado = freteCalculado.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }
    // --- FIM DA MODIFICAÇÃO ---

    content.innerHTML = `
      <div class="details-section">
        <h3><i class="fas fa-info-circle"></i> Resumo</h3>
        <div class="details-row"><span class="details-label">ID Transporte:</span><span>${data.id || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">ID Pedido:</span><span>${data.pedido_id || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Status:</span><span>${data.status || 'N/A'}</span></div>
                <div class="details-row"><span class="details-label">Valor do Frete:</span><span>${valorFreteFormatado}</span></div>
        <div class="details-row"><span class="details-label">Distância:</span><span>${data.distancia_km || 'N/A'} km</span></div>
      </div>
      <div class="details-section">
        <h3><i class="fas fa-truck-moving"></i> Rota</h3>
        <div class="details-row"><span class="details-label">Origem (Fazenda):</span><span>${data.origem_nome || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Destino (Frigorífico):</span><span>${data.destino_nome || 'N/A'}</span></div>
      </div>
      <div class="details-section">
        <h3><i class="fas fa-calendar-alt"></i> Datas</h3>
        <div class="details-row"><span class="details-label">Data Solicitação:</span><span>${dataCriacao}</span></div>
        <div class="details-row"><span class="details-label">Data Retirada:</span><span>${dataRetirada}</span></div>
        <div class="details-row"><span class="details-label">Prev. Entrega:</span><span>${dataPrevEntrega || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Entrega Real:</span><span>${dataEntregaReal || 'N/A'}</span></div>
      </div>
      <div class="details-section">
        <h3><i class="fas fa-users"></i> Envolvidos</h3>
        <div class="details-row"><span class="details-label">Veículo (Placa):</span><span>${data.placa || 'N/A'}</span></div>
        <div class="details-row"><span class="details-label">Motorista:</span><span>${data.motorista_nome || 'N/A'}</span></div>
      </div>
    `;
    modal.style.display = 'block';
  }
  // =========================================================================
  // FIM DA FUNÇÃO MODIFICADA
  // =========================================================================
  
  function closeModal(id) {
    document.getElementById(id).style.display = 'none';
  }
  
  // Event delegation (Adicionado '.trip-details-btn' e '.delete-btn')
  document.addEventListener('click', e => {
    const detailsBtn = e.target.closest('.details-btn');
    if (detailsBtn) {
      const data = JSON.parse(detailsBtn.dataset.veiculo);
      openDetailsModal(data);
      return;
    }
    const editBtn = e.target.closest('.edit-btn');
    if (editBtn) {
      const id = editBtn.dataset.id;
      const data = JSON.parse(editBtn.dataset.veiculo);
      openEditModal(id, data);
      return;
    }
    // NOVO: Listener para o botão de detalhes da viagem
    const tripDetailsBtn = e.target.closest('.trip-details-btn');
    if (tripDetailsBtn) {
      const data = JSON.parse(tripDetailsBtn.dataset.trip);
      openTripDetailsModal(data);
      return;
    }
    // --- NOVO LISTENER PARA EXCLUIR VEÍCULO ---
    const deleteBtn = e.target.closest('.delete-btn');
    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      const data = JSON.parse(deleteBtn.dataset.veiculo);
      openDeleteVehicleModal(id, data);
      return;
    }
  });
  
  // Fechar modal ao clicar fora (Adicionado 'tripDetailsModal' e 'deleteVehicleModal')
  window.onclick = function(e) {
    ['detailsModal', 'editModal', 'tripDetailsModal', 'deleteVehicleModal'].forEach(id => {
      const modal = document.getElementById(id);
      if (e.target === modal) modal.style.display = 'none';
    });
  };
  
  // Validação simples no form
  document.getElementById('editForm').addEventListener('submit', function(e) {
    const placa = document.getElementById('editPlaca').value.trim();
    if (!placa) {
      e.preventDefault();
      alert('A placa é obrigatória.');
    }
    // Validação básica para novos semir reboques se CARRETA
    const tipo = document.getElementById('editTipo').value;
    if (tipo === 'CARRETA') {
        let hasSr = false;
        const inputs = document.querySelectorAll('input[name^="new_sr_placa_"]');
        inputs.forEach(input => {
            if (input.value.trim()) hasSr = true;
        });
        const existing = document.querySelectorAll('.sr-existing-item input[type="checkbox"]:checked');
        if (!hasSr && existing.length === 0 && document.querySelectorAll('.sr-existing-item').length > 0) {
            // Se não adicionou novo e não marcou nenhum para delete, mas tem existentes, ok
        } else if (!hasSr && existing.length === document.querySelectorAll('.sr-existing-item').length) {
            e.preventDefault();
            alert('Para CARRETA, adicione pelo menos um semir reboque ou mantenha um existente.');
        }
    }
  });
</script>
</body>
</html>