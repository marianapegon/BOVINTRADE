<?php
session_start();
// Assuma que este arquivo tem acesso à conexão PDO
// Certifique-se de que o caminho para 'config.php' está correto.
require_once 'config.php';


header('Content-Type: application/json');

// Proteção básica: Se não for Frigorífico, nega o acesso
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo_usuario'] !== 'FRIGORIFICO') {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

$frigorifico_id = $_SESSION['usuario']['id'];
$carrinho = $_SESSION['carrinho'] ?? [];

// Se o carrinho estiver vazio, retorna 0
if (empty($carrinho)) {
    echo json_encode(['frete_total' => 0.00, 'sucesso' => true]);
    exit;
}

$frete_por_km = 5.50;
$frete_total = 0.00;

try {
    // 1. Obter Coordenadas do Frigorífico (Destino)
    $stmt_frig = $pdo->prepare("SELECT latitude, longitude FROM usuarios WHERE id = :id");
    $stmt_frig->execute([':id' => $frigorifico_id]);
    $frig_coords = $stmt_frig->fetch(PDO::FETCH_ASSOC);

    // VALIDAÇÃO 1: Coordenadas do Frigorífico
    if (!$frig_coords || empty($frig_coords['latitude']) || empty($frig_coords['longitude'])) {
        throw new Exception("FRIGORÍFICO: Coordenadas de Latitude/Longitude não cadastradas ou inválidas.");
    }
    
    $lat_frig = (float)$frig_coords['latitude'];
    $lon_frig = (float)$frig_coords['longitude'];

    $fretes_por_lote = [];
    
    // VALIDAÇÃO 2: Garantir que todos os itens do carrinho têm 'fazenda_id'
    $fazendas_ids = [];
    foreach ($carrinho as $lote) {
        if (!isset($lote['fazenda_id'])) {
            throw new Exception("CARRINHO: Item do carrinho (Lote ID: " . ($lote['id'] ?? 'N/A') . ") não possui fazenda_id.");
        }
        $fazendas_ids[] = $lote['fazenda_id'];
    }
    $fazendas_ids = array_unique($fazendas_ids);
    $fazendas_coords = [];

    // 2. Obter Coordenadas de Todas as Fazendas (Origem)
    $placeholders = implode(',', array_fill(0, count($fazendas_ids), '?'));
    $stmt_faz = $pdo->prepare("SELECT id, latitude, longitude FROM usuarios WHERE id IN ($placeholders)");
    $stmt_faz->execute($fazendas_ids);

    while ($row = $stmt_faz->fetch(PDO::FETCH_ASSOC)) {
        $fazendas_coords[$row['id']] = [
            'lat' => (float)$row['latitude'],
            'lon' => (float)$row['longitude']
        ];
    }

    // 3. Função Haversine (Cálculo de Distância)
    function haversine($lat1, $lon1, $lat2, $lon2) {
        $R = 6371; // Raio da Terra em km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $R * $c; // Distância em km
        return round($distance);
    }

    // 4. Calcular o Frete para Cada Item
    foreach ($carrinho as $item) {
        $lote_id = $item['id'];
        $fazenda_id = $item['fazenda_id'];

        if (!isset($fazendas_coords[$fazenda_id]) || empty($fazendas_coords[$fazenda_id]['lat']) || empty($fazendas_coords[$fazenda_id]['lon'])) {
            // Se a fazenda não foi encontrada ou faltam as coordenadas, lança erro específico
            throw new Exception("FAZENDA: Coordenadas da Fazenda (ID: {$fazenda_id}) não cadastradas ou inválidas.");
        }

        $coords_faz = $fazendas_coords[$fazenda_id];
        
        $distancia_km = haversine(
            $coords_faz['lat'], $coords_faz['lon'], 
            $lat_frig, $lon_frig
        );
        
        // O frete é calculado por KM (R$ 5,50 por km)
        $custo_frete = $distancia_km * $frete_por_km;
        
        $fretes_por_lote[$lote_id] = [
            'custo' => $custo_frete,
            'distancia' => $distancia_km
        ];
        
        $frete_total += $custo_frete;
    }

    echo json_encode([
        'frete_total' => $frete_total,
        'fretes_por_lote' => $fretes_por_lote,
        'sucesso' => true
    ]);

} catch (Throwable $e) {
    // Retorna o erro detalhado para a requisição AJAX
    http_response_code(500);
    echo json_encode([
        'error' => 'ERRO CRÍTICO DE FRETE: ' . $e->getMessage(),
        'sucesso' => false
    ]);
}
?>