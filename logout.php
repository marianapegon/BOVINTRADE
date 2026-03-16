<?php
// Bovintrade-PHP/Projeto-Bovintrade-2/logout.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'config.php'; // Sua conexão PDO

$frigoId = (int)($_SESSION['usuario']['id'] ?? 0);

if ($frigoId > 0) {
    $pdo->beginTransaction();
    try {
        // 1. Libera lotes EM_NEGOCIACAO com reserva ativa deste frigorífico
        $stmt = $pdo->prepare("
            UPDATE lote_bois lb
            JOIN reservas_lote r ON r.lote_id = lb.id
            JOIN pedidos p ON p.id = r.pedido_id
            SET lb.status = 'DISPONIVEL', lb.updated_at = NOW()
            WHERE p.frigorifico_id = ? 
              AND r.expira_em > NOW()
              AND lb.status = 'EM_NEGOCIACAO'
        ");
        $stmt->execute([$frigoId]);

        // 2. Remove as reservas
        $stmt = $pdo->prepare("
            DELETE r FROM reservas_lote r
            JOIN pedidos p ON p.id = r.pedido_id
            WHERE p.frigorifico_id = ? AND r.expira_em > NOW()
        ");
        $stmt->execute([$frigoId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Erro ao liberar reservas no logout (frigo_id: $frigoId): " . $e->getMessage());
    }
}

// === Limpa sessão completamente ===
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();
header('Location: login.php');
exit;