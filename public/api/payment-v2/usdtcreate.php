<?php
header("Content-Type: application/json");

$uid    = $_POST["uid"] ?? 0;
$amount = $_POST["amount"] ?? 0;

if ($uid <= 0 || $amount <= 0) {
    echo json_encode(["code" => 0, "msg" => "参数错误"]);
    exit;
}

$orderNo = "USDT-" . time() . rand(1000,9999);
$address = "TRX" . rand(1000000,9999999);

echo json_encode([
    "code" => 1,
    "msg"  => "ok",
    "data" => [
        "order_no" => $orderNo,
        "amount"   => $amount,
        "address"  => $address,
        "chain"    => "TRC20",
        "qrcode"   => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=$address"
    ]
]);
exit;
?>
