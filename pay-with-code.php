<?php
/*
Plugin Name: Pay with Code
Description: Payment gateway for WooCommerce allowing payments via code.
Version: 1.0
Author: <a href="mailto:holakhunle@gmail.com">Dynasty</a>
Contributors: Dynahsty
Tags: woocommerce, payment, gateway, extension, secure checkout
Donate link: https://flutterwave.com/donate/pylumi0ufo1d
Requires at least: 5.0
Tested up to: 6.6.1
Requires PHP: 7.4
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}


function pwcp_enqueue_admin_styles($hook_suffix) {
    switch ($hook_suffix) {
        case 'toplevel_page_pwcp_settings':
            wp_enqueue_style('pwcp-settings-css', plugins_url('pay-css/pwcp-settings.css', __FILE__), array(), filemtime(plugin_dir_path(__FILE__) . 'pay-css/pwcp-settings.css'));
            break;
        case 'payadmin_page_pwcp_generate':
            wp_enqueue_style('pwcp-generate-css', plugins_url('pay-css/pwcp-generate.css', __FILE__), array(), filemtime(plugin_dir_path(__FILE__) . 'pay-css/pwcp-generate.css'));
            break;
        case 'payadmin_page_pwcp_logs':
            wp_enqueue_style('pwcp-logs-css', plugins_url('pay-css/pwcp-logs.css', __FILE__), array(), filemtime(plugin_dir_path(__FILE__) . 'pay-css/pwcp-logs.css'));
            break;
        case 'payadmin_page_pwcp_donation':
            wp_enqueue_style('pwcp-donation-css', plugins_url('pay-css/pwcp-donation.css', __FILE__), array(), filemtime(plugin_dir_path(__FILE__) . 'pay-css/pwcp-donation.css'));
            break;
        case 'payadmin_page_pwcp_clear_generated_codes':
            wp_enqueue_style('pwcp-clear-codes-css', plugins_url('pay-css/pwcp-clear-codes.css', __FILE__), array(), filemtime(plugin_dir_path(__FILE__) . 'pay-css/pwcp-clear-codes.css'));
            break;
    }
}
add_action('admin_enqueue_scripts', 'pwcp_enqueue_admin_styles');



function pwcp_enqueue_admin_scripts($hook_suffix) {
    if ('payadmin_page_pwcp_logs' === $hook_suffix) {
        $version = filemtime(plugin_dir_path(__FILE__) . 'pay-js/pwcp-admin.js');
        
        wp_enqueue_script('pwcp-admin-js', plugins_url('pay-js/pwcp-admin.js', __FILE__), array('jquery'), $version, true);
        
        $data = array(
            'generated_codes' => get_option('pwcp_generated_codes', array())
        );
        
        wp_localize_script('pwcp-admin-js', 'pwcpData', $data);
    }
}
add_action('admin_enqueue_scripts', 'pwcp_enqueue_admin_scripts');



add_action('admin_init', 'pwcp_process_deactivation');

function pwcp_process_deactivation() {
    if (isset($_GET['deactivate_code'])) {
       if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'deactivate_nonce_action')) {
            die('Security check failed');
        }

        $code_to_deactivate = sanitize_text_field($_GET['deactivate_code']);
        $generated_codes = get_option('pwcp_generated_codes', array());

        foreach ($generated_codes as &$code_info) {
            if ($code_info['code'] === $code_to_deactivate) {
                $code_info['status'] = 'Deactivated';
                break;
            }
        }

        update_option('pwcp_generated_codes', $generated_codes);
        wp_redirect(admin_url('admin.php?page=pwcp_logs'));
        exit;
    }
}

add_action('plugins_loaded', 'pwcp_init_gateway');


function pwcp_init_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class PWCP_Payment_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'pwcp';
            $this->has_fields = true;
            $this->method_title = __('Pay with Code', 'pay-with-code');
            $this->method_description = __('Have your customers pay with a unique generated purchase code. Fast and secured payments.', 'pay-with-code');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->auto_complete = $this->get_option('auto_complete');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'pay-with-code'),
                    'type' => 'checkbox',
                    'label' => __('Enable Pay with Code', 'pay-with-code'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title' => __('Title', 'pay-with-code'),
                    'type' => 'text',
                    'description' => __('This controls what the user sees during checkout.', 'pay-with-code'),
                    'default' => __('Pay with Code', 'pay-with-code'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'pay-with-code'),
                    'type' => 'textarea',
                    'description' => __('This controls what the user sees during checkout. You can edit as you prefer ', 'pay-with-code'),
                    'default' => __('Pay securely using the code bought from the admin.', 'pay-with-code'),
                ),
                'auto_complete' => array(
                    'title' => __('Auto Complete Order', 'pay-with-code'),
                    'type' => 'checkbox',
                    'description' => __('If enabled, the order will be marked as complete upon sucessful payment.', 'pay-with-code'),
                    'label' => __('Automatically complete the order upon successful payment', 'pay-with-code'),
                    'default' => 'no',
                ),
            );
        }

        public function process_payment($order_id) {
            if (!isset($_POST['pwcp_process_payment_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pwcp_process_payment_nonce'])), 'pwcp_process_payment_nonce_action')) {
                wc_add_notice(__('Nonce verification failed', 'pay-with-code'), 'error');
                return array(
                    'result' => 'failure'
                );
            }

            $order = wc_get_order($order_id);
            $custom_code = isset($_POST['custom_code']) ? sanitize_text_field($_POST['custom_code']) : '';

            if (empty($custom_code)) {
                wc_add_notice(__('Please enter your purchase code.', 'pay-with-code'), 'error');
                return array(
                    'result' => 'failure'
                );
            }

            $stored_codes = get_option('pwcp_generated_codes', array());

            $code_found = false;
            $today = gmdate('Y-m-d');
            foreach ($stored_codes as &$code_info) {
                if ($code_info['code'] === $custom_code) {
                    $code_found = true;
                    if ($code_info['status'] === 'Used') {
                        wc_add_notice(__('This code has already been used.', 'pay-with-code'), 'error');
                        return array(
                            'result' => 'failure'
                        );
                    } elseif ($code_info['status'] === 'Deactivated') {
                        wc_add_notice(__('This code has been deactivated.', 'pay-with-code'), 'error');
                        return array(
                            'result' => 'failure'
                        );
                    } elseif (strtotime($code_info['expiration_date']) < strtotime($today)) {
                        wc_add_notice(__('This code has expired.', 'pay-with-code'), 'error');
                        return array(
                            'result' => 'failure'
                        );
                    } else {
                        $code_info['status'] = 'Used';
                        $user = get_user_by('email', $order->get_billing_email());
                        $code_info['used_by'] = $user ? $user->user_login : $order->get_billing_email();
                        update_option('pwcp_generated_codes', $stored_codes);

                        $this->send_email_notifications($order, $custom_code, $code_info['used_by']);

                        if ($this->auto_complete === 'yes') {
                            $order->update_status('completed', __('Payment received, your order is now completed.', 'pay-with-code'));
                        } else {
                            $order->update_status('processing', __('Payment received, your order is being processed.', 'pay-with-code'));
                        }

                        wc_reduce_stock_levels($order_id);

                        WC()->cart->empty_cart();

                        return array(
                            'result' => 'success',
                            'redirect' => $this->get_return_url($order)
                        );
                    }
                }
            }

            if (!$code_found) {
                wc_add_notice(__('Invalid purchase code.', 'pay-with-code'), 'error');
                return array(
                    'result' => 'failure'
                );
            }
        }

        private function send_email_notifications($order, $custom_code, $user_display_name) {
            $user_email = $order->get_billing_email();
            $order_id = $order->get_id();

            $email_styles = "
                <style>
                    .email-body {
                        font-family: 'Helvetica', Arial, sans-serif;
                        font-size: 14px;
                        color: #555;
                    }
                    .email-text {
                        font-weight: bold;
                        color: #333;
                    }
                    .email-subject {
                        font-family: 'Helvetica', Arial, sans-serif;
                        font-size: 12px;
                        color: #666;
                    }
                </style>
            ";

            $user_email_body = "
                <div class='email-body' style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border-radius: 16px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);'>
                    <div style='background-color: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);'>
                        <p class='email-text'>Hello, " . esc_html($user_display_name) . ".</p>
                        <p>Your purchase code <strong>" . esc_html($custom_code) . "</strong> has been successfully used for your order <strong>" . esc_html($order_id) . "</strong>.</p>
                        <p class='email-text'>Thanks for your patronage!</p>
                    </div>
                </div>
            ";
            $user_email_body = $email_styles . $user_email_body;

            wp_mail($user_email, __('Your Purchase Code has been Used!', 'pay-with-code'), $user_email_body, array('Content-Type: text/html; charset=UTF-8'));

            $admin_email_body = "
                <div class='email-body' style='max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);'>
                    <h2 class='email-subject'>" . __('Generated Code Used', 'pay-with-code') . "</h2>
                    <div style='background-color: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);'>
                        <p>The generated code <strong>" . esc_html($custom_code) . "</strong> has been successfully used for order <strong>" . esc_html($order_id) . "</strong> by " . esc_html($user_display_name) . ".</p>
                    </div>
                </div>
            ";
            $admin_email_body = $email_styles . $admin_email_body;

            wp_mail(get_option('admin_email'), __('Generated Code Used', 'pay-with-code'), $admin_email_body, array('Content-Type: text/html; charset=UTF-8'));
        }

        public function payment_fields() {
            $description = $this->get_description();
            ?>
            <div class="form-row form-row-wide">
                <label for="custom_code"><?php esc_html_e('Enter your purchase code', 'pay-with-code'); ?><span class="required">*</span></label>
                <input type="text" id="custom_code" name="custom_code" class="input-text" required />
            </div>
            <?php if (!empty($description)) : ?>
            <div class="form-row form-row-wide">
                <small><?php echo wp_kses_post($description); ?></small>
            </div>
            <?php endif; ?>
            <?php
            if (wc_notice_count('error') > 0) {
                echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide" style="color: red;">';
                wc_print_notices(array('notice', 'error'));
                echo '</p>';
            }
            wp_nonce_field('pwcp_process_payment_nonce_action', 'pwcp_process_payment_nonce');
            ?>
            
  
            <?php
        }
    }


function enqueue_insert_image_script() {
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }

    $version = filemtime(plugin_dir_path(__FILE__) . 'pay-js/insert-image.js');
    
    wp_register_script(
        'insert-image-script',
        plugin_dir_url(__FILE__) . 'pay-js/insert-image.js',
        array('jquery'),
        $version,
        true
    );

    wp_localize_script('insert-image-script', 'pwcp_image_url', esc_url(plugins_url('images/pwcp-img.png', __FILE__)));

    wp_enqueue_script('insert-image-script');
}
add_action('wp_enqueue_scripts', 'enqueue_insert_image_script');


    function pwcp_add_gateway($methods) {
        $methods[] = 'PWCP_Payment_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'pwcp_add_gateway');
}

add_action('admin_menu', 'pwcp_admin_menu');

function pwcp_admin_menu() {
    add_menu_page(
        __('PayAdmin', 'pay-with-code'),
        __('PayAdmin', 'pay-with-code'),
        'manage_options',
        'pwcp_settings',
        'pwcp_dashboard_page',
        'dashicons-admin-users'
    );

    add_submenu_page(
        'pwcp_settings',
        __('Generate Codes', 'pay-with-code'),
        __('Generate Codes', 'pay-with-code'),
        'manage_options',
        'pwcp_generate',
        'pwcp_generate_page'
    );

    add_submenu_page(
        'pwcp_settings',
        __('Logs', 'pay-with-code'),
        __('Logs', 'pay-with-code'),
        'manage_options',
        'pwcp_logs',
        'pwcp_logs_page'
    );

    add_submenu_page(
        'pwcp_settings',
        __('Clear Codes', 'pay-with-code'),
        __('Clear Codes', 'pay-with-code'),
        'manage_options',
        'pwcp_clear_generated_codes',
        'pwcp_clear_generated_codes_page'
    );

    add_submenu_page(
        'pwcp_settings',
        __('Donation', 'pay-with-code'),
        __('Donation', 'pay-with-code'),
        'manage_options',
        'pwcp_donation',
        'pwcp_donation_page'
    );
}

function pwcp_dashboard_page() {
    ?>
    <div class="wrap pwcp-dashboard">
        <div class="pwcp-container">
            <div class="dashboard-card">
<h1 class="dashboard-title"><?php echo esc_html(__('Welcome to PayAdmin Dashboard', 'pay-with-code')); ?></h1>
                <div class="card-container">
                    <div class="card admin-card">
                        <div class="card-body">
                     <h5 class="card-title"><?php echo esc_html(__('Admins', 'pay-with-code')); ?></h5>
                            <p class="card-text"><?php echo count(get_users(array('role' => 'administrator'))); ?></p>
                        </div>
                    </div>
                    <div class="card user-card">
                        <div class="card-body">
                           <h5 class="card-title"><?php echo esc_html(__('Members', 'pay-with-code')); ?></h5>
                            <p class="card-text"><?php echo count(get_users(array('role__not_in' => array('administrator')))); ?></p>
                        </div>
                    </div>
                    <div class="card generated-code-card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo esc_html(__('Generated Codes', 'pay-with-code')); ?></h5>
                            <?php
                            $generated_codes = get_option('pwcp_generated_codes', array());
                            $num_generated_codes = count($generated_codes);
                            ?>
                           <p class="card-text"><?php echo esc_html($num_generated_codes); ?></p>
                        </div>
                    </div>
                </div>
                <div class="footer">
<p>
  <?php 
// translators: %s is the current year
echo esc_html(sprintf(__('Copyright &copy; %s PayWithCode. Developed by Dynasty', 'pay-with-code'), gmdate('Y')));
 
  ?>
</p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function pwcp_generate_page() {
    ?>
    <div class="wrap pwcp-dashboard">
        <div class="pwcp-container">
            <div class="dashboard-card">
                <h1 class="dashboard-title"><?php esc_html_e('Generate Codes', 'pay-with-code'); ?></h1>
                <form method="post">
                    <?php wp_nonce_field('pwcp_generate_codes_action', 'pwcp_generate_codes_nonce'); ?>
                    <div class="form-group">
                        <label for="num_codes"><?php esc_html_e('Number of Codes to Generate:', 'pay-with-code'); ?></label>
                        <input type="number" id="num_codes" name="num_codes" min="1" value="1" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="expiration_date"><?php esc_html_e('Expiration Date:', 'pay-with-code'); ?></label>
                        <input type="date" id="expiration_date" name="expiration_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="email"><?php esc_html_e('Send Codes to Email (optional):', 'pay-with-code'); ?></label>
                        <input type="email" id="email" name="email" placeholder="<?php esc_attr_e('Enter customer mail', 'pay-with-code'); ?>" class="form-control">
                    </div>
                   <button type="submit" name="generate_codes" class="btn btn-primary" style="background-color: #ff7e5f; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px;">
    <?php esc_html_e('Generate Codes', 'pay-with-code'); ?>
</button>

                </form>

                <?php
                if (isset($_POST['generate_codes'])) {
                    if (!isset($_POST['pwcp_generate_codes_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pwcp_generate_codes_nonce'])), 'pwcp_generate_codes_action')) {
                        echo '<div class="alert alert-danger" role="alert">' . esc_html(__('Security check failed.', 'pay-with-code')) . '</div>';
                        return;
                    }

                    $num_codes = isset($_POST['num_codes']) ? absint($_POST['num_codes']) : 1;
                    $generated_codes = array();
                    $expiration_date = isset($_POST['expiration_date']) ? sanitize_text_field($_POST['expiration_date']) : '';
                    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

                    if (!empty($expiration_date) && strtotime($expiration_date) === false) {
                        echo '<div class="alert alert-danger" role="alert">' . esc_html__('Invalid expiration date.', 'pay-with-code') . '</div>';
                        return;
                    }

                    for ($i = 0; $i < $num_codes; $i++) {
                        $code = strtoupper(wp_generate_password(8, false));
                        $timestamp = current_time('mysql');
                        $generated_codes[] = array(
                            'code' => $code,
                            'status' => 'Not Used',
                            'timestamp' => $timestamp,
                            'expiration_date' => $expiration_date
                        );
                    }

                    $saved_codes = get_option('pwcp_generated_codes', array());
                    $saved_codes = array_merge($saved_codes, $generated_codes);
                    update_option('pwcp_generated_codes', $saved_codes);

                   // translators: %d is the number of generated codes
echo '<div class="alert alert-success" role="alert">' . esc_html(sprintf(__('Successfully generated %d codes.', 'pay-with-code'), $num_codes)) . '</div>';


                    echo '<div class="generated-codes">';
                    echo '<h3>' . esc_html__('Generated Codes:', 'pay-with-code') . '</h3>';
                    echo '<div class="code-preview-container">';

                    foreach ($generated_codes as $generated_code) {
                        echo '<div class="code-preview">';
                        echo '<div class="code">' . esc_html($generated_code['code']) . '</div>';
                        echo '<div class="code-details">';
                        echo '<span>' . esc_html__('Generated on:', 'pay-with-code') . ' ' . esc_html($generated_code['timestamp']) . '</span><br>';
                        echo '<span>' . esc_html__('Expiration Date:', 'pay-with-code') . ' ' . esc_html($generated_code['expiration_date']) . '</span>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';

                    if (!empty($email)) {
                        $subject = __('Your Generated Codes', 'pay-with-code');
                        $message = "
                            <style>
                                .email-body {
                                    font-family: 'Helvetica', Arial, sans-serif;
                                    font-size: 14px;
                                    color: #555;
                                    max-width: 600px;
                                    margin: 0 auto;
                                    padding: 20px;
                                    background-color: #f9f9f9;
                                    border-radius: 16px;
                                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                                }
                                .email-text {
                                    font-weight: bold;
                                    color: #333;
                                }
                                .email-subject {
                                    font-family: 'Helvetica', Arial, sans-serif;
                                    font-size: 12px;
                                    color: #666;
                                }
                                .code-list {
                                    list-style: none;
                                    padding: 0;
                                }
                                .code-list li {
                                    margin-bottom: 10px;
                                    padding: 10px;
                                    background: #f9f9f9;
                                    border: 1px solid #ddd;
                                }
                            </style>
                            <div class='email-body'>
                                <div style='background-color: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);'>
                                    <p class='email-text'>Hello,</p>
                                    <p>Here are your generated codes:</p>
                                    <ul class='code-list'>
                        ";
                        foreach ($generated_codes as $generated_code) {
                            $message .= '<li>' . esc_html($generated_code['code']) . ' - ' . esc_html($generated_code['timestamp']) . ' - Exp: ' . esc_html($generated_code['expiration_date']) . '</li>';
                        }
                        $message .= "
                                    </ul>
                                    <p>You can apply the code at the pay with code checkout field</p>
                                    <p class='email-text'>Thanks for your patronage!</p>
                                </div>
                            </div>
                        ";
                        wp_mail($email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
                        // translators: %s is the email address where the codes have been sent
echo '<div class="alert alert-info" role="alert">' . esc_html(sprintf(__('Codes have been sent to %s.', 'pay-with-code'), esc_html($email))) . '</div>';

                    }
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

function pwcp_logs_page() {
    $generated_codes = get_option('pwcp_generated_codes', array());
    $codes_per_page = 5;
    $total_codes = count($generated_codes);
    $total_pages = ceil($total_codes / $codes_per_page);
   $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

    $offset = ($current_page - 1) * $codes_per_page;
    $codes_to_display = array_slice($generated_codes, $offset, $codes_per_page);
    ?>
    <div class="background-container">
        <div class="wrap pwcp-logs">
            <h1 class="dashboard-title"><?php esc_html_e('Generated Codes', 'pay-with-code'); ?></h1>

            <input type="text" id="searchInput" placeholder="<?php esc_attr_e('Search for codes...', 'pay-with-code'); ?>">

            <button id="exportButton" class="button export-code"><?php esc_html_e('Export Codes', 'pay-with-code'); ?></button>

            <?php
            if (!empty($codes_to_display)) {
                ?>
                <div class="logs-container row row-cols-1 row-cols-md-2 g-4">
                    <?php foreach ($codes_to_display as $code_info): ?>
                        <div class="card col-md-6 mb-4 code-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo esc_html(__('Code', 'pay-with-code')); ?></h5>
                                <p class="card-text"><?php echo esc_html($code_info['code']); ?></p>
                                <?php if ($code_info['status'] !== 'Used' && $code_info['status'] !== 'Deactivated' && strtotime($code_info['expiration_date']) >= strtotime(gmdate('Y-m-d'))): ?>
                                    <?php
                                    $nonce = wp_create_nonce('deactivate_nonce_action');
                                    $deactivate_url = esc_url(add_query_arg([
                                        'deactivate_code' => $code_info['code'],
                                        '_wpnonce' => $nonce
                                    ]));
                                    ?>
                                    <a href="<?php echo esc_url($deactivate_url); ?>" class="button deactivate-code"><?php echo esc_html(__('Deactivate', 'pay-with-code')); ?></a>
                                <?php endif; ?>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <span class="badge <?php echo esc_attr(($code_info['status'] === 'Used') ? 'bg-danger' : ((strtotime($code_info['expiration_date']) < strtotime(gmdate('Y-m-d'))) ? 'bg-warning' : ($code_info['status'] === 'Deactivated' ? 'bg-secondary' : 'bg-success'))); ?>">
                                        <?php 
                                        if ($code_info['status'] === 'Used') {
                                            echo esc_html(__('Used', 'pay-with-code'));
                                        } elseif (strtotime($code_info['expiration_date']) < strtotime(gmdate('Y-m-d'))) {
                                            echo esc_html(__('Expired', 'pay-with-code'));
                                        } elseif ($code_info['status'] === 'Deactivated') {
                                            echo esc_html(__('Deactivated', 'pay-with-code'));
                                        } else {
                                            echo esc_html(__('Not Used', 'pay-with-code'));
                                        }
                                        ?>
                                    </span>
                                </li>
                                <li class="list-group-item"><?php echo esc_html(__('Used By', 'pay-with-code')); ?>: <?php echo isset($code_info['used_by']) ? esc_html($code_info['used_by']) : esc_html(__('?', 'pay-with-code')); ?></li>
                                <li class="list-group-item"><?php echo esc_html(__('Generated Date', 'pay-with-code')); ?>: <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($code_info['timestamp']))); ?></li>
                                <li class="list-group-item"><?php echo esc_html(__('Expiration Date', 'pay-with-code')); ?>: <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($code_info['expiration_date']))); ?></li>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="pagination">
                    <?php
                    $base_url = remove_query_arg('paged');
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $page_url = add_query_arg('paged', $i, $base_url);
                        echo '<a href="' . esc_url($page_url) . '" class="page-number ' . ($current_page === $i ? 'current' : '') . '">' . esc_html($i) . '</a>';
                    }
                    ?>
                </div>
                <?php
            } else {
                echo '<p class="no-logs">' . esc_html__('No generated codes found.', 'pay-with-code') . '</p>';
            }
            ?>
        </div>
    </div>


    <?php
}


function pwcp_clear_generated_codes_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['pwcp_clear_codes_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pwcp_clear_codes_nonce'])), 'pwcp_clear_codes_action')) {
            die('Security check failed.');
        }

        if (isset($_POST['pwcp_clear_used_codes']) && $_POST['pwcp_clear_used_codes'] === '1') {
            pwcp_clear_codes_by_status('Used');
        }

        if (isset($_POST['pwcp_clear_unused_codes']) && $_POST['pwcp_clear_unused_codes'] === '1') {
            pwcp_clear_codes_by_status('Not Used');
        }

        if (isset($_POST['pwcp_clear_expired_codes']) && $_POST['pwcp_clear_expired_codes'] === '1') {
            pwcp_clear_codes_by_status('Expired');
        }

        if (isset($_POST['pwcp_clear_deactivated_codes']) && $_POST['pwcp_clear_deactivated_codes'] === '1') {
            pwcp_clear_codes_by_status('Deactivated');
        }
    }
    ?>
    <div class="wrap">
        <div class="pwcp-clear-generated-codes">
            <h1 class="pwcp-page-title"><?php echo esc_html__('Clear Generated Codes', 'pay-with-code'); ?></h1>
            <form method="post" class="pwcp-clear-codes-form">
                <?php wp_nonce_field('pwcp_clear_codes_action', 'pwcp_clear_codes_nonce'); ?>
                <p class="pwcp-description"><?php echo esc_html__('This will clear all selected types of generated codes. Are you sure you want to proceed?', 'pay-with-code'); ?></p>
                <div class="pwcp-form-group">
                    <input type="checkbox" name="pwcp_clear_used_codes" value="1" id="pwcp_clear_used_codes">
                    <label for="pwcp_clear_used_codes"><?php echo esc_html__('Clear Used Codes', 'pay-with-code'); ?></label>
                </div>
                <div class="pwcp-form-group">
                    <input type="checkbox" name="pwcp_clear_unused_codes" value="1" id="pwcp_clear_unused_codes">
                    <label for="pwcp_clear_unused_codes"><?php echo esc_html__('Clear Unused Codes', 'pay-with-code'); ?></label>
                </div>
                <div class="pwcp-form-group">
                    <input type="checkbox" name="pwcp_clear_expired_codes" value="1" id="pwcp_clear_expired_codes">
                    <label for="pwcp_clear_expired_codes"><?php echo esc_html__('Clear Expired Codes', 'pay-with-code'); ?></label>
                </div>
                <div class="pwcp-form-group">
                    <input type="checkbox" name="pwcp_clear_deactivated_codes" value="1" id="pwcp_clear_deactivated_codes">
                    <label for="pwcp_clear_deactivated_codes"><?php echo esc_html__('Clear Deactivated Codes', 'pay-with-code'); ?></label>
                </div>
                <input type="submit" class="button button-primary" value="<?php echo esc_html__('Clear Codes', 'pay-with-code'); ?>">
            </form>
        </div>
    </div>
   

    <?php
}

function pwcp_clear_codes_by_status($status) {
    $generated_codes = get_option('pwcp_generated_codes', array());
    $codes_to_clear = array();

    if (!empty($generated_codes)) {
        foreach ($generated_codes as $key => $code_info) {
           $is_expired = strtotime($code_info['expiration_date']) < strtotime(gmdate('Y-m-d'));

            if ($status === 'Expired' && $is_expired) {
                $codes_to_clear[] = $key;
            } elseif ($status === 'Not Used' && $code_info['status'] === 'Not Used' && !$is_expired) {
                $codes_to_clear[] = $key;
            } elseif ($status !== 'Expired' && $status !== 'Not Used' && $code_info['status'] === $status) {
                $codes_to_clear[] = $key;
            }
        }

        foreach ($codes_to_clear as $key) {
            unset($generated_codes[$key]);
        }

        update_option('pwcp_generated_codes', array_values($generated_codes));

        $message = '';
        switch ($status) {
            case 'Used':
                $message = esc_html__('Used codes have been cleared.', 'pay-with-code');
                break;
            case 'Not Used':
                $message = esc_html__('Unused codes have been cleared.', 'pay-with-code');
                break;
            case 'Expired':
                $message = esc_html__('Expired codes have been cleared.', 'pay-with-code');
                break;
            case 'Deactivated':
                $message = esc_html__('Deactivated codes have been cleared.', 'pay-with-code');
                break;
        }
        echo '<div class="pwcp-notice pwcp-notice-success pwcp-clear-codes-success"><p>' . esc_html($message) . '</p></div>';
    }
}

function pwcp_donation_page() {
    ?>
    <div class="wrap">
        <div class="pwcp-donation-container">
            <h1 class="pwcp-donation-heading"><?php esc_html_e('Support Our Project', 'pay-with-code'); ?></h1>
            <p class="pwcp-donation-message"><?php esc_html_e('Your support helps us to keep going. Click the button below to donate:', 'pay-with-code'); ?></p>
            <p><a class="pwcp-donation-button" href="https://flutterwave.com/donate/pylumi0ufo1d"><?php esc_html_e('Donate Now', 'pay-with-code'); ?></a></p>
        </div>
    </div>
    <?php
}


function pwcp_settings_link($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=pwcp_settings')) . '">' . esc_html__('Settings', 'pay-with-code') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pwcp_settings_link');

