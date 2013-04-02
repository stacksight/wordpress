<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class HT_Admin_Ui {
	
	public function admin_init() {
		wp_enqueue_style( 'history-timeline', plugins_url( '/admin-ui/', HISTORY_TIMELINE_BASE ) . 'history-timeline.css' );
	}

	/**
	 * @param array    $args
	 * @param HT_Model $history
	 * 
	 * @deprecated
	 */
	public function viewPartialHistory( $args = array(), HT_Model $history ) {
		$rows = $history->getLastResult( $args );

		if ( ! $rows ) {
			echo '<p>No have any logs.</p>';

			return;
		}

		foreach ( $rows as $row ) {
			$user       = false;
			$userText   = 'by ';
			$objectName = $row->object_name;

			if ( ! empty( $row->user_id ) ) {
				$user = get_user_by( 'id', $row->user_id );
			}

			if ( $user ) {
				$userText .= '<a href="user-edit.php?user_id=' . $user->ID . '">' . $user->user_login . '</a>';
			}
			else {
				$userText .= 'Guest';
			}

			$userText .= ' (' . $row->histIP . ')';

			if ( $row->object_type === 'Post' ) {
				$objectName = '<a href="post.php?post=' . $row->object_id . '&action=edit">' . $row->object_name . '</a>';
			}


			?>
			<div id="history-system-item-<?php echo $row->histid; ?>" class="row">
				<div class="type"><?php echo $row->object_type; ?><?php echo ( ! empty( $row->object_subtype ) ) ? ' (' . $row->object_subtype . ')' : ''; ?></div>
				<div class="name"><?php echo $objectName; ?></div>
				<div class="action"> was <?php echo $row->action; ?></div>
				<div class="dateaction"><?php echo human_time_diff( $row->histTime, current_time( 'timestamp' ) ); ?></div>
				<div class="dateshow"><?php echo date( 'd/m/Y H:i', $row->histTime ); ?></div>
				<div class="user"><?php echo $userText; ?></div>
			</div>
		<?php
		}
	}

	public function create_admin_menu() {
		add_dashboard_page( 'History Timeline', 'History Timeline', 'edit_pages', 'history_timeline_page', array( &$this, 'history_timeline_page_func' ) );
	}

	public function history_timeline_page_func() {
		$history_table = new HT_History_List_Table();
		$history_table->prepare_items();
		
		?>
		<div class="wrap">
			<h2>History Timeline:</h2>

			<div class="aryo-history-system-types">
				Modules: <?php echo $history_table->get_all_object_types(); ?>
			</div>

			<div class="aryo-history-system-users">
				Users: <?php echo $history_table->get_all_users(); ?>
			</div>

			<hr />

			<form id="history-filter" method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
				<?php $history_table->display(); ?>
			</form>
			
		</div>

	<?php
	}
	
	public function __construct() {
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
		add_action( 'admin_menu', array( &$this, 'create_admin_menu' ) );
	}
}