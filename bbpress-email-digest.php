<?php
/**
 * Plugin Name: bbPress Email Digest
 * Plugin URI:  https://github.com/stompweb/bbpress-email-digests
 * Description: Send hourly email digests for subscriptions to bbPress users.
 * Author:      Steven Jones
 * Author URI:  https://stomptheweb.co.uk
 * Version:     0.1
 */

/*
 * Require Backdrop for async tasks
 */
require_once dirname( __FILE__ ) . '/hm-backdrop/hm-backdrop.php';

/*
 * Capture information when a new topic is added and fire off a background task to save this data to all forum subscribers
 */
function bed_topic_notifications( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0 ) {

	if ( ! bbp_is_subscriptions_active() ) {
		return false;
	}

	if ( ! bbp_is_topic_published( $topic_id ) ) {
		return false;
	}

	$user_ids = bbp_get_forum_subscribers( $forum_id, true );

	if ( $user_ids ) {

		$args['topic_author'] = $topic_author;
		$args['user_ids'] = $user_ids;
		$args['topic_id'] = $topic_id;

		// Queue this up so it doesn't block the user.
		$task = new \HM\Backdrop\Task( 'bed_store_topic_notifications', $args );
		$task->schedule();

	}

}
add_action( 'bbp_new_topic', 'bed_topic_notifications', 11, 4 );

/*
 * Save the topic notification for each user
 */
function bed_store_topic_notifications( $args ) {

	$topic_author = $args['topic_author'];
	$user_ids = $args['user_ids'];
	$topic_id = $args['topic_id'];

	foreach ( (array) $user_ids as $user_id ) {

		// Don't send notifications to the person who made the post
		if ( ! empty( $topic_author ) && (int) $user_id === (int) $topic_author ) {
			continue;
		}

		$topic_notifications = get_user_meta( $user_id, 'bbpress_topic_notifications', true );

		if ( ! is_array( $topic_notifications ) ) {
			$topic_notifications = array();
		}

		$topic_notifications[] = $topic_id;

		update_user_meta( $user_id, 'bbpress_topic_notifications', $topic_notifications );
		update_user_meta( $user_id, 'bbpress_notifications', 1 );

	}

}

/*
 * Capture information when a new reply is added and fire off a background task to save this data to all topic subscribers
 */
function bed_reply_notifications( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0 ) {

	if ( ! bbp_is_subscriptions_active() ) {
		return false;
	}

	// Bail if topic is not published
	if ( ! bbp_is_topic_published( $topic_id ) ) {
		return false;
	}

	// Bail if reply is not published
	if ( ! bbp_is_reply_published( $reply_id ) ) {
		return false;
	}

	$user_ids = bbp_get_topic_subscribers( $topic_id, true );

	if ( $user_ids ) {

		$args['reply_author'] = $reply_author;
		$args['user_ids'] = $user_ids;
		$args['reply_id'] = $reply_id;
		$args['topic_id'] = $topic_id;

		// Queue this up so it doesn't block the user.
		$task = new \HM\Backdrop\Task( 'bed_store_reply_notifications', $args );
		$task->schedule();

	}

}
add_action( 'bbp_new_reply', 'bed_reply_notifications', 11, 5 );

/*
 * Add a notification to all subscribers
 */
function bed_store_reply_notifications( $args ) {

	$reply_author = $args['reply_author'];
	$user_ids = $args['user_ids'];
	$reply_id = $args['reply_id'];
	$topic_id = $args['topic_id'];

	foreach ( (array) $user_ids as $user_id ) {

		// Don't send notifications to the person who made the post
		if ( ! empty( $reply_author ) && (int) $user_id === (int) $reply_author ) {
			continue;
		}

		$reply_notifications = get_user_meta( $user_id, 'bbpress_reply_notifications', true );

		if ( ! is_array( $reply_notifications ) ) {
			$reply_notifications = array();
		}

		$reply_notifications[] = array(
			'topic_id' => $topic_id,
			'reply_id' => $reply_id,
			'reply_author' => $reply_author,
		);

		update_user_meta( $user_id, 'bbpress_reply_notifications', $reply_notifications );
		update_user_meta( $user_id, 'bbpress_notifications', 1 );

	}

}

