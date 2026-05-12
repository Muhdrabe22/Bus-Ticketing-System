<?php
require_once __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json');

$db     = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Validate promo code (public)
if ($action === 'validate') {
    $code = clean($_POST['code'] ?? '');
    $fare = (float)($_POST['fare'] ?? 0);
    $uid  = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

    $promo = $db->query("SELECT * FROM promo_codes WHERE code='$code' AND is_active=1 AND (valid_from IS NULL OR valid_from <= CURDATE()) AND (valid_until IS NULL OR valid_until >= CURDATE())")->fetch_assoc();
    if (!$promo) { echo json_encode(['valid'=>false,'message'=>'Invalid or expired promo code']); exit; }
    if ($promo['used_count'] >= $promo['usage_limit']) { echo json_encode(['valid'=>false,'message'=>'Promo code limit reached']); exit; }
    if ($fare < $promo['min_fare']) { echo json_encode(['valid'=>false,'message'=>'Minimum fare of ₦'.number_format($promo['min_fare']).' required']); exit; }

    // Check if user already used this code
    if ($uid) {
        $used = $db->query("SELECT id FROM promo_usage WHERE promo_id={$promo['id']} AND user_id=$uid")->num_rows;
        if ($used > 0) { echo json_encode(['valid'=>false,'message'=>'You have already used this promo code']); exit; }
    }

    $discount = $promo['discount_type'] === 'percent'
        ? min($fare * $promo['discount_value'] / 100, $promo['max_discount'] ?: PHP_INT_MAX)
        : $promo['discount_value'];
    $discount = round(min($discount, $fare), 2);

    echo json_encode(['valid'=>true,'discount'=>$discount,'final_fare'=>$fare-$discount,'message'=>$promo['description'],'promo_id'=>$promo['id']]);
    exit;
}

// Admin: list promos
if ($action === 'list' && isLoggedIn() && $_SESSION['user_role']==='admin') {
    $promos = $db->query("SELECT p.*, u.full_name as creator FROM promo_codes p LEFT JOIN users u ON p.created_by=u.id ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['promos'=>$promos]); exit;
}

// Admin: toggle promo
if ($action === 'toggle' && isLoggedIn() && $_SESSION['user_role']==='admin') {
    $id  = (int)$_POST['id'];
    $val = (int)$_POST['value'];
    $db->query("UPDATE promo_codes SET is_active=$val WHERE id=$id");
    echo json_encode(['success'=>true]); exit;
}

echo json_encode(['error'=>'Invalid action']);
