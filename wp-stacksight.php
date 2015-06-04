<?php
/**
 * Plugin Name: Wordpress integration for stacksight
 * Plugin URI: http://mean.io
 * Description: Wordpress integration for stacksight
 * Version: 1.0
 * Author: Linnovate Technologies LTD
 * Author URI: http://mean.io
 * License: GPL
 */

defined('ABSPATH') or die("No script kiddies please!");

require('stacksight-php-sdk/class-stacksight-base.php');
require('stacksight-php-sdk/interface-stacksight.php');

function b_error_log($message, $level = 'info') {
    if (!$message) return;

    $log_file = __DIR__.'/'.$level.'.log';
    // удалить лог если он превышает $logfile_limit
    $logfile_limit = 1024000; // размер лог файла в килобайтах (102400 = 100 мб)
    if (file_exists($log_file) && filesize($log_file) / 1024 > $logfile_limit) unlink($log_file);
    
    // $date = new Datetime(null, new DateTimeZone('Europe/Minsk'));
    $date = new Datetime();
    $date_format = $date->format('d.m.Y H:i:s');
    error_log($date_format .' '. $message."\n", 3, $log_file);
}

class WPStackSight extends StackSightBase implements iStacksight {

    public function initApp($name, $token) {
        if (!$name || !$token) return array('success' => false, 'message' => 'Empty name or token');

        $result = array();

        $response = wp_remote_post(WPStackSight::INIT_APP_ENDPOINT_01, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 0,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'authorization' => $token
            ),
            'body' => array(
                'name' => urlencode($name)
            )
        ));

        if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           $result['message'] = "initApp: something went wrong: $error_message";
           $result['success'] = false;
        } else {
            if ($response['response']['code'] == 200) {
                $result['data'] = json_decode($response['body'], true);
                $result['message'] = 'OK';
                $result['success'] = true;
            } else {
                $result['message'] = "initApp: something went wrong: ".$response['body'];
                $result['success'] = false;
            }
        }

        return $result;
    }

    public function publishEvent($data) {
        $result = array();

        $response = wp_remote_post(WPStackSight::EVENTS_ENDPOINT_01, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 0,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data)
        ));

        if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           $result['message'] = "publishEvent: something went wrong: $error_message";
           $result['success'] = false;
        } else {
            if ($response['response']['code'] == 200) {
                $result['message'] = 'OK';
                $result['success'] = true;
            } else {
                $result['message'] = "publishEvent: something went wrong: ".$response['body'];
                $result['success'] = false;
            }
        }

        return $result;
    }

}

class WPStackSightPlugin {

    public $wp_stack_sight;
    private $options;

    public function __construct() {
        $this->wp_stack_sight = new WPStackSight();

        register_activation_hook( __FILE__, array(__CLASS__, 'install'));
        register_deactivation_hook( __FILE__, array(__CLASS__, 'uninstall'));

        if(is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_notices', array($this, 'show_errors'));
        }

        add_action('aal_insert_log', array(&$this, 'insert_log_mean'), 30);
    }

    public function insert_log_mean($args) {
        b_error_log('insert_log_mean: '.print_r($args, true));

        $app = get_option('stack_sight_app');
        $this->options = get_option('stacksight_opt');
        $data = array();
        $mct = explode(" ", microtime());
        if (!$app) return;

        $data['index'] = 'events';
        $data['type'] = 'events';
        $data['key'] = $args['object_type'];
        $data['name'] = $args['action'];
        $data['token'] = $this->options['token'];
        $data['created'] = date("Y-m-d\TH:i:s",$mct[1]).substr((string)$mct[0],1,4).'Z';
        $data['appId'] = $app['_id'];
        if (!$args['object_subtype']) {
            $data['data']['description'] = $args['object_type'] .' (' . $args['object_subtype'] .' has been '. $args['action'];
        } else {
            $data['data']['description'] = $args['object_type'] .' (' . $args['object_subtype'] . ') - '. $args['object_name'] .' has been '. $args['action'];
        }

        switch ($args['object_type']) {
            case 'Post':
                $data['design']['icon'] = 'fa-file-text';
                $data['design']['color'] = '#8FD5FF';
                break;
            
            default:
                $data['design']['color'] = '#8FD5FF';
                $data['design']['icon'] = 'fa-bell';
                break;
        }

        $res = $this->wp_stack_sight->publishEvent($data);
    }

