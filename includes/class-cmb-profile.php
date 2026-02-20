<?php
/**
 * Color Me Beautiful – User Profile Fields
 *
 * Renders the "Color Me Beautiful" section on the user-profile and
 * user-edit admin screens, and persists the chosen preset and custom
 * accent colour to user meta.
 *
 * @package    cm-beautiful
 * @since      1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles profile-page UI and meta persistence for the colour preferences.
 *
 * @since 1.0.0
 */
class CMB_Profile {

	/**
	 * Curated colour presets available to users.
	 *
	 * Keys are stored in user meta. 'hex' is null for the "follow WordPress"
	 * option, which delegates to the active WP admin colour scheme.
	 * Labels are translation keys — use get_preset_label() for display.
	 *
	 * @since 1.0.0
	 * @var   array<string, array{label: string, hex: string|null}>
	 */
	public const PRESETS = [
		'follow_wp'    => [ 'label' => 'Follow WordPress', 'hex' => null ],
		'neutral_blue' => [ 'label' => 'Neutral Blue', 'hex' => '#2271b1' ],
		'indigo'       => [ 'label' => 'Indigo', 'hex' => '#4f46e5' ],
		'teal'         => [ 'label' => 'Teal', 'hex' => '#0f766e' ],
		'green'        => [ 'label' => 'Green', 'hex' => '#15803d' ],
		'amber'        => [ 'label' => 'Amber', 'hex' => '#b45309' ],
		'red'          => [ 'label' => 'Red', 'hex' => '#b91c1c' ],
		'slate'        => [ 'label' => 'Slate', 'hex' => '#334155' ],
	];

	/**
	 * Get translated label for a preset.
	 *
	 * Labels cannot be translated in the PRESETS constant because __() is a
	 * runtime function. This method provides the translated version.
	 *
	 * @since 1.0.1
	 * @param  string $key Preset key.
	 * @return string      Translated label, or the key if not found.
	 */
	public static function get_preset_label( string $key ): string {
		$labels = [
			'follow_wp'    => __( 'Follow WordPress', 'cm-beautiful' ),
			'neutral_blue' => __( 'Neutral Blue', 'cm-beautiful' ),
			'indigo'       => __( 'Indigo', 'cm-beautiful' ),
			'teal'         => __( 'Teal', 'cm-beautiful' ),
			'green'        => __( 'Green', 'cm-beautiful' ),
			'amber'        => __( 'Amber', 'cm-beautiful' ),
			'red'          => __( 'Red', 'cm-beautiful' ),
			'slate'        => __( 'Slate', 'cm-beautiful' ),
		];

		return $labels[ $key ] ?? $key;
	}

	/**
	 * User-meta key for the selected preset.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const META_PRESET = 'cmb_ui_preset';

	/**
	 * User-meta key for the optional custom accent colour.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const META_CUSTOM_ACCENT = 'cmb_ui_custom_accent';

	/**
	 * User-meta key for the night-mode toggle.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const META_NIGHT_MODE = 'cmb_ui_night_mode';

	/**
	 * Register WordPress hooks.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_hooks(): void {
		/*
		 * `personal_options` fires at the END of the 'Personal Options' table —
		 * directly after the Administration Color Scheme row — on BOTH the
		 * profile.php (own profile) and user-edit.php (edit other user) screens.
		 * It passes the WP_User object and we are still inside <table>, so we
		 * output <tr> rows rather than a standalone <h2>+<table> block.
		 */
		add_action( 'personal_options', [ $this, 'render_profile_fields' ] );

		// Save when the current user updates their own profile.
		add_action( 'personal_options_update', [ $this, 'save_profile_fields' ] );

