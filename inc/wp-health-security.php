<?php 

class WPHealthSecurity {

	function __construct() {
	    $this->loader_operations();
	}

	private function loader_operations() {
		global $aio_wp_security;

		if (empty($aio_wp_security->admin_init))
	    	$aio_wp_security->admin_init = new AIOWPSecurity_Admin_Init();
	}

	public function getStrengthMeterValues() {
	    global $aio_wp_security, $aiowps_feature_mgr;
	    $aio_wp_security->admin_init->initialize_feature_manager();

	    return array(
	        'point_max' => $aiowps_feature_mgr->get_total_achievable_points(),
	        'point_cur' => $aiowps_feature_mgr->get_total_site_points()
	    );
	}

	public function getSecurityPointsInfo(){
        global $aiowps_feature_mgr;
        $items = array(
            'blacklist-manager-ip-user-agent-blacklisting' => array(
                'desc' => __('Enable IP or User Agent Blacklisting', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_BLACKLIST_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'bf-rename-login-page' => array(
                'desc' => __('Enable Rename Login Page Feature', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_BRUTE_FORCE_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'firewall-enable-brute-force-attack-prevention' => array(
                'desc' => __('Enable Brute Force Attack Prevention', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_BRUTE_FORCE_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'user-login-captcha' => array(
                'desc' => __('Enable Captcha On Login Page', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_BRUTE_FORCE_MENU_SLUG,
                'tab'  => 'tab3'
            ),
            'custom-login-captcha' => array(
                'desc' => __('Enable Captcha On Custom Login Form', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_BRUTE_FORCE_MENU_SLUG,
                'tab'  => 'tab3'
            ),
            'lost-password-captcha' => array(
                'desc' => __('Enable Captcha On Lost Password Page', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_BRUTE_FORCE_MENU_SLUG,
                'tab'  => 'tab3'
            ),
            'whitelist-manager-ip-login-whitelisting' => array(
                'desc' => __('Enable IP Whitelisting', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_BRUTE_FORCE_MENU_SLUG,
                'tab'  => 'tab4'
            ),
            'login-honeypot' => array(
                'desc' => __('Enable Honeypot On Login Page', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_BRUTE_FORCE_MENU_SLUG,
                'tab'  => 'tab5'
            ),
            'db-security-db-prefix' => array(
                'desc' => __('Change db prefix', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_DB_SEC_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'db-security-db-backup' => array(
                'desc' => __('Enable Automated Scheduled Backups', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_DB_SEC_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'scan-file-change-detection' => array(
                'desc' => __('Enable Automated File Change Detection Scan', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FILESCAN_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'filesystem-file-permissions' => array(
                'desc' => __('Change files permission', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FILESYSTEM_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'filesystem-file-editing' => array(
                'desc' => __('Disable Ability To Edit PHP Files', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FILESYSTEM_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'block-wp-files-access' => array(
                'desc' => __('Prevent Access to WP Default Install Files', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FILESYSTEM_MENU_SLUG,
                'tab'  => 'tab3'
            ),
            'firewall-basic-rules' => array(
                'desc' => __('Enable Basic Firewall Protection', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'firewall-pingback-rules' => array(
                'desc' => __('Enable Pingback Protection', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'firewall-block-debug-file-access' => array(
                'desc' => __('Block Access to debug.log File', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'firewall-disable-index-views' => array(
                'desc' => __('Disable Index Views', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'firewall-disable-trace-track' => array(
                'desc' => __('Disable Trace and Track', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'firewall-forbid-proxy-comments' => array(
                'desc' => __('Forbid Proxy Comment Posting', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'firewall-deny-bad-queries' => array(
                'desc' => __('Deny Bad Query Strings', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'firewall-advanced-character-string-filter' => array(
                'desc' => __('Enable Advanced Character String Filter', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'firewall-enable-5g-blacklist' => array(
                'desc' => __('Enable 5G Firewall Protection', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab3'
            ),
            'firewall-block-fake-googlebots' => array(
                'desc' => __('Block Fake Googlebots', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab4'
            ),
            'prevent-hotlinking' => array(
                'desc' => __('Prevent Image Hotlinking', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab5'
            ),
            'firewall-enable-404-blocking' => array(
                'desc' => __('Enable IP Lockout For 404 Events', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_FIREWALL_MENU_SLUG,
                'tab'  => 'tab6'
            ),
            'wp-generator-meta-tag' => array(
                'desc' => __('Remove WP Generator Meta Info', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_SETTINGS_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'comment-form-captcha' => array(
                'desc' => __('Enable Captcha On Comment Forms', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_SPAM_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'block-spambots' => array(
                'desc' => __('Block Spambots From Posting Comments', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_SPAM_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'bp-register-captcha' => array(
                'desc' => __('Enable Captcha On BuddyPress Registration Form', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_SPAM_MENU_SLUG,
                'tab'  => 'tab3'
            ),
            'user-accounts-change-admin-user' => array(
                'desc' => __('Change Admin Username', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_USER_ACCOUNTS_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'user-accounts-display-name' => array(
                'desc' => __('Modify Accounts With Identical Login Name & Display Name', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_USER_ACCOUNTS_MENU_SLUG,
                'tab'  => 'tab2'
            ),
            'user-login-login-lockdown' => array(
                'desc' => __('Enable Login Lockdown Feature', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_USER_LOGIN_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'user-login-force-logout' => array(
                'desc' => __('Enable Force WP User Logout', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_USER_LOGIN_MENU_SLUG,
                'tab'  => 'tab3'
            ),
            'manually-approve-registrations' => array(
                'desc' => __('Enable manual approval of new registrations', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_USER_REGISTRATION_MENU_SLUG,
                'tab'  => 'tab1'
            ),
            'user-registration-captcha' => array(
                'desc' => __('Enable Captcha On Registration Page', 'all-in-one-wp-security-and-firewall'),
                'page' => AIOWPSEC_USER_REGISTRATION_MENU_SLUG,
                'tab'  => 'tab2'
            )
        );
        $list = array();

        $list['completed']['title'] = 'Completed Tasks';
        $list['pending']['title'] = 'Pending Tasks';

        foreach($items as $key => $desc){
            $total_secure_item = $aiowps_feature_mgr->get_feature_item_by_id($key);
            if(!empty($total_secure_item)){
                if($total_secure_item->feature_status == 'active')
                    $list_key = 'completed';
                else
                    $list_key = 'pending';

                $point = (isset($list[$list_key]['points'])) ? $list[$list_key]['points'] : 0;
                $list[$list_key]['points'] = $point + $total_secure_item->item_points;

                $list[$list_key]['data'][] = array(
                    'id' => $total_secure_item->feature_id,
                    'title' => $total_secure_item->feature_name,
                    'desc' => $desc['desc'],
                    'points' => $total_secure_item->item_points,
                    'security_level' => $total_secure_item->security_level,
                    'link' => site_url('wp-admin/admin.php?page='.$desc['page'].'&tab='.$desc['tab']),
                    'status' => ($total_secure_item->feature_status == 'active') ? 1 : 0
                );
            }
        }

        return $list;
	}

	public function getCriticalFeaturesStatus() {
	    global $aio_wp_security, $aiowps_feature_mgr;
	    $f_mgr = $aiowps_feature_mgr;
	    $list = array();
	    $aio_wp_security->admin_init->initialize_feature_manager();

		$uname_admin = $f_mgr->get_feature_item_by_id("user-accounts-change-admin-user");
		$list[] = array(
			'name' => __('Admin Username','all-in-one-wp-security-and-firewall'),
			'status' => $uname_admin->feature_status == $f_mgr->feature_active ? 1 : 0,
			'link' => site_url('wp-admin/admin.php?page='.AIOWPSEC_USER_ACCOUNTS_MENU_SLUG)
		);

		$login_lockdown = $f_mgr->get_feature_item_by_id("user-login-login-lockdown");
		$list[] = array(
			'name' => __('Login Lockdown','all-in-one-wp-security-and-firewall'),
			'status' => $login_lockdown->feature_status == $f_mgr->feature_active ? 1 : 0,
			'link' => site_url('wp-admin/admin.php?page='.AIOWPSEC_USER_LOGIN_MENU_SLUG)
		);

		$filesystem = $f_mgr->get_feature_item_by_id("filesystem-file-permissions");
		$list[] = array(
			'name' => __('File Permission','all-in-one-wp-security-and-firewall'),
			'status' => $filesystem->feature_status == $f_mgr->feature_active ? 1 : 0,
			'link' => site_url('wp-admin/admin.php?page='.AIOWPSEC_FILESYSTEM_MENU_SLUG)
		);

		$basic_firewall = $f_mgr->get_feature_item_by_id("firewall-basic-rules");
		$list[] = array(
			'name' => __('Basic Firewall','all-in-one-wp-security-and-firewall'),
			'status' => $basic_firewall->feature_status == $f_mgr->feature_active ? 1 : 0,
			'link' => site_url('wp-admin/admin.php?page='.AIOWPSEC_FIREWALL_MENU_SLUG)
		);
	    return $list;
	}
	
}