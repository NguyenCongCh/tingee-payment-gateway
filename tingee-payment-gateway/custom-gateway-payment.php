<?php
/*
 * Plugin Name:  TINGEE PAYMENT GATEWAY
 * Plugin URI: https://shop.tingee.vn
 * Description: TINGEE PAYMENT GATEWAY
 * Author: hieu
 * Author URI: https://www.facebook.com/minhhieu.minh.54
 * Text Domain: tingee
 * Domain Path: /languages
 * Version: 1.0.0
 * Tested up to: 2.0.0
 * License: GNU General Public License v3.0
 */
/*
* dang ky class voi cong thanh toan wc
*/
add_filter( 'woocommerce_payment_gateways', 'tingee_add_gateway_class' );
function tingee_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_TINGEE_GW';
    return $gateways;
}

/*
 * add_action trong hook plugins_loaded
 */
add_action( 'plugins_loaded', 'tingee_init_gateway_class' );
function tingee_init_gateway_class() {
    class WC_TINGEE_GW extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'tingee';
            $this->icon               = apply_filters( 'woocommerce_tingee_icon', '' );
            $this->has_fields         = true;
            $this->method_title       = __( 'Cổng Thanh Toán TINGEE', 'tingee' );
            $this->method_description = __( 'Thanh toán bằng scan QR code với TINGEE', 'tingee' );

            //lay list bank tu api vietqr
            $this->bank_list = $this->get_vietqr_bank_list();
            

            // xac dinh bien.
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->account_name = $this->get_option( 'account_name' );
            $this->account_number = $this->get_option( 'account_number' );
            $this->va_account_number = $this->get_option('va_account_number');
            $this->template_id = $this->get_option( 'template_id' );
            $this->prefix = $this->get_option('prefix');
            $this->suffix = $this->get_option( 'suffix');
            $this->bank = $this->get_option('bank');
            $this->secret_token = $this->get_option('secret_token');
            $this->Url_webhook = $this->get_option('Url_webhook');


            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            // Actions.
            // add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'tingee_payment_gateway_validate' ),);

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ),);   
            
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page',) );
            
            // Customer Emails.
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
            // Filter to display the gateway in the list of payment methods on the checkout page.
            add_filter('woocommerce_available_payment_gateways', array($this, 'filter_available_payment_gateways'));
        }

        /**
        * Initialise Gateway Settings Form Fields.
        */
        public function init_form_fields(){
            $base_url = "$_SERVER[HTTP_HOST]";
            $base_url = home_url();
            $base_url = is_ssl() ? str_replace('http://', 'https://', $base_url) : $base_url;
            //Tự động sinh prefix đơn hàng cho website.
            $server_domain = $_SERVER['SERVER_NAME'];
            $shopname = preg_replace('#^.+://[^/]+#', '', $server_domain);
            $shopname = str_replace(".","",$shopname);

            //Tạo danh sách tên ngân hàng cho select form
            $bank_name = [];
            foreach ($this->bank_list['data'] as $bank) {
                if($bank['short_name'] === 'OCB'){
                $bank_name[$bank['short_name']] = $bank['short_name'];
            }
            }


            $this->form_fields = array(
                'enabled'         => array(
                    'title'   => __( 'Enable/Disable', 'woocommerce' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Bật/Tắt cổng thanh toán TINGEE', 'tingee' ),
                    'default' => 'yes',
                ),
                'title'           => array(
                    'title'       => __( 'Title', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'Điều chỉnh tựa đề mà khách hàng nhìn thấy trong quá trình thanh toán', 'woocommerce' ),
                    'default'     => __( 'Cổng thanh toán QR với TINGEE', 'tingee' ),
                    'desc_tip'    => true,
                ),
                'description'     => array(
                    'title'       => __( 'Description', 'woocommerce' ),
                    'type'        => 'textarea',
                    'description' => __( 'Mô tả phương thức thanh toán mà khách hàng thấy trong phần thanh toán của bạn', 'woocommerce' ),
                    'default'     => __( 'Thanh toán trực tiếp bằng mã QR hoặc chuyển khoản vào tài khoản của chúng tôi. Vui lòng chuyển khoản ghi rõ, đúng nội dung chuyển khoản theo đơn hàng của bạn. Đơn hàng của bạn sẽ không được vận chuyển nếu tiền chưa vào tài khoản của chúng tôi.', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'bank'           => array(
                    'title'       => __('Tên Ngân Hàng', 'tingee'),
                    'type'        => 'select',
                    'options'     => $bank_name,
                  ),
                'account_number' => array(
                    'title' => __( 'Số Tài Khoản', 'tingee'),
                    'type' => 'text',
                  ),
                'va_account_number' => array(
                    'title' => __( 'Số Tài Khoản Định Danh', 'tingee'),
                    'type' => 'text',
                  ),
                 'account_name' => array(
                    'title' => __( 'Tên Chủ Tài Khoản', 'tingee'),
                    'type' => 'text'
                  ),
                 'prefix'           => array(
                    'title'       => __('Tiền tố', 'tingee'),
                    'type'        => 'text',
                    'description' => __('Tiền tố dùng để kết hợp vào đằng trước với mã lệnh tạo nội dung chuyển tiền. Đặt quy tắc: không có dấu cách, không quá 15 ký tự và không có ký tự đặc biệt. Vi phạm sẽ bị xóa.', 'tingee'),
                    'default'     => __('TTHDINV', 'tingee'),
                    'desc_tip'    => true,
                  ),
                 'suffix'           => array(
                    'title'       => __('Hậu Tố', 'tingee'),
                    'type'        => 'text',
                    'description' => __('Hậu tố dùng để kết hợp vào đằng sau với mã lệnh tạo nội dung chuyển tiền. Đặt quy tắc: không có dấu cách, không quá 15 ký tự và không có ký tự đặc biệt. Vi phạm sẽ bị xóa.', 'tingee'),
                    'default'     => __('TINGEE','tingee'),
                    'desc_tip'    => true,
                  ),
                  // 'template_id' => array(
                  //   'title' => __( 'VietQR Template ID', 'tingee'),
                  //   'type' => 'text',
                  //   'default' => 'compact'
                  // ),
                  'secret_token'    => array(
                    'title'       =>__('Mã Bí Mật', 'tingee'),
                    'type'        =>'password',
                    'description' =>__('Mã bí mật lấy trong liên kết tài khoản của TINGEE', 'tingee'),
                    'desc_tip'    => true,
                  ),
                  
                  'Url_webhook'    => array(
                    'title'       =>__('Url webhook', 'tingee'),
                    'type'        =>'text',
                    'description' =>__('Url webhook liên kết với tingee', 'tingee'),
                    'default'     =>__(''.$base_url.'/wp-content/plugins/tingee-payment-gateway/payment-webhook-url.php','tingee'),
                    'desc_tip'    => true,
                  ),
            );
            /*
            $html = "<script src=\"https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js\"></script>
            <script>
                $(document).ready(function() {
                    $('#woocommerce_tingee_Url_webhook').attr('readonly', 'readonly');
                    $('#woocommerce_tingee_title').attr('readonly','readonly');
                    $('#woocommerce_tingee_description').attr('readonly','readonly');
                });
            </script>
            ";
            echo $html;
            */
        }
        public function add_custom_button_to_payment_settings($s){
            $html ='<i id="copyButton" class="far fa-copy" onclick="copyToClipboard()">aa</i>';
            echo $html;
            return $s;
        }
        public function process_admin_options(){
            $posted_data = $this->get_post_data();
                    // echo '<pre>';
                    // var_dump($posted_data);
                    // echo '</pre>';

                    // // Debug thông báo
                    // wp_die('Debug message');
                $validation_result = $this->validate_settings($posted_data);
                if (is_wp_error($validation_result)) {
                    foreach ($validation_result->get_error_messages() as $message) {
                        $this->add_error($message);
                        
                    }
                    add_action('admin_notices', array($this, 'admin_notices'));
                    return;
                }
                else{
                    return parent::process_admin_options();
                }

        } 
        public function admin_notices(){
            $errors = $this-> get_errors();

            if(!empty($errors)){
                foreach($errors as $error){
                     echo '<div class="error"><p>' . esc_html($error) . '</p></div>';
                }
            }
        }



            private function validate_settings($data) {

                if (empty($data['woocommerce_tingee_account_number']) || !is_numeric($data['woocommerce_tingee_account_number'])) {
                    return new WP_Error('missing_account_number', __('Please enter a valid numeric value for your account number.', 'tingee'));
                }
                if (empty($data['woocommerce_tingee_va_account_number'])){
                    return new WP_Error('missing_va_account', __('Pls enter a valid va_account number for your account number.', 'tingee'));
                }
                if (empty($data['woocommerce_tingee_account_name'])){
                    return new WP_Error('missing_account_name', __('pls','tingee'));
                }
                if (empty($data['woocommerce_tingee_prefix'])){
                    return new WP_Error('missing_prefix', __('pls type prefix', 'tingee'));
                }
                if (empty($data['woocommerce_tingee_suffix'])){
                    return new WP_Error('missing_suffix', __('pls type suffix', 'tingee'));
                }
                // $errors = new WP_Error();

                // if(empty($data['woocommerce_tingee_account_number']) || !is_numeric($data['woocommerce_tingee_account_number'])){
                //     $errors -> add('invalid field', __('your field is required', 'tingee'));
                // }

                // return apply_filters('woocommerce_gateway_validate_setting_'. $this->id, $errors, $data);


                return true;
            }


        /**
         * Filter to display the gateway in the list of payment methods on the checkout page.
         */
        public function filter_available_payment_gateways($available_gateways) {
            if ($this->is_available()) {
                $available_gateways[$this->id] = $this;
            }
            return $available_gateways;
        }
        /**
         * Check if the gateway is available for use.
         */
        public function is_available() {
            // Implement any logic to check if the gateway should be available.
            return true;
        }
        /**
         * Output for the order received page.
         *
         * @param int $order_id Order ID.
         */
        public function redirect_on_order_status_change($order_id, $old_status, $new_status) {
        // Kiểm tra nếu trạng thái mới là "processing" và trạng thái cũ là "pending"
            if ($new_status === 'processing' && $old_status === 'pending') {
        // Lấy URL trang đơn hàng và thực hiện redirect
            $order_url = get_permalink(wc_get_page_id('myaccount')) . 'view-order/' . $order_id . '/';
            wp_redirect($order_url);
            exit;
                }
            }

        public function thankyou_page( $order_id ) {
 
            $notif = '<h1 style="background-color:#F5F5F5; color:#8470FF ;text-align:center">Thanh toán thành công, đơn hàng của bạn đang được xử lý và giao hàng sớm nhất!</h1>';     
            $order = wc_get_order($order_id);
            if($order->get_status() === 'pending'){
            $this->payment_details( $order_id );
            }
            else{
                echo $notif;
            }

       }
        
        /**
         * Add content to the WC emails.
         *
         * @param WC_Order $order Order object.
         * @param bool     $sent_to_admin Sent to admin.
         * @param bool     $plain_text Email format: plain text or HTML.
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            if (!$sent_to_admin && 'tingee' === $order->get_payment_method() && $order->has_status('processing')) {
                $this->payment_details($order->get_id());
            }
        }

        private function payment_details($order_id) {

            // Get order and store in $order.
            $order = wc_get_order($order_id);
            if (isset($_GET['key'])){
                $order_key = sanitize_text_field($_GET['key']);
            }

            // Get VietQR Image URL and Pay URL
            $data = $this->get_vietqr_img_url($order_id);
            $qrcode_image_url  = $data['img_url'];
            $qrcode_page_url = $data['pay_url'];

            $bank_shortname = $this->bank;
            $bank_data = $this->search_bank_info($bank_shortname);
            $bank_name = $bank_data['name'];
            $bank_logo  = $bank_data['logo'];

            $html  = '';        
            $html .= '<section class="tingee-payment">';
            $html .= '<h3>Chuyển khoản ngân hàng</h3>';
            $html .= '<div class="tingee-payment-detail">
                        <div class="tingee-qr-code">';
            
            if ($qrcode_image_url) {
                $html .= '<div id="qrcode">
                        <img src="' . esc_html($qrcode_image_url) . '"  alt="VietQR QR Image" width="400px" />
                      </div>';
            }
            $html .= '<div class="bank-name"><img src="'. esc_html($bank_logo).'" alt="'. $bank_name .'" width="100px" /><span>Ngân hàng </span><strong> '. $bank_name . '</strong></div>';
            $html .= '</div>';

            $html .= '<div class="bank-info order-amount"><span>Số tiền:</span><strong> '. number_format($order->get_total()) . " VND" .'</strong></div>';
            $html .= '<div class="bank-info account-number"><span>Số tài khoản:</span><strong> '. $this->va_account_number . '</strong></div>';
            $html .= '<div class="bank-info account-name"><span>Tên tài khoản:</span><strong> '. $this->account_name . '</strong></div>';
            $html .= '<div class="bank-info prefix"><span>Nội dung chuyển khoản:</span> <strong>'. $this->prefix . $order->get_order_number() . $this->suffix .'</strong></div>';
            $html .= '<h2 class="alert alert-info" style="text-align:center">Vui lòng không thay đổi và nhập đúng nội dung chuyển khoản <strong style="color:red">' .  $this->prefix . $order_id   . $this->suffix .'</strong> để đơn hàng được xử lý nhanh nhất.</h2> <br/>';

            $html .= '</div></section>';
            $html .= '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
            $html .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">';

            

            $html .= '<!-- STYLE CSS-->
                        <style>
                            .tingee-payment {
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                margin: 40px auto;
                                max-width: 800px;
                            }

                            .tingee-payment-detail {
                                border: 1px solid #DDD;
                                margin-top: 20px;
                            }

                            .tingee-payment-detail > div {
                                padding: 20px;
                                border-bottom: 1px solid #DDD;
                            }

                            .tingee-payment-detail > div:last-of-type {       
                                border-bottom: 0;
                            }
                            
                            #qrcode {
                                background: #FFF;
                                position: relative;
                                display: flex;
                                justify-content: center;
                                max-width: 400px;
                                margin: 0 auto 40px;
                            }
                            
                            #qrcode:before {
                                content: "";
                                position: absolute;
                                top: 0; right: 0; bottom: 0; left: 0;
                                z-index: -1;
                                margin: -10px;
                                border-radius: inherit;
                                background: linear-gradient(to right, #21447B, #A2CA46);
                                border-radius: 15px;
                            }
                            #qrcode img {
                                padding: 10px 0;
                            }
                            /*.bank-info {
                                width: 100%;
                            }
                            .bank-info div {
                                border-bottom: 1px solid #EEE;
                                padding: 5px 0;
                            }*/
                            
                            .bank-info span:nth-child(2) {
                                font-weight: bold;
                            }
                            .bank-name  {
                                display: grid; 
                                grid-template-columns: minmax(50px, 100px) 1fr; 
                                grid-template-rows: 1fr 1fr; 
                                gap: 0px 0px; 
                                grid-template-areas: 
                                  "logo ."
                                  "logo ."; 
                              }
                              .bank-name img { 
                                  grid-area: logo; 
                                  align-self: center;
                              }

                              .button {
                                background-color: #000;
                                color: #fff;
                                padding: 10px 20px;
                                border-radius: 20px;
                                text-decoration: none;
                                text-align: center;
                                }

                            .button:hover {
                                background-color: #333;
                                }
                            .swal-footer {
                                display: flex;
                                justify-content: center;
                                align-item: center;
                            }
                            </style>';
            $html .= "
                <script src=\"https://code.jquery.com/jquery-3.6.4.min.js\"></script>
                <script>
                    var base_url = window.location.origin;
                    var check = false;
                    function checkOrderStatus(){
                        $.ajax({
                            url: base_url + '/wp-content/plugins/tingee-payment-gateway/check-payment-status.php?order_id=$order_id',
                            type: 'GET',
                            dataType: 'json',
                            success: function(data) {
                                if (data.payment) {
                                    if(!check){
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Bạn đã thanh toán thành công!',
                                            text: 'Đơn hàng của bạn đang được xử lý và giao hàng trong thời gian sớm nhất',
                                            showConfirmButton: true
                                        });
                                        check = true;
                                        $(\".tingee-payment\").hide();
                                    }
                                }
                            },
                            error: function(error) {
                                console.error('Error checking order status:', error);
                            }
                        });
                    }
                    $(document).ready(function(){
                        setInterval(checkOrderStatus, 5000);
                    });
                </script>
            ";   


            echo $html;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id ) {
    
            $order =  wc_get_order( $order_id );
    

            if($order->get_total()>0){
             // Mark as on-hold (we're awaiting the payment).
            
            $order->update_status('pending', __('Awaiting bank transfer payment', 'woocommerce'));}
            else{
                $order->payment_complete();
            }
            // Reduce stock levels (if needed).
            // order -> reduce_order_stock();
            // Remove cart.
            WC()->cart->empty_cart();

    
            // Return thankyou redirect.
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
    
        }

        public function get_vietqr_img_url($order_id) {

            // Get order and store in $order.
            $order = wc_get_order($order_id);

            $accountNo = $this->va_account_number;
            $accountName = $this->account_name;
            $bank = $this->bank;
            $amount = $order->get_total();
            $info = $this->prefix . $order_id . $this->suffix;
            
            $template = $this->template_id;

            $img_url = get_transient( 'vietqr_img_url_'.$order_id );
            $pay_url = get_transient( 'vietqr_pay_url_'.$order_id );

            if ( false === $img_url ) {
                $img_url = "https://img.vietqr.io/image/{$bank}-{$accountNo}-compact.jpg?amount={$amount}&addInfo={$info}&accountName={$accountName}";
            }

            if ( false === $pay_url ) {
                $pay_url = "https://api.vietqr.io/{$bank}/{$accountNo}/{$amount}/{$info}";
            }

            set_transient( 'vietqr_img_url_'.$order_id, $img_url, DAY_IN_SECONDS );
            set_transient( 'vietqr_pay_url_'.$order_id, $pay_url, DAY_IN_SECONDS );

            return array(
                "img_url" => $img_url,
                "pay_url" => $pay_url,
            );
        }

        public function get_vietqr_bank_list() {

            $body = get_transient( 'vietqr_banklist' );
            
            if ( false === $body ) {
                $url = "https://api.vietqr.io/v2/banks";
                $response = wp_remote_get($url );
            
                if (200 !== wp_remote_retrieve_response_code($response)) {
                    return;
                }
            
                $body = wp_remote_retrieve_body($response);
                set_transient( 'vietqr_banklist', $body, DAY_IN_SECONDS );
            }

            $bank_list = json_decode($body, true);
            
            
            return $bank_list;
        }

        public function search_bank_info($bank) {
            foreach ($this->bank_list['data'] as $bank_data) {
                if ($bank_data['short_name'] === $bank) {
                    if($bank === 'OCB'){
                    return array(
                        "name" => $bank_data['name'],
                        "logo" => $bank_data['logo'],

                        );
                    break;

                    }
                }
            }

            return null;
         }

    }
} 


