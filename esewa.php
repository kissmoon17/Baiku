<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function page($title, $kicker, $heading, $message, $success = false, $orderId = null): void {
    $color = $success ? '#2f6b4f' : '#b5654a';
    $link = $success ? 'index.html' : 'checkout.html';
    $label = $success ? 'Back to Baiku' : 'Return to checkout';
    $order = $orderId ? '<p style="margin-top:10px"><strong>Order #BK-' . htmlspecialchars(str_pad((string)$orderId,4,'0',STR_PAD_LEFT)) . '</strong></p>' : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . ' — Baiku</title><link rel="stylesheet" href="css/style.css"></head><body><div class="announcement">Free shipping worldwide over $100 · Easy returns · Authentic gear only</div><header class="header"><div class="container nav"><a class="logo" href="index.html"><span class="logo-main">Baiku</span><span class="logo-sub">RIDING GEAR</span></a></div></header><main><section class="page-hero"><div class="container"><div class="section-kicker" style="color:' . $color . '">' . htmlspecialchars($kicker) . '</div><h1 class="page-title">' . htmlspecialchars($heading) . '</h1></div></section><div class="container" style="max-width:760px;padding-top:70px;padding-bottom:100px"><p style="font-size:18px;line-height:1.8;color:#777169">' . htmlspecialchars($message) . '</p>' . $order . '<a class="btn" href="' . $link . '" style="margin-top:28px">' . $label . ' →</a></div></main></body></html>';
    exit;
}

function decodeEsewaData(string $encoded): array {
    $encoded = trim($encoded);
    if ($encoded === '') return [];
    $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
    $encoded = strtr($encoded, '-_', '+/');
    $raw = base64_decode($encoded, true);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function generateSignature(string $message): string {
    return base64_encode(hash_hmac('sha256', $message, ESEWA_SECRET_KEY, true));
}

function responseSignatureIsValid(array $data): bool {
    $signed = trim((string)($data['signed_field_names'] ?? ''));
    $provided = (string)($data['signature'] ?? '');
    if ($signed === '' || $provided === '') return false;
    $parts = [];
    foreach (explode(',', $signed) as $field) {
        $field = trim($field);
        if ($field === '' || !array_key_exists($field, $data)) return false;
        $parts[] = $field . '=' . $data[$field];
    }
    return hash_equals(generateSignature(implode(',', $parts)), $provided);
}

function verifyWithEsewa(string $uuid, float $amount): array {
    $query = http_build_query([
        'product_code' => ESEWA_PRODUCT_CODE,
        'total_amount' => number_format($amount, 2, '.', ''),
        'transaction_uuid' => $uuid,
    ]);
    $url = ESEWA_STATUS_URL . '?' . $query;
    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true]);
        $body = curl_exec($ch);
        curl_close($ch);
    }
    if ($body === false) {
        $context = stream_context_create(['http' => ['timeout' => 15]]);
        $body = @file_get_contents($url, false, $context);
    }
    if ($body === false) return ['status' => 'UNAVAILABLE'];
    $json = json_decode($body, true);
    if (!is_array($json)) return ['status' => 'INVALID_RESPONSE'];
    return $json;
}

function restoreOrderStock(PDO $pdo, int $orderId): void {
    $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = :order_id AND product_id IS NOT NULL');
    $items->execute(['order_id' => $orderId]);
    $restore = $pdo->prepare('UPDATE products SET stock = stock + :quantity WHERE id = :id');
    foreach ($items->fetchAll() as $item) {
        $restore->execute(['quantity' => (int)$item['quantity'], 'id' => (int)$item['product_id']]);
    }
}

function restoreOrderCart(PDO $pdo, int $orderId, int $userId): void {
    $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = :order_id AND product_id IS NOT NULL');
    $items->execute(['order_id' => $orderId]);
    $insert = $pdo->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)');
    foreach ($items->fetchAll() as $item) {
        $insert->execute(['user_id' => $userId, 'product_id' => (int)$item['product_id'], 'quantity' => (int)$item['quantity']]);
    }
}

