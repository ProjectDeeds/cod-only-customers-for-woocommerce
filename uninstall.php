<?php
/** Remove only this plugin's settings when it is deleted. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
delete_option( 'wccou_settings' );
delete_option( 'wccou_restricted_user_ids' );
