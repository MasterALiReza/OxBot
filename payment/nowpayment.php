<?php
ini_set('error_log', 'error_log');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../keyboard.php';
require_once __DIR__ . '/../jdf.php';
require __DIR__ . '/../vendor/autoload.php';
$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
$textbotlang = languagechange('../text.json');

// ───────────────────────────────────────────────────
// Security: Verify NOWPayments IPN Signature (HMAC-SHA512)
// Without this check, anyone can forge a webhook and get free balance.
// ───────────────────────────────────────────────────
$raw_body = file_get_contents("php://input");
$ipn_secret = getPaySettingValue('nowpayment_ipn_secret', '');

if (!empty($ipn_secret)) {
    $sig_header = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
    if (empty($sig_header)) {
        error_log('[NOWPayments IPN] Missing x-nowpayments-sig header from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(401);
        exit('Unauthorized: Missing signature');
    }
    $expected_sig = hash_hmac('sha512', $raw_body, $ipn_secret);
    if (!hash_equals($expected_sig, strtolower($sig_header))) {
        error_log('[NOWPayments IPN] Invalid signature from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(401);
        exit('Unauthorized: Invalid signature');
    }
}

$data = json_decode($raw_body, true);
if (isset($data['payment_status']) && $data['payment_status'] == "finished") {
    $pay = StatusPayment($data['payment_id']);
    if ($pay['payment_status'] != "finished")
        return;
    $Payment_report = select("Payment_report", "*", "dec_not_confirmed", $pay['invoice_id'], "select");
    if ($Payment_report) {
        if ($Payment_report['payment_Status'] == "paid")
            return;
        DirectPayment($Payment_report['id_order'], "../images.jpg");
        $pricecashback = select("PaySetting", "ValuePay", "NamePay", "cashbacknowpayment", "select")['ValuePay'];
        $Balance_id = select("user", "*", "id", $Payment_report['id_user'], "select");
        if ($pricecashback != "0") {
            $result = ($Payment_report['price'] * $pricecashback) / 100;
            $Balance_confrim = intval($Balance_id['Balance']) + $result;
            update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
            $pricecashback = number_format($pricecashback);
            $text_report = sprintf($textbotlang['paymentGateway']['giftReport'], $result);
            sendmessage($Balance_id['id'], $text_report, null, 'HTML');
        }
        $text_reportpayment = sprintf($textbotlang['paymentGateway']['reportNowpayment'], $Balance_id['username'], $Balance_id['id'], $Payment_report['price'], $pay['actually_paid']);
        if (strlen($setting['Channel_Report']) > 0) {
            telegram('sendmessage', [
                'chat_id' => $setting['Channel_Report'],
                'message_thread_id' => $paymentreports,
                'text' => $text_reportpayment,
                'parse_mode' => "HTML"
            ]);
        }
        update("Payment_report", "payment_Status", "paid", "id_order", $Payment_report['id_order']);
    }
}