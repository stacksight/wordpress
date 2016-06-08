<?php
/**
 * Plugin Name: Stacksight
 * Plugin URI: https://wordpress.org/plugins/stacksight/
 * Description: Stacksight wordpress support (featuring events, error logs and updates)
 * Version: 1.9.3
 * Author: Stacksight LTD
 * Author URI: http://stacksight.io
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
    private $options_slack;
    private $options_features;
    private $health;
    private $dep_plugins = array();

    public $defaultDefines = array(
        'STACKSIGHT_INCLUDE_LOGS' => false,
        'STACKSIGHT_INCLUDE_HEALTH' => true,
        'STACKSIGHT_INCLUDE_INVENTORY' => true,
        'STACKSIGHT_INCLUDE_EVENTS' => true,
        'STACKSIGHT_INCLUDE_UPDATES' => true
    );

    public function __construct() {
        register_activation_hook( __FILE__, array(__CLASS__, 'install'));
        register_deactivation_hook( __FILE__, array(__CLASS__, 'uninstall'));

        if(is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_notices', array($this, 'show_errors'));
            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'stacksight_plugin_action_links'));
        }

        $this->_setUpMultidomainsConfig(array('STACKSIGHT_APP_ID', 'STACKSIGHT_TOKEN', 'STACKSIGHT_GROUP'));

        if (defined('STACKSIGHT_TOKEN') && defined('STACKSIGHT_BOOTSTRAPED')) {
            $app_id = (defined('STACKSIGHT_APP_ID')) ?  STACKSIGHT_APP_ID : false;
            $group = (defined('STACKSIGHT_GROUP')) ?  STACKSIGHT_GROUP : false;
            $this->ss_client = new SSWordpressClient(STACKSIGHT_TOKEN, SSClientBase::PLATFORM_WORDPRESS, $app_id, $group);
            add_filter('cron_schedules', array($this, 'cron_custom_interval'));
            if (function_exists('register_nav_menus')){

            }
            if(defined('STACKSIGHT_DEPENDENCY_AAL') && STACKSIGHT_DEPENDENCY_AAL === true){
                require_once(ABSPATH .'wp-content/plugins/aryo-activity-log/aryo-activity-log.php');
                if(function_exists('aal_insert_log')) {
                    add_action('aal_insert_log', array(&$this, 'insert_log_mean'), 30);
                }
            }
            add_action('stacksight_main_action', array($this, 'cron_do_main_job'));
        }
    }

    public function showStackMessages(){
        SSUtilities::checkPermissions();
        if(isset($_SESSION['STACKSIGHT_MESSAGE']) && !empty($_SESSION['STACKSIGHT_MESSAGE']) && is_array($_SESSION['STACKSIGHT_MESSAGE'])){
            foreach($_SESSION['STACKSIGHT_MESSAGE'] as $message){
                add_settings_error('', '', $message);
            }
        }
    }

    private function _setUpMultidomainsConfig($params = array()){
        if($params && is_array($params)){
            foreach($params as $param){
                if (is_multisite()) {
                    global $blog_id;
                    $constant = 'WP_' . $blog_id . '_' . $param;
                    if (defined($constant)){
                        define($param, untrailingslashit(constant($constant)));
                    }
                }
            }
        }
    }

    public function stacksight_plugin_action_links( $links ) {
        $links[] = '<a href="'. esc_url( get_admin_url(null, 'options-general.php?page=stacksight') ) .'">'.__('Settings').'</a>';
        return $links;
    }

    public function cron_custom_interval($schedules) {
        $this->options = get_option('stacksight_opt');
        $interval = isset($this->options['cron_updates_interval']) ? (int)$this->options['cron_updates_interval'] : 86400; // default dayli

        $schedules['updates_interval'] = array(
            'interval' => $interval,
            'display' => __('Once a specified period')
        );

        return $schedules;
    }

    public function handshake(){
        $total_state = $this->getTotalState();
        $total_hash_state = md5(serialize($total_state));
        $old_hash_exist = false;
        $old_hash_state = false;
        $date_of_old_hash_state = false;
        $state_option = false;


        if($state_option = get_option('stacksight_state')){
            $tempory = unserialize($state_option);
            $old_hash_exist = true;
            $old_hash_state = $tempory['hash_of_state'];
            $date_of_old_hash_state = $tempory['date_of_set'];
        }

        // If we have changed state
        if($total_hash_state != $old_hash_state){
            $time = time();
            // Send new state
            $handshake_event = array(
                'action' => ($old_hash_exist === true) ? 'updated' : 'registred',
                'type' => 'stacksight',
                'name' => 'configuration',
                'data' => $total_state
            );

            // Write new state to DB
            $new_state_to_db = array(
                'hash_of_state' => $total_hash_state,
                'date_of_set' => $time
            );

            if($old_hash_exist === true){
                update_option('stacksight_state', serialize($new_state_to_db));
            } else{
                add_option('stacksight_state', serialize($new_state_to_db));
            }

            $this->ss_client->publishEvent($handshake_event, true);
        }
    }

    public function cron_do_main_job() {
        if(!defined('STACKSIGHT_TOKEN') || !isset($this->ss_client) || !$this->ss_client)
            return;

        SSUtilities::error_log('cron_do_main_job has been run', 'cron_log');

        $this->handshake();

        // updates
        if(defined('STACKSIGHT_INCLUDE_UPDATES') && STACKSIGHT_INCLUDE_UPDATES == true){
            $updates = array(
                'data' => $this->get_update_info()
            );
            $this->ss_client->sendUpdates($updates, true);
        }

        $health = array();

        if(defined('STACKSIGHT_INCLUDE_HEALTH') && STACKSIGHT_INCLUDE_HEALTH == true){
            // health, include health security class if All in One Security plugin exists
            $all_in_one_dir = WP_PLUGIN_DIR.'/all-in-one-wp-security-and-firewall';
            if (is_file($all_in_one_dir.'/wp-security-core.php')) {
                require_once($all_in_one_dir.'/wp-security-core.php');
                require_once($all_in_one_dir.'/admin/wp-security-admin-init.php');
                require_once('inc/wp-health-security.php');

                $active = is_plugin_active('all-in-one-wp-security-and-firewall/wp-security.php');
                if($active === true){
                    if(!$this->health)
                        $this->health = new stdClass;

                    $this->health->security = new WPHealthSecurity();
                    $health['data'][] = $this->getSecurityData();
                }
            }

            $seo_dir = WP_PLUGIN_DIR.'/wordpress-seo';
            if (is_file($seo_dir.'/wp-seo-main.php')) {
                require_once('inc/wp-health-seo.php');

                $active = is_plugin_active('wordpress-seo/wp-seo.php');
                if($active === true){
                    if(!$this->health)
                        $this->health = new stdClass;

                    $this->health->seo = new WPHealthSeo();
                    if(!isset($health))
                        $health = array();

                    if($seo_data = $this->getSeoData())
                        $health['data'][] = $seo_data;
                }
            }

            $backups_dir = WP_PLUGIN_DIR . '/updraftplus';
            if (is_file($backups_dir . '/updraftplus.php')) {
                if(!defined('UPDRAFTPLUS_DIR'))
                    define('UPDRAFTPLUS_DIR', true);
                require_once('inc/wp-health-backups.php');
                require_once($backups_dir . '/restorer.php');
                require_once($backups_dir . '/options.php');

                $active = is_plugin_active('updraftplus/updraftplus.php');
                if($active === true){
                    if (!$this->health)
                        $this->health = new stdClass;

                    $this->health->backups = new WPHealthBackups();
                    if (!isset($health))
                        $health = array();

                    if ($backups_data = $this->getBackupsData())
                        $health['data'][] = $backups_data;
                }
            }

            if(isset($health['data']) && !empty($health['data'])){
                $this->ss_client->sendHealth($health, true);
            }
        }

        if(defined('STACKSIGHT_INCLUDE_INVENTORY') && STACKSIGHT_INCLUDE_INVENTORY == true){
            $inventory = $this->getInventory();
            if(!empty($inventory)){
                $data = array(
                    'data' => $inventory
                );
                $this->ss_client->sendInventory($data, true);
            }
        }

        $this->ss_client->sendMultiCURL();
    }

    public function insert_log_mean($args) {
        if(defined('STACKSIGHT_INCLUDE_EVENTS') && STACKSIGHT_INCLUDE_EVENTS == true){
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
                    if (in_array($args['action'], array('spam', 'trash', 'delete'))) return;

                    $comment = get_comment($args['object_id']);

                    if ($args['action'] == 'pending') {
                        $action = 'added';
                        if (!isset($event['user'])) $event['user'] = array(
                            'name' => isset($comment->comment_author) ? $comment->comment_author : 'guest'
                        );
                    } else $action = $args['action'];

                    $event = array(
                            'action' => $action,
                            'type' => 'comment',
                            'name' => $comment->comment_content,
                            'id' => $args['object_id']
                        ) + $event;

                    break;

                default:
                    break;
            }

            $ready_show_debug = false;

            if(is_admin() && (defined('STACKSIGHT_DEBUG') && STACKSIGHT_DEBUG === true)){
                if(!strpos($_SERVER['REQUEST_URI'], 'page=stacksight&tab=debug_mode')){
                    define('STACKSIGHT_DEBUG_MODE',true);
                    $ready_show_debug = true;
                }
            }

            $res = $this->ss_client->publishEvent($event);

            if($ready_show_debug === true){
                if(SSUtilities::checkPermissions()){
                    if(isset($_SESSION['stacksight_debug']['events']) && !empty($_SESSION['stacksight_debug']['events'])){
                        SSUtilities::error_log(print_r($_SESSION['stacksight_debug']['events'], true), 'debug_events', true);
                    }
                }
            }
            if (!$res['success']) SSUtilities::error_log($res['message'], 'error');
        }
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

    public function getInventory(){
        $object_plugins = get_plugins();
        $object_themes = get_themes();
        $plugins = array();
        $themes = array();

        if($object_plugins && is_array($object_plugins)){
            foreach($object_plugins as $path => $plugin){
                $plugins[] = array(
                    'type' => SSWordpressClient::TYPE_PLUGIN,
                    'name' => ($plugin['TextDomain']) ? $plugin['TextDomain'] : basename($path),
                    'version' => $plugin['Version'],
                    'label' => $plugin['Name'],
                    'description' => $plugin['Description'],
                    'active' => (is_plugin_active($path)) ? true : false,
                    'requires' => array()
                );
            }
        }

        $current_theme = get_current_theme();
        if($object_themes && is_array($object_themes)){
            foreach($object_themes as $theme_name => $theme){
                $themes[] = array(
                    'type' => SSWordpressClient::TYPE_THEME,
                    'name' => $theme->get('TextDomain'),
                    'version' => $theme->get('Version'),
                    'label' => $theme->get('Name'),
                    'description' => $theme->get('Description'),
                    'active' => ($theme->get('Status') == 'publish') ? true : false,
                    'current' => ($theme_name == $current_theme) ? true: false,
                    'requires' => array()
                );
            }
        }
        return array_merge($themes, $plugins);
    }

    /**
     * Options page callback
     */
    public function create_admin_page() {
        wp_enqueue_style('ss-admin', plugins_url('assets/css/ss-admin.css', __FILE__ ));
        $active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general_settings';
        if(file_exists(ABSPATH .'wp-content/plugins/aryo-activity-log/aryo-activity-log.php')){
            if(is_plugin_active('aryo-activity-log/aryo-activity-log.php')){
                define('STACKSIGHT_ACTIVE_AAL', true);
            } else{
                define('STACKSIGHT_ACTIVE_AAL', false);
            }
        }
        require_once('texts.php');
        $this->showStackMessages();
        ?>
        <div class="ss-wrap wrap">
            <h2>App setting for StackSight</h2>
            <!-- Create a header in the default WordPress 'wrap' container -->
            <div class="wrap">
                <?php settings_errors(); ?>
                <h2 class="nav-tab-wrapper">
                    <a href="?page=stacksight&tab=general_settings" class="nav-tab <?php echo $active_tab == 'general_settings' ? 'nav-tab-active' : ''; ?>">General settings</a>
                    <a href="?page=stacksight&tab=features_settings" class="nav-tab <?php echo $active_tab == 'features_settings' ? 'nav-tab-active' : ''; ?>">Features</a>
                    <?php if(defined('STACKSIGHT_DEBUG') && STACKSIGHT_DEBUG === true):?>
                        <a href="?page=stacksight&tab=debug_mode" class="nav-tab <?php echo $active_tab == 'debug_mode' ? 'nav-tab-active' : ''; ?>">Debug</a>
                    <?php endif;?>
                </h2>
                <form method="post" action="options.php">
                    <?php
                    if( $active_tab == 'general_settings' ) {
                        settings_fields( 'stacksight_option_group' );
                        do_settings_sections( 'stacksight-set-admin' );
                        //                 show code instructions block
                        $app_settings = get_option('stacksight_opt');
                    } elseif($active_tab == 'features_settings') {
                        settings_fields( 'stacksight_option_features' );
                        do_settings_sections( 'stacksight-set-features' );
                        //                 show code instructions block
                        $app_settings = get_option('stacksight_opt_features');
                    } else{
                        if(defined('STACKSIGHT_DEBUG') && STACKSIGHT_DEBUG === true){
                            define('STACKSIGHT_DEBUG_MODE',true);
                            $_SESSION['stacksight_debug'] = array();
                            $this->cron_do_main_job();
                            $this->showDebugInfo();
                        }
                    }
                    if($active_tab != 'debug_mode')
                        submit_button();
                    ?>
                </form>

            </div><!-- /.wrap -->
        </div>
        <?php
    }

    private function showDebugInfo(){
        if(isset($_SESSION['stacksight_debug']) && !empty($_SESSION['stacksight_debug']) && is_array($_SESSION['stacksight_debug'])){
            foreach($_SESSION['stacksight_debug'] as $key => $feature):?>
                <div class="feature-block">
                    <h3 class="header">
                        <?php switch($key){
                            case 'updates':
                                echo 'Updates';
                                break;
                            case 'health':
                                echo 'Health';
                                break;
                            case 'inventory':
                                echo 'Inventory';
                                break;
                            case 'events':
                                echo 'Events';
                                break;
                            case 'logs':
                                echo 'Logs';
                                break;
                        }?>
                    </h3>
                    <hr>
                    <div class="connection-info">
                        <?php foreach($feature['data'] as $key => $feature_detail):?>
                            <div>
                            <span>
                                Sending type:
                                <span class="header">
                                    <?php switch($feature_detail['type']){
                                        case 'curl':
                                            echo 'cURL';
                                            break;
                                        case 'multicurl':
                                            echo 'Multi cURL';
                                            break;
                                        case 'sockets':
                                            echo 'Sockets';
                                            break;
                                        case 'threads':
                                            echo 'Threads';
                                            break;
                                    };?>
                                </span>
                            </span>
                            </div>
                            <div>
                                <?php if($feature_detail['type'] == 'curl' || $feature_detail['type'] == 'multicurl'):?>
                                    <?php if(isset($feature['request_info']) && !empty($feature['request_info']) && is_array($feature['request_info'])):?>
                                        <table class="debug-table" cellpadding="0" cellspacing="0">
                                            <tbody>
                                            <?php $i = 0; foreach($feature['request_info'] as $key_request => $request_value):?>
                                                <?php if(in_array($key_request, SSUtilities::getCurlInfoFields())): ++$i;?>
                                                    <tr class="<?php if(($i % 2) == 0) echo 'odd'; else echo 'even';?>">
                                                        <th scope="row"><?php echo SSUtilities::getCurlDescription($key_request);?></th>
                                                        <td>
                                                            <?php
                                                            if(is_array($request_value)){
                                                                echo implode('<br/>', $request_value);
                                                            } else{
                                                                echo $request_value;
                                                            }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endif;?>
                                            <?php endforeach;?>
                                            </tbody>
                                        </table>
                                        <div class="response">
                                            <strong>Response:</strong>
                                            <?php echo $feature['request_info']['response']?>
                                        </div>
                                    <?php else:?>
                                        <table class="debug-table" cellpadding="0" cellspacing="0">
                                            <tbody>
                                            <tr>
                                                <td>Connect information not found... :(</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    <?php endif;?>
                                <?php endif;?>
                                <?php if($feature_detail['type'] == 'sockets'):?>
                                    <?php if(isset($feature['request_info'][$key]) && !empty($feature['request_info'][$key]) && is_array($feature['request_info'][$key])):?>
                                        <table class="debug-table" cellpadding="0" cellspacing="0">
                                            <tbody>
                                            <tr class="odd">
                                                <th scope="row">
                                                    Result#<?php echo $key + 1;?>:
                                                </th>
                                                <td>
                                                    <?php if($feature['request_info'][$key]['error'] == true):?>
                                                        <strong class="pre-code-red">Error</strong>
                                                    <?php else:?>
                                                        <strong class="pre-code-green">Success</strong>
                                                    <?php endif;?>
                                                </td>
                                            </tr>
                                            <tr class="even">
                                                <th scope="row">
                                                    Details:
                                                </th>
                                                <td><?php echo$feature['request_info'][$key]['data'];?></td>
                                            </tr>
                                            <tr class="odd">
                                                <th scope="row">
                                                    Meta:
                                                </th>
                                                <td><?php print_r($feature['request_info'][$key]['meta']);?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    <?php endif;?>
                                <?php endif;?>
                            </div>
                        <?php endforeach;?>
                    </div>
                </div>
                <?php
            endforeach;
            foreach($_SESSION['stacksight_debug'] as $key => $feature_dump_data):?>
                <div class="feature-block">
                    <h3 class="header">
                        <?php switch($key){
                            case 'updates':
                                echo 'Updates';
                                break;
                            case 'health':
                                echo 'Health';
                                break;
                            case 'inventory':
                                echo 'Inventory';
                                break;
                            case 'events':
                                echo 'Events';
                                break;
                            case 'logs':
                                echo 'Logs';
                                break;
                        }?>
                    </h3>
                    <hr>
                    <div class="dump-of-data">
                        <?php if(isset($feature_dump_data['data']) && !empty($feature_dump_data['data'])):?>
                            <?php foreach($feature_dump_data['data'] as $dum_data):?>
                                <pre>
                                    <?php print_r($dum_data['data']);?>
                                </pre>
                            <?php endforeach;?>
                        <?php else:?>
                            <strong>Data not found... :(</strong>
                        <?php endif;?>

                    </div>
                </div>
                <?php
            endforeach;
        }
    }

    public function getBackupsData() {
        if (empty($this->health)) return;

        $returned = false;
        $general_backups = $this->health->backups->getBackupsData();
        $widgets = array();

        if(!empty($general_backups)){
            $widgets[] = array(
                'type' => "backup",
                'title' => "Your backups",
                'desc' => "For information, updates and documentation, please visit the AIO WP Security & Firewall Plugin Page",
                'group' => 1,
                'order' => 1,
                'data' => $general_backups
            );
            $returned = true;
        }

        $data = array(
            'category' => 'backups',
            'title' => __('Backups'),
            'desc' => __('For information, updates and documentation, please visit the UpdraftPlus Backup/Restore Plugin Page'),
            'widgets' => $widgets
        );

        if($returned){
            return $data;
        } else return false;

    }

    public function getSeoData() {
        if (empty($this->health)) return;

        $returned = false;

        $data = array(
            'category' => 'seo',
            'title' => __('SEO'),
            'desc' => __('This panel shows your SEO (according to the Yoast SEO plugin)'),
        );

        $general_seo = $this->health->seo->getSeoValues();
        if (!empty($general_seo)) {
            if(!empty($general_seo['performance'])){
                $data['widgets'][] = array(
                    'type' => 'seo_meter',
                    'title' => __('General SEO performance'),
                    'desc' => __('This is general performance information'), // Optional
                    'order' => 1,       // specifies the block sequence (the place in DOM). Optinal
                    'group' => 1,       // specifies the group where the widget will be rendered.
                    'seo_meter' => $general_seo['performance']
                );
                $returned = true;
            }

            if(!empty($general_seo['graphic'])){
                $data['widgets'][] = array(
                    'type' => 'seo_chart',
                    'title' => __('SEO graphic data'),
                    'desc' => __('This is general graphic information about posts'), // Optional
                    'order' => 2,       // specifies the block sequence (the place in DOM). Optinal
                    'group' => 1,       // specifies the group where the widget will be rendered.
                    'seo_chart' => $general_seo['graphic']
                );
                $returned = true;
            }

            if(!empty($general_seo['detail'])){
                $data['widgets'][] = array(
                    'type' => 'seo_detail',
                    'title' => __('SEO detail data'),
                    'desc' => __('This is detail information about posts'), // Optional
                    'order' => 3,       // specifies the block sequence (the place in DOM). Optinal
                    'group' => 1,       // specifies the group where the widget will be rendered.
                    'seo_detail' => $general_seo['detail']
                );
                $returned = true;
            }
        }
        if ($returned === true){
            return $data;
        } else return false;
    }

    public function getSecurityData() {
        if (empty($this->health)) return;

        $data = array(
            'category' => 'security',
            'title' => __('Security'),
            'desc' => __('This panel shows the summary how your site is secure (according to the All In One Security And Firewall plugin)'),
            'plugin_url' => site_url('wp-admin/admin.php?page='.AIOWPSEC_MAIN_MENU_SLUG)
        );

        $meter = $this->health->security->getStrengthMeterValues();
        if (!empty($meter)) {
            $data['widgets'][] = array(
                'type' => 'meter',
                'title' => __('Security Strength Meter'),
                'desc' => __('This meter shows in points the security level of your site'), // Optional
                'order' => 1,       // specifies the block sequence (the place in DOM). Optinal
                'group' => 1,       // specifies the group where the widget will be rendered.
                // lets sey for meter widget where will be 2 checklists but they should be display in once parent DOM container. Optinal
                'point_max' => $meter['point_max'], // max available points to gain
                'point_cur' => $meter['point_cur']  // current amount of the points
            );
        }

        $critical_features = $this->health->security->getCriticalFeaturesStatus();
        if (!empty($critical_features)) {
            $data['widgets'][] = array(
                'type' => 'checklist',
                'title' => __('Critical Features Status'),
                'desc' => __('Below is the current status of the critical features that you should activate on your site to achieve a minimum level of recommended security','all-in-one-wp-security-and-firewall'),
                'order' => 2,       // specifies the block sequence (the place in DOM). Optinal
                'group' => 1,       // specifies the group where the widget will be rendered.
                // lets sey for meter widget where will be 2 checklists but they should be display in once parent DOM container. Optinal
                'checklist' => $critical_features
            );
        }

        $points_data = $this->health->security->getSecurityPointsInfo();

        if(!empty($points_data)){
            $data['widgets'][] = array(
                'type' => 'pointslist',
                'title' => __('Secure points tasks'),
                'desc' => __('Secure points tasks desc','all-in-one-wp-security-and-firewall'),
                'order' => 3,       // specifies the block sequence (the place in DOM). Optinal
                'group' => 1,       // specifies the group where the widget will be rendered.
                // lets sey for meter widget where will be 2 checklists but they should be display in once parent DOM container. Optinal
                'pointslist' => $points_data
            );
        }
        return $data;
    }

    public function get_update_info() {
        require_once(ABSPATH.'wp-admin/includes/update.php');
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $upd = array();
        $plg_upd = get_plugin_updates();
        $thm_upd = get_theme_updates();
        $core_upd = get_core_updates();

        foreach ($plg_upd as $key => $uitem) {
            $upd[] = array(
                'title' => $uitem->Name,
                // 'release_ts' => $uitem['datestamp'],
                'current_version' => $uitem->Version,
                'latest_version' => $uitem->update->new_version,
                'type' => 'plugin',
                'status' => 5, // 5 means new update is available
                'description' => isset($uitem->update->upgrade_notice) ? $uitem->update->upgrade_notice : '',
                'link' => $uitem->PluginURI,
                'release_link' => $uitem->update->url . 'changelog',
                'download_link' => $uitem->update->package,
                'update_link' => site_url('wp-admin/update-core.php'),
            );
        }

        foreach ($thm_upd as $theme => $uitem) {
            $upd[] = array(
                'title' => $uitem->display('Name'),
                // 'release_ts' => $uitem['datestamp'],
                'current_version' => $uitem->display('Version'),
                'latest_version' => $uitem->update['new_version'],
                'type' => 'theme',
                'status' => 5, // 5 means new update is available
                'description' => isset($uitem->update['upgrade_notice']) ? $uitem->update['upgrade_notice'] : '',
                'link' => (isset($uitem->update->url)) ? $uitem->update->url : '',
                'release_link' => 'https://themes.trac.wordpress.org/log/'.$theme,
                'download_link' => (isset($uitem->update->package)) ? $uitem->update->package : '',
                'update_link' => site_url('wp-admin/update-core.php'),
            );
        }

        if ( isset($core_upd[0]->response) || 'upgrade' == $core_upd[0]->response ) {
            $cur_version = get_bloginfo('version');
            foreach ($core_upd as $uitem) {
                $upd[] = array(
                    'title' => 'WordPress Core',
                    'current_version' => $cur_version,
                    'latest_version' => $uitem->version,
                    'type' => 'core',
                    'status' => 1, // 1 means security update (recommended)
                    'link' => 'https://wordpress.org',
                    'release_link' => 'https://codex.wordpress.org/Changelog/'.$uitem->version,
                    'download_link' => $uitem->download,
                    'update_link' => site_url('wp-admin/update-core.php'),
                );
            }

        }

        return $upd;
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

        register_setting(
            'stacksight_option_slack', // Option group
            'stacksight_opt_slack', // Option name
            array( $this, 'slackSanitize' ) // Sanitize
        );

        register_setting(
            'stacksight_option_features', // Option group
            'stacksight_opt_features', // Option name
            array( $this, 'featuresSanitize' ) // Sanitize
        );


        add_settings_section(
            'setting_section_stacksight', // ID
            null, // Title
            null, // Callback
            'stacksight-set-features' // Page
        );

        add_settings_section(
            'setting_section_stacksight', // ID
            null, // Title
            null, // Callback
            'stacksight-set-slack' // Page
        );

        add_settings_section(
            'setting_section_stacksight', // ID
            null, // Title
            null, // Callback
            'stacksight-set-admin' // Page
        );

        $title = (defined('stacksight_logs_title')) ? stacksight_logs_title : 'Include Logs';
        add_settings_field(
            'include_logs',
            $title,
            array( $this, 'include_logs_callback' ),
            'stacksight-set-features',
            'setting_section_stacksight'
        );

        $title = (defined('stacksight_health_title')) ? stacksight_health_title : 'Include Health';
        add_settings_field(
            'include_health',
            $title,
            array( $this, 'include_health_callback' ),
            'stacksight-set-features',
            'setting_section_stacksight'
        );

        $title = (defined('stacksight_inventory_title')) ? stacksight_inventory_title : 'Include Inventory';
        add_settings_field(
            'include_inventory',
            $title,
            array( $this, 'include_inventory_callback' ),
            'stacksight-set-features',
            'setting_section_stacksight'
        );

        $title = (defined('stacksight_events_title')) ? stacksight_events_title : 'Include Events';
        add_settings_field(
            'include_events',
            $title,
            array( $this, 'include_events_callback' ),
            'stacksight-set-features',
            'setting_section_stacksight'
        );

        $title = (defined('stacksight_updates_title')) ? stacksight_updates_title : 'Include Updates';
        add_settings_field(
            'include_updates',
            $title,
            array( $this, 'include_updates_callback' ),
            'stacksight-set-features',
            'setting_section_stacksight'
        );

        add_settings_field(
            'slack_url',
            'Webhook incoming URL',
            array( $this, 'slack_url_callback' ),
            'stacksight-set-slack',
            'setting_section_stacksight'
        );

        add_settings_field(
            'enable_slack_notify_logs',
            'Enable slack notifications',
            array( $this, 'enable_slack_notify_logs_callback' ),
            'stacksight-set-slack',
            'setting_section_stacksight'
        );

        add_settings_field(
            'enable_slack_options',
            'Slack notify options',
            array( $this, 'enable_slack_options_callback' ),
            'stacksight-set-slack',
            'setting_section_stacksight'
        );

        if(defined('STACKSIGHT_APP_ID')){
            add_settings_field(
                '_id',
                'App ID',
                array( $this, 'app_id_callback' ),
                'stacksight-set-admin',
                'setting_section_stacksight'
            );
        }

        add_settings_field(
            'token',
            'Access Token *',
            array( $this, 'token_callback' ),
            'stacksight-set-admin',
            'setting_section_stacksight'
        );

        add_settings_field(
            'group',
            'App Group',
            array( $this, 'group_callback' ),
            'stacksight-set-admin',
            'setting_section_stacksight'
        );

        /*
        add_settings_field(
            'enable_options',
            'Enable options',
            array( $this, 'enable_options_callback' ),
            'stacksight-set-admin',
            'setting_section_stacksight'
        );
        */

        add_settings_field(
            'cron_updates_interval',
            'Cron updates interval',
            array( $this, 'cron_updates_interval_callback' ),
            'stacksight-set-admin',
            'setting_section_stacksight'
        );

        $this->options = get_option('stacksight_opt');
//        $this->options_slack = get_option('stacksight_opt_slack');
        $this->options_features = get_option('stacksight_opt_features');
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input) {
        $new_input = array();

//        if(!$input['_id']) add_settings_error('_id', '_id', '"App ID" can not be empty');
        if(!$input['token']) add_settings_error('token', 'token', '"App Acces Token" can not be empty');
//        if(!$input['group']) add_settings_error('group', 'group', '"App group" can not be empty');

        $any_errors = $this->any_form_errors();

        if(defined('STACKSIGHT_SETTINGS_IN_DB') && STACKSIGHT_SETTINGS_IN_DB === true){
            $new_input['_id'] = $input['_id'];
            $new_input['token'] = $input['token'];
            $new_input['group'] = $input['group'];
        }
        $new_input['enable_options'] = array_keys($input['enable_options']);
        $new_input['cron_updates_interval'] = $input['cron_updates_interval'];
        // schedule the updates action
        wp_clear_scheduled_hook('stacksight_main_action');
        wp_schedule_event(time(), 'updates_interval', 'stacksight_main_action');

        return $new_input;
    }

    public function featuresSanitize($input) {
        $new_input = array();
        $any_errors = $this->any_form_errors();
        $new_input['include_logs'] = (isset($input['include_logs']) && $input['include_logs'] == 'on') ? true : false;
        $new_input['include_health'] = (isset($input['include_health']) && $input['include_health'] == 'on') ? true : false;
        $new_input['include_inventory'] = (isset($input['include_inventory']) && $input['include_inventory'] == 'on') ? true : false;
        $new_input['include_events'] = (isset($input['include_events']) && $input['include_events'] == 'on') ? true : false;
        $new_input['include_updates'] = (isset($input['include_updates']) && $input['include_updates'] == 'on') ? true : false;
        // schedule the updates action
        wp_clear_scheduled_hook('stacksight_main_action');
        return $new_input;
    }

    public function slackSanitize($input) {
        $new_input = array();
        if(!$input['slack_url']) add_settings_error('slack_url', 'slack_url', '"Webhook incoming URL" can not be empty');

        $any_errors = $this->any_form_errors();

        $new_input['slack_url'] = $input['slack_url'];
        $new_input['enable_slack_notify_logs'] = (isset($input['enable_slack_notify_logs']) && $input['enable_slack_notify_logs'] == 'on') ? true : false;
        $new_input['enable_slack_options'] = (is_array($input['enable_slack_options'])) ? array_keys($input['enable_slack_options']) : array();
        // schedule the updates action
        wp_clear_scheduled_hook('stacksight_main_action');
        return $new_input;
    }

    /**
     * Get the settings option array and print one of its values
     */

    public function include_logs_callback(){
        $checked = '';
        if(defined('STACKSIGHT_INCLUDE_LOGS') && STACKSIGHT_INCLUDE_LOGS === true) {
            $checked = 'checked';
        }
        $description = '';
        if(defined('stacksight_logs_text')){
            $description = stacksight_logs_text;
        }
        printf('<div class="health_features_option"><div class="checkbox"><input type="checkbox" name="stacksight_opt_features[include_logs]" id="enable_features_logs" '.$checked.' /></div>'.$description.'</div>');
    }

    public function include_health_callback(){
        $checked = '';
        if((defined('STACKSIGHT_INCLUDE_HEALTH') && STACKSIGHT_INCLUDE_HEALTH === true) || !defined('STACKSIGHT_INCLUDE_HEALTH')){
            $checked = 'checked';
        }
        $description = '';
        if(defined('stacksight_health_text')){
            $description = stacksight_health_text;
        }
        printf('<div class="health_features_option"><div class="checkbox"><input type="checkbox" name="stacksight_opt_features[include_health]" id="enable_features_health" '.$checked.' /></div>'.$description.'</div>');
    }

    public function include_inventory_callback(){
        $checked = '';
        if((defined('STACKSIGHT_INCLUDE_INVENTORY') && STACKSIGHT_INCLUDE_INVENTORY === true) ||  !defined('STACKSIGHT_INCLUDE_INVENTORY')) {
            $checked = 'checked';
        }
        $description = '';
        if(defined('stacksight_inventory_text')){
            $description = stacksight_inventory_text;
        }
        printf('<div class="health_features_option"><div class="checkbox"><input type="checkbox" name="stacksight_opt_features[include_inventory]" id="enable_features_inventory" '.$checked.' /></div>'.$description.'</div>');
    }

    public function include_updates_callback(){
        $checked = '';
        if((defined('STACKSIGHT_INCLUDE_UPDATES') && STACKSIGHT_INCLUDE_UPDATES === true) || !defined('STACKSIGHT_INCLUDE_UPDATES')){
            $checked = 'checked';
        }
        $description = '';
        if(defined('stacksight_updates_text')){
            $description = stacksight_updates_text;
        }
        printf('<div class="health_features_option"><div class="checkbox"><input type="checkbox" name="stacksight_opt_features[include_updates]" id="enable_features_events" '.$checked.' /></div>'.$description.'</div>');
    }

    public function include_events_callback(){
        $checked = '';
        if((defined('STACKSIGHT_INCLUDE_EVENTS') && STACKSIGHT_INCLUDE_EVENTS === true) || !defined('STACKSIGHT_INCLUDE_EVENTS')){
            $checked = 'checked';
        }
        $description = '';
        if(defined('stacksight_events_text')){
            $description = stacksight_events_text;
        }

        if((defined('STACKSIGHT_DEPENDENCY_AAL') && STACKSIGHT_DEPENDENCY_AAL === true) && function_exists('aal_insert_log') && (defined('STACKSIGHT_ACTIVE_AAL') && STACKSIGHT_ACTIVE_AAL === true)){
            printf('<div class="health_features_option"><div class="checkbox"><input type="checkbox" name="stacksight_opt_features[include_events]" id="enable_features_events" '.$checked.' /></div>'.$description.'</div>');
        } else{
            printf('<div class="health_features_option"><div class="checkbox"><input type="checkbox" name="stacksight_opt_features[include_events]" id="enable_features_events" disabled/></div>'.$description.'</div>');

        }
    }

    public function slack_url_callback() {
        printf(
            '<input type="text" id="slack_url" name="stacksight_opt_slack[slack_url]" value="%s" size="50" />',
            isset( $this->options_slack['slack_url'] ) ? esc_attr( $this->options_slack['slack_url']) : ''
        );
    }

    public function app_id_callback() {
        if(defined('STACKSIGHT_SETTINGS_IN_DB') && STACKSIGHT_SETTINGS_IN_DB === true){
            printf(
                '<input type="text" id="_id" name="stacksight_opt[_id]" value="%s" size="50" />',
                isset( $this->options['_id'] ) ? esc_attr( $this->options['_id']) : ''
            );
        } else{
            if(!defined('STACKSIGHT_APP_ID')){
                printf(
                    '<span class="pre-code-green"> Is calculated </span>'
                );
            } else {
                printf(
                    '<span>'.STACKSIGHT_APP_ID.'</span>'
                );
            }
        }


    }

    public function token_callback() {
        if(defined('STACKSIGHT_SETTINGS_IN_DB') && STACKSIGHT_SETTINGS_IN_DB === true){
            printf(
                '<input type="text" id="token" name="stacksight_opt[token]" value="%s" size="50" />',
                isset( $this->options['token'] ) ? esc_attr( $this->options['token']) : ''
            );
        } else{
            if(!defined('STACKSIGHT_TOKEN')){
                printf(
                    '<span class="pre-code-red"> Not set </span>'
                );
            } else {
                printf(
                    '<span>'.STACKSIGHT_TOKEN.'</span><input type="hidden" name="stacksight_opt[token]" value="'.STACKSIGHT_TOKEN.'">'
                );
            }
        }
    }

    public function group_callback() {
        if(defined('STACKSIGHT_SETTINGS_IN_DB') && STACKSIGHT_SETTINGS_IN_DB === true){
            printf(
                '<input type="text" id="group" name="stacksight_opt[group]" value="%s" size="50" />',
                isset( $this->options['group'] ) ? esc_attr( $this->options['group']) : ''
            );
        } else{
            if(!defined('STACKSIGHT_GROUP')){
                printf(
                    '<span class="pre-code-red"> Not set </span>'
                );
            } else {
                printf(
                    '<span>'.STACKSIGHT_GROUP.'</span>'
                );
            }
        }
    }

    public function enable_slack_notify_logs_callback(){
        $checked = '';
        if(isset($this->options_slack['enable_slack_notify_logs']) && $this->options_slack['enable_slack_notify_logs'] == true){
            $checked = 'checked';
        }
        printf('<div><input type="checkbox" name="stacksight_opt_slack[enable_slack_notify_logs]" id="enable_slack_notify_logs" '.$checked.' /></div>');
    }

    public function enable_slack_options_callback(){
        $options = array(
            'error' => 'Errors',
            'warn' => 'Warning',
            'info' => 'Info'
        );

        $opt_array = '';
        $checked_options = isset($this->options_slack['enable_slack_options']) ? $this->options_slack['enable_slack_options'] : array();
        foreach($options as $key => $option){
            $checked = '';
            if(in_array($key, $checked_options)){
                $checked = 'checked';
            }
            printf('<div><input type="checkbox" name="stacksight_opt_slack[enable_slack_options]['.$key.']" id="enable_slack_options" '.$checked.' /><span>%s</span></div>', $option);
        }
    }

    public function enable_options_callback(){
        $options = array(
            'logs' => 'Logs',
            'health_seo' => 'Health SEO',
            'health_backup' => 'Health backup',
            'health_security' => 'Health security',
            'inventory' => 'Inventory',
        );

        $opt_array = '';
        $checked_options = isset($this->options['enable_options']) ? $this->options['enable_options'] : array();
        foreach($options as $key => $option){
            $checked = '';
            if(in_array($key, $checked_options)){
                $checked = 'checked';
            }
            printf('<div><input type="checkbox" name="stacksight_opt[enable_options]['.$key.']" id="enable_options" '.$checked.' /><span>%s</span></div>', $option);
        }
    }

    public function cron_updates_interval_callback() {
        $arr_opt = array(
            1 => 'Every second',
            60 => 'Every minute',
            3600 => 'Every hour',
            86400 => 'Every day'
        );

        $opt_str = '';
        $interval = isset($this->options['cron_updates_interval']) ? (int)$this->options['cron_updates_interval'] : 1;

        foreach ($arr_opt as $seconds => $caption) {
            $opt_str .= SSUtilities::t('<option {selected} value="{seconds}">{caption}</option>', array(
                '{selected}' => $seconds === $interval ? 'selected' : '',
                '{seconds}' => $seconds,
                '{caption}' => $caption,
            ));
        }

        printf('<select id="cron_updates_interval" name="stacksight_opt[cron_updates_interval]">%s</select>', $opt_str);
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
        wp_clear_scheduled_hook('stacksight_main_action');
    }

    public function getRelativeRootPath() {
        $plg_dir = str_replace('\\', '/', plugin_dir_path( __FILE__ ));
        $abs_path = str_replace('\\', '/', ABSPATH);
        if (strpos($plg_dir, $abs_path) === FALSE) return;
        return substr($plg_dir, strlen($abs_path));
    }

    public function getDiagnostic($app) {
        $list = array();
        $show_code = false;

        if (!defined('STACKSIGHT_TOKEN')) {
            $list[] = __('Token doesn\'t exist', 'stacksight').'<br>';
            $show_code = true;
        }

        if ((!defined('STACKSIGHT_PHP_SDK_INCLUDE') || (defined('STACKSIGHT_PHP_SDK_INCLUDE') && STACKSIGHT_PHP_SDK_INCLUDE !== true))) {
            $list[] = __('wp-config.php is not configured as specified below', 'stacksight').'<br>';
            $show_code = true;
        }

        foreach ($this->dep_plugins as $plugin => $d_plg) {
            if (!is_plugin_active($plugin)) {
                $list[] = SSUtilities::t('Plugin <a target="_blank" href="{link}">{plugin}</a> is required, please install and activate', array(
                        '{link}' => $d_plg['link'],
                        '{plugin}' => $d_plg['name']
                    )).'<br>';
            }
        }

        return array('list' => array_reverse($list), 'show_code' => $show_code);
    }

    public function getTotalState(){
        global $wpdb;

        $plugin_info = get_plugin_data(dirname(__FILE__).'/wp-stacksight.php');
        if(function_exists('exec')){
            if (is_multisite()) {
                $wp_version = get_bloginfo('version');
                $blog_id = (int) get_current_blog_id();
                $is_old = false;

                if(is_dir(get_home_path().'wp-content/blogs.dir')){
                    $is_old = true;
                }
                
                if($is_old){
                    $basic_path = 'wp-content/blogs.dir';
                } else{
                    $basic_path = 'wp-content/uploads';
                }

                $full_size = (int) trim(str_replace('	.','', shell_exec('du -sk '.get_home_path())));
                $upload_full_size = (int) trim(str_replace('	.','', shell_exec('du -sk '.get_home_path(). $basic_path)));
                if($blog_id == 1){
                    if($is_old){
                        $blogs_size =  (int) trim(str_replace('	.','', shell_exec('du -sk '.get_home_path(). $basic_path)));
                    } else{
                        $blogs_size =  (int) trim(str_replace('	.','', shell_exec('du -sk '.get_home_path(). $basic_path.'/sites')));
                    }
                    $blog_size =  $upload_full_size - $blogs_size;
                } else{
                    if($is_old){
                        $blog_size =  (int) trim(str_replace('	.','', shell_exec('du -sk '.get_home_path(). $basic_path.'/'.$blog_id)));
                    } else{
                        $blog_size =  (int) trim(str_replace('	.','', shell_exec('du -sk '.get_home_path(). $basic_path.'/sites/'.$blog_id)));
                    }
                }
                $total_size = $full_size - $upload_full_size + $blog_size;
                $plugin_info['space_used'] = $total_size;
            } else{
                $plugin_info['space_used'] = (int) trim(str_replace('	.','', shell_exec('du -sk .')));
            }
        } else{
            $plugin_info['space_used'] = 'n/a';
        }

        $plugin_info['wpml_lang'] = false;
        $login_activity_table = $wpdb->prefix.'aiowps_login_activity';
        $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM $login_activity_table ORDER BY login_date DESC LIMIT %d", 1), ARRAY_A);
        $login_date = false;
        $table = _get_meta_table('user');

        if (!empty($data) && isset($data[0]['user_id'])) {
            $sql = "SELECT * FROM $table WHERE meta_key = 'last_login_time' AND user_id = ".$data[0]['user_id']." ORDER BY meta_value DESC LIMIT 1";
            $login_date = $data[0]['login_date'];
        } else{
            $sql = "SELECT * FROM $table WHERE meta_key = 'last_login_time' ORDER BY meta_value DESC LIMIT 1";
        }

        $meta = $wpdb->get_row($sql);
        if (isset($meta->meta_value)){
            $meta->meta_value = maybe_unserialize( $meta->meta_value );
            if(isset($meta->user_id)){
                $user_info = get_userdata($meta->user_id);
                if($login_date === false){
                    $login_time = strtotime($meta->meta_value);
                } else{
                    $login_time = strtotime($login_date);
                }
                $plugin_info['last_login'] = array(
                    'user_id' => $meta->user_id,
                    'user_login' => $user_info->user_login,
                    'user_mail' => $user_info->user_email,
                    'user_name' => $user_info->display_name,
                    'user_link' => get_edit_user_link($meta->user_id),
                    'time' => $login_time
                );
            }
        }

        $owner_mail = get_option('admin_email');
        $user_owner = get_user_by_email($owner_mail);
        if($user_owner){
            $plugin_info['owner'] = array(
                'user_id' => $user_owner->get('id'),
                'user_login' => $user_owner->get('user_login'),
                'user_mail' => $user_owner->get('user_email'),
                'user_name' => $user_owner->get('display_name'),
                'user_link' => get_edit_user_link($user_owner->get('id')),
            );
        }

        $plugin_info['public'] = get_option('blog_public');
        $plugin_info['url'] = get_home_url();

        return array(
            'app' => $plugin_info,
            'settings' => array(
                'app_id' => (defined('STACKSIGHT_APP_ID')) ? STACKSIGHT_APP_ID : false,
                'app_token' => (defined('STACKSIGHT_TOKEN')) ? STACKSIGHT_TOKEN : false,
                'app_group' => (defined('STACKSIGHT_GROUP')) ? STACKSIGHT_GROUP : false,
                'debug_mode' => (defined('STACKSIGHT_DEBUG')) ? STACKSIGHT_DEBUG : false
            ),
            'features' => array(
                'logs' => (defined('STACKSIGHT_INCLUDE_LOGS')) ? STACKSIGHT_INCLUDE_LOGS : $this->defaultDefines['STACKSIGHT_INCLUDE_LOGS'],
                'health' => (defined('STACKSIGHT_INCLUDE_HEALTH')) ? STACKSIGHT_INCLUDE_HEALTH : $this->defaultDefines['STACKSIGHT_INCLUDE_HEALTH'],
                'inventory' => (defined('STACKSIGHT_INCLUDE_INVENTORY')) ? STACKSIGHT_INCLUDE_INVENTORY : $this->defaultDefines['STACKSIGHT_INCLUDE_INVENTORY'],
                'events' => (defined('STACKSIGHT_INCLUDE_EVENTS')) ? STACKSIGHT_INCLUDE_EVENTS : $this->defaultDefines['STACKSIGHT_INCLUDE_EVENTS'],
                'updates' => (defined('STACKSIGHT_INCLUDE_UPDATES')) ? STACKSIGHT_INCLUDE_UPDATES : $this->defaultDefines['STACKSIGHT_INCLUDE_UPDATES']
            )
        );
    }

}
$ss_client_plugin = new WPStackSightPlugin();
