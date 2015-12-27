<?php
/**
 * Plugin Name: Stacksight
 * Plugin URI: http://mean.io
 * Description: Stacksight wordpress support (featuring events, error logs and updates)
 * Version: 1.7.0
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
    private $health;
    private $dep_plugins = array(
        'aryo-activity-log/aryo-activity-log.php' => array(
            'name' => 'Activity Log',
            'link' => 'https://wordpress.org/plugins/aryo-activity-log'
        )
    );

    public function __construct() {
        register_activation_hook( __FILE__, array(__CLASS__, 'install'));
        register_deactivation_hook( __FILE__, array(__CLASS__, 'uninstall'));

        if(is_admin()) {
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_notices', array($this, 'show_errors'));
            wp_enqueue_style('ss-admin', plugins_url('assets/css/ss-admin.css', __FILE__ ));
            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'stacksight_plugin_action_links'));
        }

        $this->_setUpMultidomainsConfig(array('STACKSIGHT_APP_ID', 'STACKSIGHT_TOKEN', 'STACKSIGHT_GROUP'));

        if (defined('STACKSIGHT_TOKEN') && defined('STACKSIGHT_BOOTSTRAPED')) {
            $app_id = (defined('STACKSIGHT_APP_ID')) ?  STACKSIGHT_APP_ID : false;
            $group = (defined('STACKSIGHT_GROUP')) ?  STACKSIGHT_GROUP : false;
            $this->ss_client = new SSWordpressClient(STACKSIGHT_TOKEN, SSClientBase::PLATFORM_WORDPRESS, $app_id, $group);
            add_filter('cron_schedules', array($this, 'cron_custom_interval'));
            add_action('aal_insert_log', array(&$this, 'insert_log_mean'), 30);
            add_action('stacksight_main_action', array($this, 'cron_do_main_job'));
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

    public function cron_do_main_job() {
        SSUtilities::error_log('cron_do_main_job has been run', 'cron_log');
        // updates
        $updates = array(
            'data' => $this->get_update_info()
        );
        $this->ss_client->sendUpdates($updates);

        // health, include health security class if All in One Security plugin exists
        $all_in_one_dir = WP_PLUGIN_DIR.'/all-in-one-wp-security-and-firewall';
        if (is_file($all_in_one_dir.'/wp-security-core.php')) {
            require_once($all_in_one_dir.'/wp-security-core.php');
            require_once($all_in_one_dir.'/admin/wp-security-admin-init.php');
            require_once('inc/wp-health-security.php');
            // echo '<pre>'.print_r($GLOBALS['aio_wp_security'], true).'</pre>';
            $this->health = new stdClass;
            $this->health->security = new WPHealthSecurity();
            $health = array();
            $health['data'][] = $this->getSecurityData();
        }

        $seo_dir = WP_PLUGIN_DIR.'/wordpress-seo';
        if (is_file($seo_dir.'/wp-seo-main.php')) {
            require_once('inc/wp-health-seo.php');

            if(!$this->health)
                $this->health = new stdClass;

            $this->health->seo = new WPHealthSeo();
            if(!isset($health))
                $health = array();

            if($seo_data = $this->getSeoData())
                $health['data'][] = $seo_data;
        }

        $backups_dir = WP_PLUGIN_DIR.'/updraftplus';
        if (is_file($backups_dir.'/updraftplus.php')) {
            require_once('inc/wp-health-backups.php');
            require_once($backups_dir.'/restorer.php');
            require_once($backups_dir.'/options.php');

            if(!$this->health)
                $this->health = new stdClass;

            $this->health->backups = new WPHealthBackups();
            if(!isset($health))
                $health = array();

            if($backups_data = $this->getBackupsData())
                $health['data'][] = $backups_data;
        }
        if(isset($health['data']) && !empty($health['data'])){
            $this->ss_client->sendHealth($health, true);
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

        $res = $this->ss_client->publishEvent($event);
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

        if($object_themes && is_array($object_themes)){
            foreach($object_themes as $theme_name => $theme){
                $themes[] = array(
                    'type' => SSWordpressClient::TYPE_THEME,
                    'name' => $theme->get('TextDomain'),
                    'version' => $theme->get('Version'),
                    'label' => $theme->get('Name'),
                    'description' => $theme->get('Description'),
                    'active' => ($theme->get('Status') == 'publish') ? true : false,
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
        $this->cron_do_main_job();
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

                // trigger_error('test', E_USER_ERROR);
                // echo '<pre>'.print_r($GLOBALS['aio_wp_security'], true).'</pre>';
//                 echo '<pre>'.print_r($app_settings, true).'</pre>';
            ?>
            <?php submit_button(); ?>
            </form>
        </div>
        <?php
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
                'link' => $uitem->update->url,
                'release_link' => 'https://themes.trac.wordpress.org/log/'.$theme,
                'download_link' => $uitem->update->package,
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
        add_settings_section(
            'setting_section_stacksight', // ID
            null, // Title
            null, // Callback
            'stacksight-set-admin' // Page
        );

        if(defined('STACKSIGHT_APP_ID')){
            add_settings_field(
                'appId',
                'Application ID',
                array( $this, 'appId_callback' ),
                'stacksight-set-admin',
                'setting_section_stacksight'
            );
        }

        add_settings_field(
            'token',
            'Access Token',
            array( $this, 'token_callback' ),
            'stacksight-set-admin',
            'setting_section_stacksight'
        );

        add_settings_field(
            'group',
            'Application group',
            array( $this, 'group_callback' ),
            'stacksight-set-admin',
            'setting_section_stacksight'
        );

        add_settings_field(
            'cron_updates_interval', 
            'Cron updates interval', 
            array( $this, 'cron_updates_interval_callback' ), 
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

        $any_errors = $this->any_form_errors();

        $new_input['cron_updates_interval'] = $input['cron_updates_interval'];
        // schedule the updates action
        wp_clear_scheduled_hook('stacksight_main_action');
        wp_schedule_event(time(), 'updates_interval', 'stacksight_main_action');

        return $new_input;
    }

    public function appId_callback() {
        if(defined('STACKSIGHT_TOKEN')){
            printf(
                '<span>'.STACKSIGHT_APP_ID.'</span>'
            );
        }
    }

    public function token_callback() {
        if(!defined('STACKSIGHT_TOKEN')){
            printf(
                '<span class="pre-code-red"> Not set </span>'
            );
        } else {
            printf(
                '<span>'.STACKSIGHT_TOKEN.'</span>'
            );
        }
    }

    public function group_callback(){
        if(!defined('STACKSIGHT_GROUP')){
            printf(
                '<span class=""> Not set </span>'
            );
        } else {
            printf(
                '<span>'.STACKSIGHT_GROUP.'</span>'
            );
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
        $plg_dir = plugin_dir_path( __FILE__ );
        if (strpos($plg_dir, ABSPATH) === FALSE) return;

        return substr($plg_dir, strlen(ABSPATH));
    }

    public function getDiagnostic($app) {
        $list = array();
        $show_code = false;

        if (!defined('STACKSIGHT_TOKEN')) {
            $list[] = __('-- Tokens do not match', 'stacksight').'<br>';
            $show_code = true;
        }
    
        if (!defined('STACKSIGHT_BOOTSTRAPED') || $list) {
            $list[] = __('wp-config.php is not configured as specified above', 'stacksight').'<br>';
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

public function showInstructions($app) {
    $diagnostic = $this->getDiagnostic($app);
    $app_token = defined('STACKSIGHT_TOKEN') ? STACKSIGHT_TOKEN : 'YOUR_STACKSIGHT_TOKEN';
?>
<div class="ss-diagnostic-block">
    <h3><?php echo __('Configuration status', 'stacksight') ?></h3>
    <ul class="ss-config-diagnostic">
        <?php if ($diagnostic['list']): ?>
            <?php foreach ($diagnostic['list'] as $d_item): ?>
                <li><?php echo $d_item ?></li>
            <?php endforeach ?>
        <?php else: ?>
            <h4 class="ss-ok">OK</h4>
        <?php endif ?>
    </ul>
</div>
<?php if (!defined('STACKSIGHT_TOKEN') && $diagnostic['show_code']): ?>
<div class="ss-config-block">
    <p><?php echo __("Insert that code (start - end) at the top of your wp-config.php after a line <strong>".htmlspecialchars('<?php')." </strong>") ?></p>
    <pre class="">
        <span class="code-comments">// StackSight start config</span>
        $ss_inc<span class="code-red"> = </span><span class="code-blue">dirname(__FILE__)</span><span class="code-red"> . </span><span class="code-yellow">'/<?php echo $this->getRelativeRootPath(); ?>stacksight-php-sdk/bootstrap-wp.php'</span>;
        <span class="code-red">if</span>(<span class="code-blue">is_file</span>($ss_inc)) {
            <span class="code-red">define</span>(<span class="code-yellow">'STACKSIGHT_TOKEN'</span>, '<span class="pre-code-red"><?php echo $app_token?></span>');
            <span class="code-red">require_once</span>($ss_inc);
        } <span class="code-comments">// StackSight end config</span>
    </pre>
</div>
<?php endif;
    }

}
$ss_client_plugin = new WPStackSightPlugin();