    /**
     * Add options page
     */
    public function add_plugin_page() {
        add_menu_page(
            'StackSight Integration', 
            'StackSight', 
            'manage_options', 
            'stacksight', 
            array($this, 'create_admin_page'), 
            '', 
            '80.2'
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h2>App setting for StackSight</h2>
            <!--<pre><?php //print_r(get_option('stack_sight_app')); ?></pre>-->
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'stacksight_option_group' );   
                do_settings_sections( 'stacksight-set-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init() {
        register_setting(
            'stacksight_option_group', // Option group
            'stacksight_opt', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );
        add_settings_section(
            'setting_section_stacksight', // ID
            null, // Title
            null, // Callback
            'stacksight-set-admin' // Page
        );  
        add_settings_field(
            'app_name', 
            'App Name', 
            array( $this, 'app_name_callback' ), 
            'stacksight-set-admin', 
            'setting_section_stacksight'
        );    
        add_settings_field(
            'token', 
            'App Access Token', 
            array( $this, 'token_callback' ), 
            'stacksight-set-admin', 
            'setting_section_stacksight'
        );

        $this->options = get_option('stacksight_opt');
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input) {
        $new_input = array();
        $app = get_option('stack_sight_app');
        $this->options = get_option('stacksight_opt');


        if(!$input['app_name']) add_settings_error('app_name', 'app_name', '"App Name" can not be empty');
        if(!$input['token']) add_settings_error('token', 'token', '"App Acces Token" can not be empty');


        $any_errors = $this->any_form_errors();
        // if there are errors or name or token changed - reinit app
        if (!$any_errors && (!$app || $this->options['app_name'] != $input['app_name'] || $this->options['token'] != $input['token'])) {
            $res = $this->wp_stack_sight->initApp($input['app_name'], $input['token']);

            if ($res['success']) {
                update_option('stack_sight_app', $res['data']);
                add_settings_error('app_name', 'app_name', 'App "'.$res['data']['name'].'" created successfully', 'updated');
            } else {
                add_settings_error('app_name', 'app_name', $res['message']);
                add_settings_error('token', 'token', $res['message']);
            }
        }

        if ($any_errors) update_option('stack_sight_app', '');
        $new_input['app_name'] = $input['app_name'];
        $new_input['token'] = $input['token'];
        return $new_input;
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function app_name_callback() {
        printf(
            '<input type="text" id="app_name" name="stacksight_opt[app_name]" value="%s" size="50" />',
            isset( $this->options['app_name'] ) ? esc_attr( $this->options['app_name']) : ''
        );
    }

    public function token_callback() {
        printf(
            '<input type="text" id="token" name="stacksight_opt[token]" value="%s" size="50" />',
            isset( $this->options['token'] ) ? esc_attr( $this->options['token']) : ''
        );
    }

    /**
     * Displays all messages registered to 'your-settings-error-slug'
     */
    public function show_errors() {
        settings_errors();
    }

    /**
     * Узнать есть ли хоть одна реальная ошибка в стеке сообщений
     * Если есть вернуть true, в противном случае false
     * @return bool да или нет на наличие сообщения типа 'error' соответственно
     */
    public function any_form_errors() {
        $errors = get_settings_errors();
        foreach ($errors as $error) {
            if ($error['type'] == 'error') return true;
        }
        return false;
    }

    public static function install() {
        update_option('stack_sight_token', '');
        update_option('stack_sight_name', '');
        update_option('stack_sight_app', '');
    }

    public static function uninstall() {
        delete_option('stack_sight_token');
        delete_option('stack_sight_name');
        delete_option('stack_sight_app');
        delete_option('stacksight_opt');
    }

}

$wp_stack_sight_plugin = new WPStackSightPlugin();