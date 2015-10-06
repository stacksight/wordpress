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