/**
 * On the cron job, send the emails
 */
function bed_handle_notifications() {

	$users = bed_get_all_notifiable_users();

	foreach ( $users as $user ) {

		$topic_notifications = get_user_meta( $user->ID, 'bbpress_topic_notifications', true );
		$reply_notifications = get_user_meta( $user->ID, 'bbpress_reply_notifications', true );

		bed_send_notification( $user, $topic_notifications, $reply_notifications );

	}

	bed_delete_notification_user_meta();

}
add_action( 'bed_send_bbpress_email_digests', 'bed_handle_notifications' );

/**
 * Digest email
 */
function bed_send_notification( $user, $topic_notifications, $reply_notifications ) {

	$user_email = stripslashes( $user->user_email );
	$email_subject = get_bloginfo( 'name' ) . ' digest';

	ob_start(); ?>

	<p>Here is a summary of the latest activity you are subscribed to in the forum:</p>

	<?php
	if ( $topic_notifications ) { ?>
		<p><b>Topics</b></p>
		<ul>
			<?php
			foreach ( $topic_notifications as $topic_id ) { ?>
				<li>
					<a href="<?php echo esc_url( get_permalink( $topic_id ) ); ?>">
						<?php echo get_the_title( $topic_id ); ?>
					</a>
				</li>
			<?php }
			?>
		</ul>
	<?php
	}

	if ( $reply_notifications ) { ?>
		<p><b>Replies</b></p>
		<ul>
			<?php
			foreach ( $reply_notifications as $notification ) {
				$topic_id = $notification['topic_id'];
				$reply_id = $notification['reply_id'];
				$reply_author = $notification['reply_author'];
				$author = get_userdata( $reply_author ); ?>
				<li>
					<?php echo esc_html( $author->display_name ); ?> replied to - 
					<a href="<?php echo esc_url( bbp_reply_url( $reply_id ) ); ?>">
						<?php echo get_the_title( $topic_id ); ?>
					</a>
				</li>
			<?php }
			?>
		</ul>
	<?php } ?>

	<?php
	$message = ob_get_contents();
	ob_end_clean();

	add_filter( 'wp_mail_content_type','bed_set_content_type' );
	wp_mail( $user_email, $email_subject, $message );
	remove_filter( 'wp_mail_content_type','bed_set_content_type' );

}

/**
 * Helper function to get all forum users that have notifications
 */
function bed_get_all_notifiable_users() {

	$args = array(
		'meta_query'    => array(
			array(
				'key'     => 'bbpress_notifications',
				'value'   => '1',
			),
		),
	);

	$users = new WP_User_Query( $args );

	return $users->results;

}

/**
 * Helper function to delete all user meta after all users are notified
 */
function bed_delete_notification_user_meta() {

	global $wpdb;

	$wpdb->query(
		'DELETE FROM ' . $wpdb->prefix . "usermeta
		WHERE meta_key='bbpress_reply_notifications'
		OR meta_key='bbpress_topic_notifications'
		OR meta_key='bbpress_notifications'"
	);

}

/**
 * Function to have HTML emails
 */
function bed_set_content_type() {
	return 'text/html';
}

/**
 * Setup the cron job for emails
 */
function bed_send_email_digests_job() {
	if ( ! wp_next_scheduled( 'bed_send_bbpress_email_digests' ) ) {
		wp_schedule_event( time(), 'hourly', 'bed_send_bbpress_email_digests' );
	}
}
add_action( 'init', 'bed_send_email_digests_job' );

/**
 * Remove default bbPress notification emails
 */
function bed_remove_default_bbpress_emails() {
	remove_action( 'bbp_new_reply', 'bbp_notify_topic_subscribers', 11, 5 );
	remove_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11, 4 );
}
add_action( 'plugins_loaded', 'bed_remove_default_bbpress_emails' );
