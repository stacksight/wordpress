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

require('stacksight-php-sdk/platforms/SSWordpressClient.php');
require('stacksight-php-sdk/SSLogsTracker.php');

class WPStackSightPlugin {

    public $stacksight_client;
    private $options;

    public function __construct() {
        register_activation_hook( __FILE__, array(__CLASS__, 'install'));
        register_deactivation_hook( __FILE__, array(__CLASS__, 'uninstall'));

        if(is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_notices', array($this, 'show_errors'));
        }

        $this->options = get_option('stacksight_opt');
        if ($this->options) {
            $this->stacksight_client = new SSWordpressClient($this->options['token'], 'wordpress');
            $app = $this->stacksight_client->initApp($this->options['app_name']);
            if ($app['success']) {
                add_action('aal_insert_log', array(&$this, 'insert_log_mean'), 30);
                new SSLogsTracker($this->stacksight_client, 'wordpress');
            }
            else SSUtilities::error_log($app['message'], 'error');
        }
    }

    public function insert_log_mean($args) {
        $data = array();
        $data['key'] = $args['object_type'];
        $data['name'] = $args['action'];
        $data['token'] = $this->options['token'];
        if (!$args['object_subtype']) {
            $data['data']['description'] = $args['object_type'] .' (' . $args['object_subtype'] .' has been '. $args['action'];
        } else {

            switch ($args['object_subtype']) {
                case 'attachment':
                    $img_orig = wp_get_attachment_image_src($args['object_id'], 'full');
                    if ($img_orig) {
                        $img_orig_ex = pathinfo($img_orig[0]);
                        $file = get_attached_file($args['object_id']);
                        $file_ex = pathinfo($file);
                        $image = wp_get_image_editor($file);
                        if ( ! is_wp_error( $image ) ) {
                            $image->resize(100, 100, true);
                            $image->save($file_ex['dirname'].'/ss-thumb-'.$file_ex['basename']);
                            $ss_thumb_url = $img_orig_ex['dirname'].'/ss-thumb-'.$img_orig_ex['basename'];
                            $img = '<img src="'.$ss_thumb_url.'"/>';
                        }
                    }
                    
                    if (!$img) $data['data']['description'] = $args['object_type'] .' (' . $args['object_subtype'] . ') - '. $args['object_name'] .' has been '. $args['action'];
                    else $data['data']['description'] = $args['object_type'] .' (image) '. $img .' has been '. $args['action'];
                    break;
                default:
                    $data['data']['description'] = $args['object_type'] .' (' . $args['object_subtype'] . ') - '. $args['object_name'] .' has been '. $args['action'];
                    break;
            }
        }

        switch ($args['object_type']) {
            case 'Post':
                $data['design']['icon'] = 'fa-file-text';
                $data['design']['color'] = '#8FD5FF';
                break;
            case 'User':
               $data['design']['icon'] = 'fa-user';
               $data['design']['color'] = '#8664aa';
                break;
            case 'Comments':
               $data['design']['icon'] = 'fa-comment';
               $data['design']['color'] = '#99b5bc';
                break;
            case 'Menu':
               $data['design']['icon'] = 'fa-bars';
               $data['design']['color'] = '#fd8e00';
                break;
            case 'Taxonomy':
               $data['design']['icon'] = 'fa-pie-chart';
               $data['design']['color'] = '#de1b16';
                break;
            case 'Attachment':
               $data['design']['icon'] = 'fa-paperclip';
               $data['design']['color'] = '#92c54c';
                break;
            case 'Options':
               $data['design']['icon'] = 'fa-cog';
               $data['design']['color'] = '#fbe939';
                break;
            default:
                $data['design']['color'] = '#19617a';
                $data['design']['icon'] = 'fa-bars';
                break;
        }

        $res = $this->stacksight_client->publishEvent($data);
        if (!$res['success']) SSUtilities::error_log($res['message'], 'error');
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
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'stacksight_option_group' );   
                do_settings_sections( 'stacksight-set-admin' );
                $ss_app = get_option('stacksight');
                // echo '<pre>'.print_r(, true).'</pre>';
                // trigger_error('test', E_USER_ERROR);
                if ($ss_app) printf("<p>App ID: <strong>%s</strong></p>", $ss_app['_id']);
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

        if(!$input['app_name']) add_settings_error('app_name', 'app_name', '"App Name" can not be empty');
        if(!$input['token']) add_settings_error('token', 'token', '"App Acces Token" can not be empty');


        $any_errors = $this->any_form_errors();
        // if there are errors or name or token changed - reinit app
        if (!$any_errors) {
            if (!$this->stacksight_client) $this->stacksight_client = new SSWordpressClient($input['token'], 'wordpress');
            $app = $this->stacksight_client->initApp($input['app_name']);
            
            if ($app['success']) {
                if ($app['message'] != 'LOADED') add_settings_error('app_name', 'app_name', 'The app "'.$app['data']['name'].'" created successfully', 'updated');
            } else {
                add_settings_error('', '', $app['message']);
            }
        }

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
        update_option('stacksight', '');
    }

    public static function uninstall() {
        delete_option('stacksight');
        delete_option('stacksight_opt');
    }

}

$stacksight_client_plugin = new WPStackSightPlugin();
