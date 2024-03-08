<?php
header('Content-Type: application/json');
require("AccessData.php");
// lấy thông tin phần header
if(isset($_SERVER["HTTP_X_SIGNATURE"]) && isset($_SERVER["HTTP_X_REQUEST_TIMESTAMP"])){
    $xSignature = $_SERVER["HTTP_X_SIGNATURE"];
    $xRequestTimestamp = $_SERVER["HTTP_X_REQUEST_TIMESTAMP"];
}
// lấy thông tin phần body
$data = json_decode(file_get_contents('php://input'), true);
// xác thực người gửi

if(!verifySender($xSignature,$xRequestTimestamp,$data,$table_prefix)){
    echo json_encode(array("code"=> "09","message"=> "Chữ ký không hợp lệ"));
    exit;
}

// xử lý thông tin chuyển khoản
$response = handle_request($data,$table_prefix);

echo json_encode($response);
function handle_request($data,$table_prefix){
    if(isset($data["clientId"]) && isset($data["transactionCode"]) && isset($data["amount"])
    && isset($data["content"]) && isset($data["bankTransactionDate"]) && isset($data["currency"])
    && isset($data["bank"]) && isset($data["accountNumber"])
    && isset($data["vaAccountNumber"]) && isset($data["transactionDate"])
    )
    {
        $amount = $data["amount"];
        $content = $data["content"];
        $bank = $data["bank"];
        $accountNumber = $data["accountNumber"];
        $vaAccountNumber = $data["vaAccountNumber"];
        $db = new AccessData();
        $sql = "SELECT option_value FROM ".$table_prefix."options WHERE option_name='woocommerce_tingee_settings'";
        $result = $db->query($sql);
        $row = mysqli_fetch_array($result);
        //
        $option_value = $row["option_value"];
        // get prefix
        if(preg_match('/s:6:"prefix";s:\d+:"(.*?)";/', $option_value,$match)){
            $prefix = $match[1];
        } 
        // get suffix
        if(preg_match('/s:6:"suffix";s:\d+:"(.*?)";/', $option_value,$match)){
            $suffix = $match[1];
        }
        //
        if(preg_match('/s:4:"bank";s:\d+:"(.*?)";/', $option_value,$match)){
            $check_bank = $match[1];
        }
        if($bank != $check_bank){
            return array('code' => 'xx','message'=>'sai ngân hàng');
        }
        if(preg_match('/s:14:"account_number";s:\d+:"(.*?)";/', $option_value,$match)){
            $check_accountNumber = $match[1];
        }
        //
        if($accountNumber != $check_accountNumber){
            return array('code' => 'xx','message'=>'sai số tài khoản');
        }
        if(preg_match('/s:17:"va_account_number";s:\d+:"(.*?)";/', $option_value,$match)){
            $check_vaAccountNumber = $match[1];
        }
        // so sánh stk ảo
        if($vaAccountNumber != $check_vaAccountNumber){
            return array('code' => 'xx','message'=>'sai số tài khoản ảo');
        }
        // xử lý số tiền
        $order_id = get_order_id($content,$prefix,$suffix);
        $order_id_int =intval($order_id);
        if($order_id === '' || !is_int($order_id_int)){
            return array('code'=> 'xx','message'=> 'sai nội dung chuyển khoản');
        }
        $sql = "SELECT total_amount FROM ".$table_prefix."wc_orders WHERE id=$order_id";
        $result = $db->query($sql);
        // xử lý nêu không tìm thấy order
        if($result->num_rows>0){
            $row = mysqli_fetch_array($result);
        }
        else{
            return array("code"=> "xx","message"=> "Sai mã đơn hàng");
        }
        // nếu số tiền bằng hoặc lớn hơn chuyển sang trạng thái đang xử lý
        if((int)$row["total_amount"] <= (int)$amount){
            $sql="UPDATE ".$table_prefix."wc_orders SET status='wc-processing' WHERE id=$order_id";
        }
        // nếu số tiền ít hơn chuyển sang trạng thái tạm giữ
        else{
            $sql="UPDATE ".$table_prefix."wc_orders SET status='wc-on-hold' WHERE id=$order_id";
        }
        if($db->update($sql)){
            return array("code"=> "02","message"=> "Giao dịch đã được cập nhật thành công");
        }
        else{
            return array("code"=> "xx","message"=> "Giao dịch chưa được cập nhật");
        }
    }
    else{
        return array('code' => 'xx','message'=>'thất bại');
    }
}
function verifySender($xSignature,$xRequestTimestamp,$data,$table_prefix)
{
    //
    $db = new AccessData();
    // chuỗi cần băm
    $stringToHash = $xRequestTimestamp.":".json_encode($data);
    // secret token
    $sql ="SELECT option_value FROM ".$table_prefix."options WHERE option_name='woocommerce_tingee_settings'";
    $result = $db->query($sql);
    $row = mysqli_fetch_array($result);
    //
    $option_value = $row["option_value"];
    //
    if(preg_match('/s:12:"secret_token";s:\d+:"(.*?)";/', $option_value,$match)){
        $secretToken = $match[1];
    }
    // dùng hàm hash_hmac với thuật toán sha-512 và key là secret token
    $hashString = hash_hmac('sha512', $stringToHash, $secretToken);
    // kiểm tra toàn vẹn dữ liệu
    if($hashString === $xSignature){
        return true;
    }
    return false;
}
function get_order_id($content,$prefix,$suffix){
    $pattern = "/$prefix.*$suffix/";
    $success = preg_match($pattern, $content, $match);
    if ($success) {
        $orderId =  str_replace($suffix, "", str_replace($prefix, "",$match[0]));
        return $orderId;
    }
    return '';
}