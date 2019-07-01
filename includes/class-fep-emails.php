<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Fep_Emails {
	private static $instance;

	public static function init() {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	function actions_filters() {
		if ( isset( $_POST['action'] ) && 'fep_update_ajax' == $_POST['action'] ) {
			return;
		}
		if ( true != apply_filters( 'fep_enable_email_send', true ) ) {
			return;
		}
		add_action( 'fep_status_to_publish', array( $this, 'send_email' ), 99, 2 );

		if ( '1' == fep_get_option( 'notify_ann', '1' ) ) {
			add_action( 'fep_status_to_publish', array( $this, 'notify_users' ), 99, 2 );
		}
	}

	function send_email( $mgs, $prev_status ) {
        $photoManager = new \IFriend\Profile\PhotoManager(GOOGLE_CLOUD_PROJECT_ID, GOOGLE_CLOUD_BUCKET);

		if ( 'message' != $mgs->mgs_type ) {
			return;
		}
		if ( fep_get_meta( $mgs->mgs_id, '_fep_email_sent', true ) ) {
			return;
		}

		$participants = fep_get_participants( $mgs->mgs_id );
		$participants = apply_filters( 'fep_filter_send_email_participants', $participants, $mgs->mgs_id );
		if ( $participants && is_array( $participants ) ) {
			$participants = array_unique( array_filter( $participants ) );
            $subject  = get_bloginfo( 'name' ) . ': ' . __( 'New Message', 'front-end-pm' );
            
			$raw_message  = '<p style="text-align: center; font-family: sans-serif"><b>' . __( 'You have received a new message in', 'front-end-pm' );
            $raw_message .= ' ' . get_bloginfo( 'name' ) . "</b></p>";
    
            $author_name = fep_user_name( $mgs->mgs_author );
            $author_data = get_userdata($mgs->mgs_author);
            $author_image_src = null;
            if ($author_data->user_avatar_url) {
                $author_image_src = $photoManager->getAvatarUrl($author_data);
            }
            if ($author_image_src) {
                $raw_message .= '<h2 style="text-align: center; font-family: sans-serif; margin-bottom: 40px;"><img src="'.$author_image_src.'" alt="" width="64" height="64" style="position: relative; top: 20px; border-radius: 32px;"> ' . $author_name . ' ' . __('is waiting your answer', 'front-end-pm') . '</h2>';
            } else {
                $raw_message .= '<h2 style="text-align: center; font-family: sans-serif; margin-bottom: 40px;"><b>' . $author_name . '</b> ' . __('is waiting your answer', 'front-end-pm') . '</h2>';
            }
            
            $link = home_url() . '?do=answer-message&msgid=#MSG_ID#';
            //fep_query_url( 'messagebox' );
            
            $raw_message .= '<p style="font-size: 16px; text-align: center; font-family: sans-serif">' . sprintf(__('Responding quickly to the <b>%s</b> message will help increase user confidence in your profile and service.', 'front-end-pm'), $author_name) . '</p>';            
			$raw_message .= '<p style="text-align: center; font-family: sans-serif">' . __( 'Please Click the following link to view full Message.', 'front-end-pm' ) . "</p>";
			$raw_message .= '<p style="text-align: center; font-family: sans-serif"><br><br><a href="' . $link . '" style="padding: 15px; font-weight: bold; color: white; background-color: #090; font-size: 14px;text-decoration: none;border: 1px solid #060;">'.__('ANSWER MESSAGE', 'front-end-pm').'</a></p>';
            
            if ( 'html' == fep_get_option( 'email_content_type', 'plain_text' ) ) {
                $raw_message      = nl2br( $raw_message );
                $raw_message      = apply_filters('fep_html_email_decorator', $raw_message);
				$content_type = 'text/html';
			} else {
				$content_type = 'text/plain';
			}
			$attachments             = array();
			$headers                 = array();
			$headers['from']         = 'From: ' . stripslashes( fep_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) ) . ' <' . fep_get_option( 'from_email', get_bloginfo( 'admin_email' ) ) . '>';
			$headers['content_type'] = "Content-Type: $content_type";
			fep_add_email_filters();

			foreach ( $participants as $participant ) {
				if ( $participant == $mgs->mgs_author ) {
					continue;
				}

				if ( ! fep_get_user_option( 'allow_emails', 1, $participant ) ) {
					continue;
				}
				$to = fep_get_userdata( $participant, 'user_email', 'id' );
				if ( ! $to ) {
					continue;
                }
                
                // message link
                $message = preg_replace('/#MSG_ID#/i', $mgs->mgs_id, $raw_message);

				$content = apply_filters( 'fep_filter_before_email_send', compact( 'subject', 'message', 'headers', 'attachments' ), $mgs, $to );

				if ( empty( $content['subject'] ) || empty( $content['message'] ) ) {
					continue;
				}
				wp_mail( $to, $content['subject'], $content['message'], $content['headers'], $content['attachments'] );
                do_action('fep_send_sms_new_message', $participant, preg_replace('/#MSG_ID#/i', $mgs->mgs_id, $link));
            } //End foreach
			fep_remove_email_filters();
			fep_update_meta( $mgs->mgs_id, '_fep_email_sent', time() );
		}
	}

	// Mass emails when announcement is created
	function notify_users( $mgs, $prev_status ) {
		if ( 'announcement' != $mgs->mgs_type ) {
			return;
		}
		if ( fep_get_meta( $mgs->mgs_id, '_fep_email_sent', true ) ) {
			return;
		}

		$user_ids = fep_get_participants( $mgs->mgs_id );
		if ( ! $user_ids ) {
			return;
		}
		cache_users( $user_ids );

		$to          = fep_get_option( 'ann_to', get_bloginfo( 'admin_email' ) );
		$user_emails = array();
		foreach ( $user_ids as $user_id ) {
			if ( $user_id === $mgs->mgs_author ) {
				continue;
			}
			if ( fep_get_user_option( 'allow_ann', 1, $user_id ) ) {
				$user_emails[] = fep_get_userdata( $user_id, 'user_email', 'id' );
			}
		}
		$subject  = get_bloginfo( 'name' ) . ': ' . __( 'New Announcement', 'front-end-pm' );
		$message  = __( 'A new Announcement is Published in ', 'front-end-pm' ) . "\r\n";
		$message .= get_bloginfo( 'name' ) . "\r\n";
		$message .= sprintf( __( 'Title: %s', 'front-end-pm' ), $mgs->mgs_title ) . "\r\n";
		$message .= __( 'Please Click the following link to view full Announcement.', 'front-end-pm' ) . "\r\n";
		$message .= fep_query_url( 'announcements' ) . "\r\n";
		if ( 'html' == fep_get_option( 'email_content_type', 'plain_text' ) ) {
			$message      = nl2br( $message );
			$content_type = 'text/html';
		} else {
			$content_type = 'text/plain';
		}
		$attachments             = array();
		$headers                 = array();
		$headers['from']         = 'From: ' . stripslashes( fep_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ) ) . ' <' . fep_get_option( 'from_email', get_bloginfo( 'admin_email' ) ) . '>';
		$headers['content_type'] = "Content-Type: $content_type";

		$content = apply_filters( 'fep_filter_before_announcement_email_send', compact( 'subject', 'message', 'headers', 'attachments' ), $mgs, $user_emails );

		if ( empty( $content['subject'] ) || empty( $content['message'] ) ) {
			return false;
		}

		do_action( 'fep_action_before_announcement_email_send', $content, $mgs, $user_emails );

		if ( ! apply_filters( "fep_announcement_email_send_{$mgs->mgs_id}", true ) ) {
			return false;
		}
		$chunked_bcc = array_chunk( $user_emails, 25 );
		fep_add_email_filters( 'announcement' );
		foreach ( $chunked_bcc as $bcc_chunk ) {
			if ( ! $bcc_chunk ) {
				continue;
			}
			$content['headers']['Bcc'] = 'Bcc: ' . implode( ',', $bcc_chunk );

			wp_mail( $to, $content['subject'], $content['message'], $content['headers'], $content['attachments'] );
		}
		fep_remove_email_filters( 'announcement' );
		fep_update_meta( $mgs->mgs_id, '_fep_email_sent', time() );
	}
} //END CLASS

add_action( 'wp_loaded', array( Fep_Emails::init(), 'actions_filters' ) );

