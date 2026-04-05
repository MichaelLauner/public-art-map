<?php
namespace PublicArtMap\Admin;

use PublicArtMap\Contracts\Service;

class AdminNoticeManager implements Service {
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'displayNotice' ) );
	}

	public function setNotice( string $message, string $type = 'success' ): void {
		set_transient(
			'pam_admin_notice',
			array(
				'message' => $message,
				'type'    => $type,
			),
			30
		);
	}

	public function displayNotice(): void {
		$notice = get_transient( 'pam_admin_notice' );
		if ( ! $notice ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['message'] )
		);

		delete_transient( 'pam_admin_notice' );
	}
}
