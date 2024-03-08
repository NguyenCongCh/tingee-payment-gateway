<?php
require("AccessData.php");
header('Content-Type: application/json');
if($_SERVER['REQUEST_METHOD'] === 'GET'){
    if(isset($_GET['order_id'])){
        $order_id = $_GET['order_id'];
        echo checkPaymentStatus($order_id,$table_prefix);
    }
}

function checkPaymentStatus($order_id,$table_prefix){
    $db = new AccessData();
    $sql = "SELECT status FROM ".$table_prefix ."wc_orders WHERE id=$order_id";
    $result=$db->query($sql);
    if($result){
        $row = mysqli_fetch_array($result);
        $status = $row["status"];
        $db->dis_connect();
        if($status == "wc-processing"){
            return json_encode(array("payment"=>true));
        }
        return json_encode(array("payment"=>false));
    }
}