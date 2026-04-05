<?php
namespace PublicArtMap\Admin;

use PublicArtMap\Contracts\Service;
use PublicArtMap\Infrastructure\PluginContext;
use PublicArtMap\Service\GeocodingService;
use WP_Query;

class SettingsPage implements Service {
	private PluginContext $context;
	private GeocodingService $geocoder;

	public function __construct( PluginContext $context, GeocodingService $geocoder ) {
		$this->context  = $context;
		$this->geocoder = $geocoder;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'addSettingsPage' ) );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		add_action( 'update_option_pam_cron_autofill_coords', array( $this, 'manageCronSchedule' ), 10, 2 );
		add_action( 'pam_cron_fill_coordinates', array( $this, 'runCronCoordinateFill' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminAssets' ) );
	}

	public function addSettingsPage(): void {
		add_options_page(
			__( 'Public Art Map Settings', $this->context->textDomain() ),
			__( 'Public Art Map', $this->context->textDomain() ),
			'manage_options',
			'public-art-map',
			array( $this, 'renderSettingsPage' )
		);
	}

	public function registerSettings(): void {
		register_setting(
			'pam_settings_group',
			'pam_site_logo',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			)
		);

		register_setting(
			'pam_settings_group',
			'pam_map_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		register_setting(
			'pam_settings_group',
			'pam_mapbox_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'pam_settings_group',
			'pam_cron_autofill_coords',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitizeBoolean' ),
				'default'           => false,
			)
		);

		register_setting(
			'pam_settings_group',
			'pam_export_enabled',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitizeBoolean' ),
				'default'           => false,
			)
		);

		register_setting(
			'pam_settings_group',
			'pam_export_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_section(
			'pam_main_settings',
			__( 'Map Display Settings', $this->context->textDomain() ),
			'__return_null',
			'public-art-map'
		);

		add_settings_field( 'pam_site_logo', __( 'Site Logo', $this->context->textDomain() ), array( $this, 'renderSiteLogoField' ), 'public-art-map', 'pam_main_settings' );
		add_settings_field( 'pam_mapbox_api_key', __( 'Mapbox API Key', $this->context->textDomain() ), array( $this, 'renderMapboxApiKeyField' ), 'public-art-map', 'pam_main_settings' );
		add_settings_field( 'pam_map_page', __( 'Map Display Page', $this->context->textDomain() ), array( $this, 'renderMapPageDropdown' ), 'public-art-map', 'pam_main_settings' );
		add_settings_field( 'pam_cron_autofill_coords', __( 'Background Coordinate Fill', $this->context->textDomain() ), array( $this, 'renderCronAutofillField' ), 'public-art-map', 'pam_main_settings' );

		add_settings_section(
			'pam_data_sharing_settings',
			__( 'Data Sharing', $this->context->textDomain() ),
			'__return_null',
			'public-art-map'
		);

		add_settings_field( 'pam_export_enabled', __( 'Enable Export Feed', $this->context->textDomain() ), array( $this, 'renderExportEnabledField' ), 'public-art-map', 'pam_data_sharing_settings' );
		add_settings_field( 'pam_export_token', __( 'Export Token', $this->context->textDomain() ), array( $this, 'renderExportTokenField' ), 'public-art-map', 'pam_data_sharing_settings' );
	}

	public function sanitizeBoolean( $value ): bool {
		return (bool) $value;
	}

	public function renderMapPageDropdown(): void {
		wp_dropdown_pages(
			array(
				'name'              => 'pam_map_page',
				'selected'          => get_option( 'pam_map_page' ),
				'show_option_none'  => __( '-- Select a page --', $this->context->textDomain() ),
				'option_none_value' => 0,
			)
		);
	}

	public function renderSettingsPage(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Public Art Map Settings', $this->context->textDomain() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'pam_settings_group' );
				do_settings_sections( 'public-art-map' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function renderMapboxApiKeyField(): void {
		$value = esc_attr( get_option( 'pam_mapbox_api_key', '' ) );
		echo '<input type="text" name="pam_mapbox_api_key" value="' . $value . '" style="width: 100%;" placeholder="pk.XXXXXXX">';
		echo '<p class="description">' . wp_kses_post( __( 'Paste your Mapbox public access token (starts with <code>pk.</code>). Map rendering and geocoding requests depend on this token.', $this->context->textDomain() ) ) . '</p>';
	}

	public function renderExportEnabledField(): void {
		$enabled    = (bool) get_option( 'pam_export_enabled', false );
		$token      = trim( (string) get_option( 'pam_export_token', '' ) );
		$export_url = rest_url( 'public-art-map/v1/export' );
		?>
		<label>
			<input type="checkbox" name="pam_export_enabled" value="1" <?php checked( $enabled ); ?>>
			<?php esc_html_e( 'Allow another site to fetch map locations, taxonomies, and image URLs from this site.', $this->context->textDomain() ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Export URL:', $this->context->textDomain() ); ?>
			<code><?php echo esc_html( $export_url ); ?></code>
		</p>
		<?php if ( $token ) : ?>
			<p class="description"><?php echo wp_kses_post( __( 'Use <code>?token=YOUR_TOKEN</code> or an <code>X-PAM-Token</code> header when requesting the feed.', $this->context->textDomain() ) ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Leave the token blank to make the feed public once enabled, or set a token to limit access.', $this->context->textDomain() ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function renderExportTokenField(): void {
		$value = esc_attr( get_option( 'pam_export_token', '' ) );
		echo '<input type="text" name="pam_export_token" value="' . $value . '" style="width: 100%;" placeholder="' . esc_attr__( 'Optional shared secret', $this->context->textDomain() ) . '">';
		echo '<p class="description">' . esc_html__( 'Optional. If set, requests must include this token. This is useful when you want a shareable feed without making it fully public.', $this->context->textDomain() ) . '</p>';
	}

	public function renderCronAutofillField(): void {
		$checked = checked( get_option( 'pam_cron_autofill_coords', false ), true, false );
		$total   = wp_count_posts( 'map_location' )->publish;

		$with_coords = new WP_Query(
			array(
				'post_type'      => 'map_location',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => 'pam_coordinates',
						'compare' => 'EXISTS',
					),
				),
				'fields'         => 'ids',
			)
		);

		$count_with_coords = $with_coords->post_count;
		$count_missing     = $total - $count_with_coords;

		printf(
			"<label><input type='checkbox' name='pam_cron_autofill_coords' value='1' %s> %s</label>",
			$checked,
			esc_html__( 'Automatically populate missing coordinates in the background using Mapbox geocoding.', $this->context->textDomain() )
		);
		echo "<p class='description'>";
		printf(
			esc_html__( 'Currently: %1$d with coordinates, %2$d remaining.', $this->context->textDomain() ),
			(int) $count_with_coords,
			(int) $count_missing
		);
		echo '</p>';

		if ( get_transient( 'pam_cron_is_running' ) ) {
			echo '<p class="description" style="color:#999;">' . esc_html__( 'Background process is currently running.', $this->context->textDomain() ) . '</p>';
		}

		$last_run = get_transient( 'pam_cron_last_run' );
		if ( $last_run ) {
			printf(
				"<p class='description'>%s <strong>%s</strong></p>",
				esc_html__( 'Last run:', $this->context->textDomain() ),
				esc_html( $last_run )
			);
		}
	}

	public function manageCronSchedule( $new_value, $old_value ): void {
		if ( $new_value && ! wp_next_scheduled( 'pam_cron_fill_coordinates' ) ) {
			$this->runCronCoordinateFill();
			wp_schedule_event( time() + 60, 'hourly', 'pam_cron_fill_coordinates' );
			do_action( 'pam_cron_fill_coordinates' );
		} elseif ( ! $new_value ) {
			wp_clear_scheduled_hook( 'pam_cron_fill_coordinates' );
		}
	}

	public function runCronCoordinateFill(): void {
		set_transient( 'pam_cron_is_running', true, 10 * MINUTE_IN_SECONDS );

		if ( ! $this->geocoder->hasMapboxToken() ) {
			delete_transient( 'pam_cron_is_running' );
			return;
		}

		$posts = $this->geocoder->getPostsMissingCoordinates( 5 );
		foreach ( $posts as $post ) {
			$this->geocoder->updatePostCoordinates( $post->ID );
		}

		$remaining = new WP_Query(
			array(
				'post_type'      => 'map_location',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'     => 'pam_coordinates',
						'compare' => 'NOT EXISTS',
					),
				),
				'fields'         => 'ids',
			)
		);

		if ( 0 === (int) $remaining->post_count ) {
			update_option( 'pam_cron_autofill_coords', 0 );
			wp_clear_scheduled_hook( 'pam_cron_fill_coordinates' );
		}

		set_transient( 'pam_cron_last_run', current_time( 'mysql' ), 12 * HOUR_IN_SECONDS );
		delete_transient( 'pam_cron_is_running' );
	}

	public function renderSiteLogoField(): void {
		$logo_url      = esc_url( get_option( 'pam_site_logo', '' ) );
		$media_strings = wp_json_encode(
			array(
				'selectLogo' => __( 'Select or Upload Logo', $this->context->textDomain() ),
				'useLogo'    => __( 'Use this logo', $this->context->textDomain() ),
			)
		);
		?>
		<div style="max-width: 300px;">
			<img id="pam-site-logo-preview" src="<?php echo $logo_url; ?>" alt="<?php echo esc_attr__( 'Site logo preview', $this->context->textDomain() ); ?>" style="max-width:100%;<?php echo $logo_url ? '' : 'display:none;'; ?>" />
		</div>
		<input type="hidden" name="pam_site_logo" id="pam-site-logo" value="<?php echo $logo_url; ?>" />
		<p>
			<button type="button" class="button" id="pam-upload-logo"><?php esc_html_e( 'Select Logo', $this->context->textDomain() ); ?></button>
			<button type="button" class="button" id="pam-remove-logo"><?php esc_html_e( 'Remove', $this->context->textDomain() ); ?></button>
		</p>
		<script>
			jQuery(document).ready(function($) {
				const pamSiteLogoL10n = <?php echo $media_strings; ?>;
				const frame = wp.media({
					title: pamSiteLogoL10n.selectLogo,
					button: { text: pamSiteLogoL10n.useLogo },
					multiple: false
				});

				$('#pam-upload-logo').on('click', function(e) {
					e.preventDefault();
					frame.open();
					frame.on('select', function() {
						const attachment = frame.state().get('selection').first().toJSON();
						$('#pam-site-logo').val(attachment.url);
						$('#pam-site-logo-preview').attr('src', attachment.url).show();
					});
				});

				$('#pam-remove-logo').on('click', function(e) {
					e.preventDefault();
					$('#pam-site-logo').val('');
					$('#pam-site-logo-preview').hide();
				});
			});
		</script>
		<?php
	}

	public function enqueueAdminAssets( string $hook ): void {
		if ( 'settings_page_public-art-map' === $hook ) {
			wp_enqueue_media();
		}
	}
}
