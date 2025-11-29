<?php
header("Content-Type: application/json");

$address = "TRX" . rand(10000000, 99999999);

echo json_encode([
    "code" => 1,
    "msg"  => "ok",
    "data" => [
        "address" => $address,
        "chain"   => "TRC20",
        "qrcode"  => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=$address"
    ]
]);
exit;
?>