function completeOrder(PDO $pdo, int $paymentId, array $response, array $statusCheck): int {
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM esewa_payments WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $paymentId]);
        $payment = $lock->fetch();
        if (!$payment) throw new RuntimeException('eSewa payment record was not found.');
        if ($payment['status'] === 'COMPLETE') {
            $pdo->commit();
            return (int)$payment['order_id'];
        }
        if (strtoupper((string)($statusCheck['status'] ?? '')) !== 'COMPLETE') throw new RuntimeException('eSewa did not confirm the payment as COMPLETE.');
        $verifiedAmount = (float)($statusCheck['total_amount'] ?? 0);
        if (abs($verifiedAmount - (float)$payment['amount']) > 0.009) throw new RuntimeException('The verified eSewa amount does not match the Baiku order.');
        if ((string)($statusCheck['transaction_uuid'] ?? '') !== (string)$payment['transaction_uuid']) throw new RuntimeException('The eSewa transaction could not be matched to this order.');

        $order = $pdo->prepare('SELECT id,status,total_amount,payment_status FROM orders WHERE id = :id FOR UPDATE');
        $order->execute(['id' => (int)$payment['order_id']]);
        $orderRow = $order->fetch();
        if (!$orderRow) throw new RuntimeException('The Baiku order could not be found.');
        if (abs((float)$orderRow['total_amount'] - (float)$payment['amount']) > 0.009) throw new RuntimeException('The verified amount does not match the order total.');

        $ref = (string)($statusCheck['ref_id'] ?? $response['transaction_code'] ?? '');
        $upPayment = $pdo->prepare("UPDATE esewa_payments SET status='COMPLETE', reference_code=:reference_code, response_data=:response_data WHERE id=:id");
        $upPayment->execute(['reference_code'=>$ref, 'response_data'=>json_encode(['response'=>$response,'status_check'=>$statusCheck], JSON_UNESCAPED_UNICODE), 'id'=>$paymentId]);
        $upOrder = $pdo->prepare("UPDATE orders SET status='Confirmed', payment_status='Paid', payment_reference=:reference, payment_transaction_uuid=:uuid WHERE id=:id");
        $upOrder->execute(['reference'=>$ref, 'uuid'=>$payment['transaction_uuid'], 'id'=>(int)$payment['order_id']]);
        $pdo->commit();
        return (int)$payment['order_id'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

$action = $_GET['action'] ?? '';

try {
    if ($action === 'initiate') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') page('eSewa payment', 'Payment', 'Invalid request', 'Please return to checkout and try again.');
        if (empty($_SESSION['user_id'])) page('eSewa payment', 'Payment', 'Sign in required', 'Please sign in before starting an eSewa payment.');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) $data = [];
        $userId = (int)$_SESSION['user_id'];
        $name = trim((string)($data['name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $address = trim((string)($data['address'] ?? ''));
        $city = trim((string)($data['city'] ?? ''));
        $country = trim((string)($data['country'] ?? 'Nepal')) ?: 'Nepal';
        if (mb_strlen($name)<2 || mb_strlen($name)>100 || $phone==='' || mb_strlen($phone)>30 || mb_strlen($address)<3 || mb_strlen($address)>255 || mb_strlen($city)<2 || mb_strlen($city)>100 || mb_strlen($country)>100) {
            header('Content-Type: application/json; charset=utf-8'); http_response_code(400); echo json_encode(['success'=>false,'message'=>'Please complete the shipping information.']); exit;
        }
        $userStmt=$pdo->prepare('SELECT id,name,email FROM users WHERE id=:id LIMIT 1'); $userStmt->execute(['id'=>$userId]); $user=$userStmt->fetch();
        if (!$user) { header('Content-Type: application/json; charset=utf-8'); http_response_code(401); echo json_encode(['success'=>false,'message'=>'Your account could not be found.']); exit; }
        $pdo->beginTransaction();
        try {
            $cart=$pdo->prepare('SELECT product_id,quantity FROM cart_items WHERE user_id=:user_id ORDER BY id ASC'); $cart->execute(['user_id'=>$userId]); $cartItems=$cart->fetchAll();
            if (!$cartItems) throw new RuntimeException('Your cart is empty.');
            $productStmt=$pdo->prepare('SELECT id,name,price,stock,active FROM products WHERE id=:id FOR UPDATE');
            $lines=[]; $total=0.0;
            foreach($cartItems as $ci){
                $productStmt->execute(['id'=>(int)$ci['product_id']]); $p=$productStmt->fetch();
                if(!$p || !(int)$p['active']) throw new RuntimeException('One of the products in your cart is no longer available.');
                $qty=(int)$ci['quantity']; if($qty<1 || $qty>(int)$p['stock']) throw new RuntimeException($p['name'].' does not have enough stock.');
                $unit=(float)$p['price']; $sub=round($unit*$qty,2); $total+= $sub; $lines[]=['product_id'=>(int)$p['id'],'product_name'=>$p['name'],'unit_price'=>$unit,'quantity'=>$qty,'subtotal'=>$sub];
            }
            $total=round($total,2);
            $uuid='BK-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $orderStmt=$pdo->prepare("INSERT INTO orders (user_id,customer_name,customer_email,phone,address,city,country,total_amount,status,payment_method,payment_status,payment_transaction_uuid) VALUES (:user_id,:customer_name,:customer_email,:phone,:address,:city,:country,:total_amount,'Pending Payment','eSewa','Pending',:uuid)");
            $orderStmt->execute(['user_id'=>$userId,'customer_name'=>$name,'customer_email'=>$user['email'],'phone'=>$phone,'address'=>$address,'city'=>$city,'country'=>$country,'total_amount'=>$total,'uuid'=>$uuid]);
            $orderId=(int)$pdo->lastInsertId();
            $itemStmt=$pdo->prepare('INSERT INTO order_items (order_id,product_id,product_name,unit_price,quantity,subtotal) VALUES (:order_id,:product_id,:product_name,:unit_price,:quantity,:subtotal)');
            $stockStmt=$pdo->prepare('UPDATE products SET stock=stock-:qty_dec WHERE id=:id AND stock>=:qty_check');
            foreach($lines as $line){
                $itemStmt->execute(['order_id'=>$orderId,'product_id'=>$line['product_id'],'product_name'=>$line['product_name'],'unit_price'=>$line['unit_price'],'quantity'=>$line['quantity'],'subtotal'=>$line['subtotal']]);
                $stockStmt->execute(['qty_dec'=>$line['quantity'],'id'=>$line['product_id'],'qty_check'=>$line['quantity']]);
                if($stockStmt->rowCount()!==1) throw new RuntimeException('Stock changed while starting payment. Please try again.');
            }
            $pdo->prepare('DELETE FROM cart_items WHERE user_id=:user_id')->execute(['user_id'=>$userId]);
            $pay=$pdo->prepare('INSERT INTO esewa_payments (order_id,transaction_uuid,product_code,amount,status) VALUES (:order_id,:uuid,:product_code,:amount,\'PENDING\')');
            $pay->execute(['order_id'=>$orderId,'uuid'=>$uuid,'product_code'=>ESEWA_PRODUCT_CODE,'amount'=>$total]);
            $pdo->commit();
        } catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }

        $message='total_amount=' . number_format($total,2,'.','') . ',transaction_uuid=' . $uuid . ',product_code=' . ESEWA_PRODUCT_CODE;
        $signature=generateSignature($message);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>true,'order_id'=>$orderId,'payment'=>['amount'=>number_format($total,2,'.',''),'transaction_uuid'=>$uuid,'product_code'=>ESEWA_PRODUCT_CODE,'signature'=>$signature,'signed_field_names'=>'total_amount,transaction_uuid,product_code','form_url'=>ESEWA_FORM_URL,'success_url'=>ESEWA_SUCCESS_URL,'failure_url'=>ESEWA_FAILURE_URL]]); exit;
    }

    if ($action === 'success') {
        $encoded=$_GET['data'] ?? $_POST['data'] ?? '';
        $data=decodeEsewaData((string)$encoded);
        if(!$data) page('Payment result','eSewa payment','Could not read the payment response','eSewa returned an unreadable response. Please check the transaction in eSewa and contact the store administrator.');
        if(!responseSignatureIsValid($data)) page('Payment result','Security check','Payment response could not be verified','The eSewa response signature did not match. The order has not been marked as paid.');
        $uuid=(string)($data['transaction_uuid']??''); $code=(string)($data['product_code']??''); $amount=(float)($data['total_amount']??0);
        if($code!==ESEWA_PRODUCT_CODE || $uuid==='' || strtoupper((string)($data['status']??''))!=='COMPLETE') page('Payment result','eSewa payment','Payment not completed','eSewa did not report this payment as complete.');
        $stmt=$pdo->prepare('SELECT * FROM esewa_payments WHERE transaction_uuid=:uuid LIMIT 1'); $stmt->execute(['uuid'=>$uuid]); $payment=$stmt->fetch();
        if(!$payment || abs((float)$payment['amount']-$amount)>0.009) page('Payment result','Security check','Payment mismatch','The eSewa payment could not be matched to the Baiku order.');
        $statusCheck=verifyWithEsewa($uuid,(float)$payment['amount']);
        if(strtoupper((string)($statusCheck['status']??''))!=='COMPLETE') page('Payment result','Verification pending','Payment needs verification','eSewa did not return a COMPLETE status during server-side verification. Please contact the store administrator before placing another order.');
        try{$orderId=completeOrder($pdo,(int)$payment['id'],$data,$statusCheck);}catch(Throwable $e){error_log('Baiku eSewa completion error: '.$e->getMessage()); page('Payment result','Payment verification','We could not finalize the order',$e->getMessage().' Please contact the store administrator.');}
        page('Payment successful','eSewa payment','Payment confirmed','Your eSewa payment was verified successfully. Your Baiku order is now confirmed.',true,$orderId);
    }

    if ($action === 'failure') {
        $encoded=$_GET['data'] ?? $_POST['data'] ?? '';
        $data=decodeEsewaData((string)$encoded);
        $uuid=(string)($data['transaction_uuid']??'');
        if($uuid!==''){
            $stmt=$pdo->prepare('SELECT ep.id,ep.order_id,ep.status,o.user_id FROM esewa_payments ep INNER JOIN orders o ON o.id=ep.order_id WHERE ep.transaction_uuid=:uuid LIMIT 1'); $stmt->execute(['uuid'=>$uuid]); $payment=$stmt->fetch();
            if($payment && $payment['status']==='PENDING'){
                $pdo->beginTransaction();
                try{
                    $lock=$pdo->prepare('SELECT ep.*,o.user_id FROM esewa_payments ep INNER JOIN orders o ON o.id=ep.order_id WHERE ep.id=:id FOR UPDATE');$lock->execute(['id'=>(int)$payment['id']]);$p=$lock->fetch();
                    if($p && $p['status']==='PENDING'){
                        restoreOrderStock($pdo,(int)$p['order_id']);
                        restoreOrderCart($pdo,(int)$p['order_id'],(int)$p['user_id']);
                        $pdo->prepare("UPDATE esewa_payments SET status='FAILED',response_data=:response_data WHERE id=:id")->execute(['response_data'=>json_encode($data,JSON_UNESCAPED_UNICODE),'id'=>(int)$p['id']]);
                        $pdo->prepare("UPDATE orders SET status='Cancelled',payment_status='Failed' WHERE id=:id AND status='Pending Payment'")->execute(['id'=>(int)$p['order_id']]);
                    }
                    $pdo->commit();
                }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Baiku eSewa failure cleanup: '.$e->getMessage());}
            }
        }
        page('Payment cancelled','eSewa payment','Payment was not completed','No payment was captured for this Baiku order. You can return to checkout and try again.');
    }

    page('eSewa payment','Payment','Invalid payment request','The requested eSewa action was not found.');
} catch (Throwable $e) {
    error_log('Baiku eSewa error: '.$e->getMessage());
    if ($action === 'initiate') { header('Content-Type: application/json; charset=utf-8'); http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage() ?: 'Could not start eSewa payment.']); exit; }
    page('eSewa payment','Payment error','Something went wrong','We could not complete the eSewa payment flow. Please return to checkout and try again.');
}
?>
