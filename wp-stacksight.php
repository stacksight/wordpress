<?php
/**
 * Plugin Name: Wordpress integration for stacksight
 * Plugin URI: http://mean.io
 * Description: Wordpress integration for stacksight
 * Version: 1.1
 * Author: Linnovate Technologies LTD
 * Author URI: http://mean.io
 * License: GPL
 */

defined('ABSPATH') or die("No script kiddies please!");

require_once('stacksight-php-sdk/SSUtilities.php');
require_once('stacksight-php-sdk/SSClientBase.php');
require_once('stacksight-php-sdk/SSHttpRequest.php');
require_once('stacksight-php-sdk/platforms/SSWordpressClient.php');

class WPStackSightPlugin {

    public $ss_client;
    private $options;

    public function __construct() {
        register_activation_hook( __FILE__, array(__CLASS__, 'install'));
        register_deactivation_hook( __FILE__, array(__CLASS__, 'uninstall'));

        if(is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_notices', array($this, 'show_errors'));
            wp_enqueue_style('ss-admin', plugins_url('assets/css/ss-admin.css', __FILE__ ));
        }

        if (defined('STACKSIGHT_APP_ID') && defined('STACKSIGHT_TOKEN') && defined('STACKSIGHT_BOOTSTRAPED')) {
            $this->ss_client = new SSWordpressClient(STACKSIGHT_TOKEN, 'wordpress');
            $this->ss_client->initApp(STACKSIGHT_APP_ID);
            add_action('aal_insert_log', array(&$this, 'insert_log_mean'), 30);
        }
    }

    public function insert_log_mean($args) {
        $event = array();
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $event['user'] = array(
                'name' => $user->user_login
            );
        }
        switch ($args['object_type']) {
            case 'Attachment':
                $mime = get_post_mime_type($args['object_id']);
                $file_mime_ex = explode('/', $mime);
                if (isset($file_mime_ex[0])) $event['subtype'] = $file_mime_ex[0];
                if ($args['action'] != 'deleted') $event['url'] = wp_get_attachment_url($args['object_id']);

                $event = array(
                    'action' => $args['action'],
                    'type' => 'file',
                    'name' => $args['object_name'],
                    'id' => $args['object_id'],
                    'data' => array(
                        'file_name' => $args['object_name'],
                        'type' => $mime,
                        'size' => filesize(get_attached_file($args['object_id'])),
                        'url' => isset($event['url']) ? $event['url'] : '',
                    )
                ) + $event;

                break;

            case 'Post':
                if ($args['action'] != 'deleted') $event['url'] = get_permalink($args['object_id']);

                $event = array(
                    'action' => $args['action'],
                    'type' => 'content',
                    'subtype' => $args['object_subtype'],
                    'name' => $args['object_name'],
                    'id' => $args['object_id']
                ) + $event;

                break;

            case 'User':
                $event = array(
                    'action' => $args['action'],
                    'type' => 'user',
                    'name' => $args['object_name'],
                    'id' => $args['object_id']
                ) + $event;

                break;

            case 'Comments':
                

                break;

            default:
                break;
        }

        $res = $this->ss_client->publishEvent($event);
        if (!$res['success']) SSUtilities::error_log($res['message'], 'error');
        SSUtilities::error_log($args, 'aryo_hook');

        /*
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

        $res = $this->ss_client->publishEvent($data);
        */
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
        <div class="ss-wrap">
            <h2>App setting for StackSight</h2>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'stacksight_option_group' );   
                do_settings_sections( 'stacksight-set-admin' );
                // show code instructions block
                $app_settings = get_option('stacksight_opt');
                $this->showInstructions($app_settings);
                // $link = get_permalink(1078);
                // echo '<pre>'.print_r($link, true).'</pre>';
                // trigger_error('test', E_USER_ERROR);
            ?>
            <?php submit_button(); ?>
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
            '_id', 
            'App ID', 
            array( $this, 'app_id_callback' ), 
            'stacksight-set-admin', 
            'setting_section_stacksight'
        );    
        add_settings_field(
            'token', 
            'Access Token', 
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

        if(!$input['_id']) add_settings_error('_id', '_id', '"App ID" can not be empty');
        if(!$input['token']) add_settings_error('token', 'token', '"App Acces Token" can not be empty');

        $any_errors = $this->any_form_errors();

        $new_input['_id'] = $input['_id'];
        $new_input['token'] = $input['token'];
        return $new_input;
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function app_id_callback() {
        printf(
            '<input type="text" id="_id" name="stacksight_opt[_id]" value="%s" size="50" />',
            isset( $this->options['_id'] ) ? esc_attr( $this->options['_id']) : ''
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
    }

    public static function uninstall() {
        delete_option('stacksight_opt');
    }

    public function getRelativeRootPath() {
        $plg_dir = plugin_dir_path( __FILE__ );
        if (strpos($plg_dir, ABSPATH) === FALSE) return;

        return substr($plg_dir, strlen(ABSPATH));
    }

    public function getDiagnostic($app) {
        $list = array();

        if (defined('STACKSIGHT_TOKEN') && STACKSIGHT_TOKEN != $app['token']) {
            $list[] = __('-- Tokens do not match', 'stacksight').'<br>';
        }
        if (defined('STACKSIGHT_APP_ID') && STACKSIGHT_APP_ID != $app['_id']) {
            $list[] = __('-- App Ids do not match', 'stacksight');
        }
        if (!defined('STACKSIGHT_BOOTSTRAPED') || $list) {
            $list[] = __('wp-config.php is not configured as specified above', 'stacksight').'<br>';
        }

        return array_reverse($list);
    }

    public function showInstructions($app) {
        $diagnostic = $this->getDiagnostic($app);
        ?>
<?php if ($app && $app['_id'] && $app['token'] && $diagnostic): ?>
    <div class="ss-config-block">
    <p><?php echo __("Copy a configuration code (start-end block) and modify your wp-config.php file like shown below") ?>:</p>
<pre class="code-ss-inlcude">
<span class="code-comments">// StackSight start config</span>
<span class="code-red">define</span>(<span class="code-yellow">'STACKSIGHT_APP_ID'</span>, <span class="code-yellow">'<?php echo $app['_id'] ?>'</span>);
<span class="code-red">define</span>(<span class="code-yellow">'STACKSIGHT_TOKEN'</span>, <span class="code-yellow">'<?php echo $app['token'] ?>'</span>);
<span class="code-red">require_once</span>(<span class="code-blue">ABSPATH</span> . <span class="code-yellow">'/<?php echo $this->getRelativeRootPath(); ?>'</span> . <span class="code-yellow">'stacksight-php-sdk/bootstrap-wp.php'</span>);
<span class="code-comments">// StackSight end config</span>

<span class="code-comments">// insert previous code block before this line (do not copy the following lines)</span>
<span class="code-comments">/** Sets up WordPress vars and included files. */</span>
<span class="code-red">require_once</span>(<span class="code-blue">ABSPATH</span> . <span class="code-yellow">'wp-settings.php'</span>);
</pre>
    </div>
<?php endif ?>

<div class="ss-diagnostic-block">
    <h3><?php echo __('wp-config.php status', 'stacksight') ?></h3>
    <ul class="ss-config-diagnostic">
        <?php if ($diagnostic): ?>
            <?php foreach ($diagnostic as $d_item): ?>
                <li><?php echo $d_item ?></li>
            <?php endforeach ?>
        <?php else: ?>
            <h4 class="ss-ok">OK</h4>
        <?php endif ?>
    </ul>
</div>

        <?php
    }

}

$ss_client_plugin = new WPStackSightPlugin();