		// Save when an admin updates another user's profile.
		add_action( 'edit_user_profile_update', [ $this, 'save_profile_fields' ] );
	}

	/**
	 * Output "Color Me Beautiful" rows inside the Personal Options table.
	 *
	 * Called on the `personal_options` action, which fires while the
	 * Personal Options <table class="form-table"> is still open.  We therefore
	 * output <tr> rows directly — no surrounding <h2> or <table> needed.
	 *
	 * The section heading spans both columns of the table.  The nonce hidden
	 * input lives inside that heading cell so the markup stays valid.
	 *
	 * @since  1.0.0
	 * @param  WP_User $user The user whose profile is being rendered.
	 * @return void
	 */
	public function render_profile_fields( WP_User $user ): void {
		$preset        = (string) get_user_meta( $user->ID, self::META_PRESET, true );
		$custom_accent = (string) get_user_meta( $user->ID, self::META_CUSTOM_ACCENT, true );
		$night_mode    = (bool) get_user_meta( $user->ID, self::META_NIGHT_MODE, true );

		// Fall back to the WordPress-native setting when meta is unset or invalid.
		if ( '' === $preset || ! array_key_exists( $preset, self::PRESETS ) ) {
			$preset = 'follow_wp';
		}

		// Resolve initial swatch colour for the preset row.
		$preset_swatch_color = self::PRESETS[ $preset ][ 'hex' ] ?? '';
		?>

		<tr class="user-cmb-section-header">
			<th colspan="2">
				<h3 style="margin:1em 0 0;"><?php esc_html_e( 'Color Me Beautiful', 'cm-beautiful' ); ?></h3>
				<?php
				/*
				 * The nonce hidden input lives here — inside the <th> — so it
				 * remains within valid table structure while still being submitted
				 * with the profile form.
				 */
				wp_nonce_field( 'cmb_save_profile_' . $user->ID, 'cmb_profile_nonce' );
				?>
			</th>
		</tr>

		<tr class="user-cmb-preset-wrap">
			<th scope="row">
				<label for="cmb_ui_preset">
					<?php esc_html_e( 'Colour Preset', 'cm-beautiful' ); ?>
				</label>
			</th>
			<td>
				<select id="cmb_ui_preset" name="<?php echo esc_attr( self::META_PRESET ); ?>">
					<?php foreach ( self::PRESETS as $key => $data ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" data-hex="<?php echo esc_attr( $data[ 'hex' ] ?? '' ); ?>"
							<?php selected( $preset, $key ); ?>>
						<?php echo esc_html( self::get_preset_label( $key ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span id="cmb-preset-swatch" class="cmb-swatch"
					style="<?php echo '' !== $preset_swatch_color ? 'background-color:' . esc_attr( $preset_swatch_color ) . ';' : ''; ?>"
					aria-hidden="true"></span>
				<p class="description">
					<?php esc_html_e( 'Choose a colour preset for this plugin\'s admin UI.', 'cm-beautiful' ); ?>
				</p>
			</td>
		</tr>

		<tr class="user-cmb-custom-accent-wrap">
			<th scope="row">
				<label for="cmb_ui_custom_accent">
					<?php esc_html_e( 'Custom Accent', 'cm-beautiful' ); ?>
				</label>
			</th>
			<td>
				<input type="text" id="cmb_ui_custom_accent" name="<?php echo esc_attr( self::META_CUSTOM_ACCENT ); ?>"
					value="<?php echo esc_attr( $custom_accent ); ?>" class="cmb-color-picker" data-default-color="" />
				<span id="cmb-custom-swatch" class="cmb-swatch"
					style="<?php echo '' !== $custom_accent ? 'background-color:' . esc_attr( $custom_accent ) . ';' : ''; ?>"
					aria-hidden="true"></span>
				<p class="description">
					<?php esc_html_e( 'Overrides the preset accent colour when set. Clear the field to fall back to the preset.', 'cm-beautiful' ); ?>
				</p>
			</td>
		</tr>

		<tr class="user-cmb-night-mode-wrap">
			<th scope="row">
				<label for="cmb_ui_night_mode">
					<?php esc_html_e( 'Night Mode', 'cm-beautiful' ); ?>
				</label>
			</th>
			<td>
				<label>
					<input type="checkbox" id="cmb_ui_night_mode" name="<?php echo esc_attr( self::META_NIGHT_MODE ); ?>"
						value="1" <?php checked( $night_mode ); ?> />
					<?php esc_html_e( 'Invert admin colours (dark / night mode)', 'cm-beautiful' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/**
	 * Validate and persist the profile-field values submitted from the form.
	 *
	 * @since  1.0.0
	 * @param  int $user_id ID of the user whose profile was saved.
	 * @return void
	 */
	public function save_profile_fields( int $user_id ): void {
		// ------------------------------------------------------------------
		// Nonce verification.
		// ------------------------------------------------------------------
		if (
			! isset( $_POST[ 'cmb_profile_nonce' ] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ 'cmb_profile_nonce' ] ) ),
				'cmb_save_profile_' . $user_id
			)
		) {
			return;
		}

		// ------------------------------------------------------------------
		// Capability check.
		// ------------------------------------------------------------------
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// ------------------------------------------------------------------
		// Preset: must exist in the allowed list; default to follow_wp.
		// ------------------------------------------------------------------
		$raw_preset = isset( $_POST[ self::META_PRESET ] )
			? sanitize_key( wp_unslash( $_POST[ self::META_PRESET ] ) )
			: 'follow_wp';

		$preset = array_key_exists( $raw_preset, self::PRESETS ) ? $raw_preset : 'follow_wp';

		update_user_meta( $user_id, self::META_PRESET, $preset );

		// ------------------------------------------------------------------
		// Custom accent: must be a valid hex colour or empty.
		//
		// When "Follow WordPress" is selected the custom accent is discarded
		// so the saved state matches the live-preview expectation: selecting
		// Follow WordPress means "use WP's colour, clear any override".
		// ------------------------------------------------------------------
		if ( 'follow_wp' === $preset ) {
			delete_user_meta( $user_id, self::META_CUSTOM_ACCENT );
		} else {
			$raw_accent = isset( $_POST[ self::META_CUSTOM_ACCENT ] )
				? sanitize_text_field( wp_unslash( $_POST[ self::META_CUSTOM_ACCENT ] ) )
				: '';

			if ( '' !== $raw_accent && CMB_Color::is_valid_hex( $raw_accent ) ) {
				update_user_meta(
					$user_id,
					self::META_CUSTOM_ACCENT,
					CMB_Color::normalize_hex( $raw_accent )
				);
			} else {
				// Treat invalid or empty submission as "no custom accent".
				delete_user_meta( $user_id, self::META_CUSTOM_ACCENT );
			}
		}

		// ------------------------------------------------------------------
		// Night mode: checkbox — present = on, absent = off.
		// ------------------------------------------------------------------
		if ( ! empty( $_POST[ self::META_NIGHT_MODE ] ) ) {
			update_user_meta( $user_id, self::META_NIGHT_MODE, '1' );
		} else {
			delete_user_meta( $user_id, self::META_NIGHT_MODE );
		}
	}
}
