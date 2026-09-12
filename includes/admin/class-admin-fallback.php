<?php
/**
 * Self-healing fallback for this plugin's own admin screens.
 *
 * The "Heretek Core" top-level menu is registered by EMCP_Tools_Admin only when
 * the full bootstrap succeeds. When it does not — a missing dependency (the
 * bundled MCP adapter), an admin class that loaded but declared nothing, or a
 * host malware scanner that quarantines a file by ZEROING it in place so
 * `require_once` succeeds silently (issue #100) — nothing owns the `emcp-tools`
 * slug, and WordPress core ends the request from wp-admin/includes/menu.php:
 *
 *     wp_die( 'Sorry, you are not allowed to access this page.', 403 );
 *
 * The lost page turns every link into this plugin — most importantly the
 * post-activation redirect to admin.php?page=emcp-tools — into a dead end that
 * explains nothing. This class guarantees the slug always resolves: at the end
 * of `admin_menu` it registers a diagnostics page when (and only when) nothing
 * else claimed the slug, and it rescues sub-screens (`emcp-tools-tools`,
 * `-connection`, …) by bouncing them to that page instead of letting core kill
 * the request.
 *
 * On a healthy install every method here is a no-op.
 *
 * @package EMCP_Tools
 * @since   3.16.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EMCP_Tools_Admin_Fallback' ) ) {

	/**
	 * Keeps admin.php?page=emcp-tools reachable no matter how booting failed.
	 *
	 * @since 3.16.2
	 */
	final class EMCP_Tools_Admin_Fallback {

		/**
		 * Menu slug every screen of this plugin lives under.
		 */
		const SLUG = 'emcp-tools';

		/**
		 * Whether init() already ran (a second copy of the plugin may call it).
		 *
		 * @var bool
		 */
		private static $initialised = false;

		/**
		 * Hooks the fallback. Safe to call more than once.
		 */
		public static function init(): void {
			if ( self::$initialised ) {
				return;
			}
			self::$initialised = true;

			// Last thing on admin_menu, so a healthy plugin has already claimed the slug.
			add_action( 'admin_menu', array( __CLASS__, 'register' ), PHP_INT_MAX );
			// Fires from wp-admin/includes/menu.php immediately before core's 403.
			add_action( 'admin_page_access_denied', array( __CLASS__, 'rescue' ) );
		}

		/**
		 * Whether this plugin already registered its top-level page.
		 *
		 * @return bool
		 */
		private static function parent_registered(): bool {
			global $admin_page_hooks, $_registered_pages;

			return isset( $admin_page_hooks[ self::SLUG ] )
				|| isset( $_registered_pages[ 'toplevel_page_' . self::SLUG ] );
		}

		/**
		 * Registers the diagnostics page when nothing else claimed the slug.
		 */
		public static function register(): void {
			if ( self::parent_registered() ) {
				return;
			}

			add_menu_page(
				__( 'Heretek Control Core — Diagnostics', 'emcp-tools' ),
				__( 'Heretek Core', 'emcp-tools' ),
				'manage_options',
				self::SLUG,
				array( __CLASS__, 'render' ),
				'dashicons-shield-alt',
				58
			);
		}

		/**
		 * Bounces one of this plugin's own sub-screens to the parent page when the
		 * screen itself was never registered, instead of letting core answer 403.
		 */
		public static function rescue(): void {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing check.
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

			if ( '' === $page || self::SLUG === $page || 0 !== strpos( $page, self::SLUG ) ) {
				return;
			}

			// Without a registered parent there is no safe target: let core decide.
			if ( ! self::parent_registered() ) {
				return;
			}

			wp_safe_redirect(
				add_query_arg( 'emcp_admin_rescue', $page, admin_url( 'admin.php?page=' . self::SLUG ) )
			);
			exit;
		}

		/**
		 * Runtime files that must exist and be non-empty, as relative => label.
		 *
		 * A zero-byte file is the fingerprint of a host scanner that quarantined
		 * it in place: `require_once` then succeeds while the class is never
		 * declared, so the plugin is "installed but not booting".
		 *
		 * @return array<string, string>
		 */
		private static function critical_files(): array {
			return array(
				'heretek-control-core.php'                                  => __( 'Main plugin file', 'emcp-tools' ),
				'includes/class-bootstrap.php'                              => __( 'Bootstrap', 'emcp-tools' ),
				'includes/class-plugin.php'                                 => __( 'Plugin singleton', 'emcp-tools' ),
				'includes/class-mcp-adapter-bootstrap.php'                  => __( 'MCP adapter bootstrap', 'emcp-tools' ),
				'includes/admin/class-admin.php'                            => __( 'Admin screens', 'emcp-tools' ),
				'vendor/autoload_packages.php'                              => __( 'Bundled autoloader', 'emcp-tools' ),
				'vendor/wordpress/mcp-adapter/includes/Core/McpAdapter.php' => __( 'Bundled MCP adapter', 'emcp-tools' ),
			);
		}

		/**
		 * This plugin's root directory, with a trailing slash.
		 *
		 * Resolved without EMCP_TOOLS_DIR so the fallback also works when it is
		 * armed before the plugin defined its constants.
		 *
		 * @return string
		 */
		private static function base_dir(): string {
			return defined( 'EMCP_TOOLS_DIR' )
				? EMCP_TOOLS_DIR
				: trailingslashit( dirname( __DIR__, 2 ) );
		}

		/**
		 * State of one runtime file: 'ok', 'missing' or 'empty'.
		 *
		 * @param string $relative Path relative to the plugin directory.
		 * @return array{state:string, size:int}
		 */
		private static function file_state( string $relative ): array {
			$path = self::base_dir() . $relative;

			if ( ! file_exists( $path ) ) {
				return array(
					'state' => 'missing',
					'size'  => 0,
				);
			}

			$size = (int) filesize( $path );

			return array(
				'state' => 0 === $size ? 'empty' : 'ok',
				'size'  => $size,
			);
		}

		/**
		 * Active-plugin entries belonging to this plugin family (any folder).
		 *
		 * @return string[]
		 */
		private static function own_active_entries(): array {
			$entries = (array) get_option( 'active_plugins', array() );
			$needles = array( 'heretek-control-core', 'emcp-tools', 'elementor-mcp', 'emcp-pro' );

			return array_values(
				array_filter(
					$entries,
					static function ( $basename ) use ( $needles ) {
						$basename = (string) $basename;

						foreach ( $needles as $needle ) {
							if ( false !== strpos( $basename, $needle ) ) {
								return true;
							}
						}

						return false;
					}
				)
			);
		}

		/**
		 * Renders the diagnostics screen.
		 */
		public static function render(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to access this page.', 'emcp-tools' ) );
			}

			$emcp_adapter_ok = class_exists( '\WP\MCP\Core\McpAdapter' );
			$emcp_booted     = class_exists( 'EMCP_Tools_Plugin' );
			$emcp_admin_ok   = class_exists( 'EMCP_Tools_Admin' );
			$emcp_api_ok     = function_exists( 'wp_register_ability' );
			$emcp_version    = defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '—';
			$emcp_basename   = defined( 'EMCP_TOOLS_BASENAME' ) ? EMCP_TOOLS_BASENAME : '—';
			$emcp_premium    = file_exists( self::base_dir() . '.emcp-pro' );
			$emcp_source     = class_exists( 'EMCP_Tools_Adapter_Bootstrap' ) ? EMCP_Tools_Adapter_Bootstrap::source() : '—';

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostic hint.
			$emcp_rescued = isset( $_GET['emcp_admin_rescue'] ) ? sanitize_key( wp_unslash( $_GET['emcp_admin_rescue'] ) ) : '';

			$emcp_broken_files = array();
			foreach ( array_keys( self::critical_files() ) as $emcp_file ) {
				$emcp_state = self::file_state( $emcp_file );
				if ( 'ok' !== $emcp_state['state'] ) {
					$emcp_broken_files[] = $emcp_file;
				}
			}
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Heretek Control Core — Diagnostics', 'emcp-tools' ); ?></h1>

				<div class="notice notice-error inline" style="margin:12px 0;">
					<p>
						<strong><?php esc_html_e( 'The plugin could not finish booting, so its normal screens are unavailable.', 'emcp-tools' ); ?></strong>
						<?php esc_html_e( 'This page is a stand-in that explains why. Nothing has been changed on your site.', 'emcp-tools' ); ?>
					</p>
				</div>

				<?php if ( '' !== $emcp_rescued ) : ?>
					<p>
						<?php
						printf(
							/* translators: %s: the admin page slug that was requested. */
							esc_html__( 'You asked for "%s", which is not registered right now — you were sent here instead of seeing a "not allowed" error.', 'emcp-tools' ),
							'<code>' . esc_html( $emcp_rescued ) . '</code>'
						);
						?>
					</p>
				<?php endif; ?>

				<h2><?php esc_html_e( 'Build', 'emcp-tools' ); ?></h2>
				<table class="widefat striped" style="max-width:900px;">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Version', 'emcp-tools' ); ?></td>
							<td><code><?php echo esc_html( $emcp_version ); ?></code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Installed as', 'emcp-tools' ); ?></td>
							<td><code><?php echo esc_html( $emcp_basename ); ?></code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Premium marker (.emcp-pro)', 'emcp-tools' ); ?></td>
							<td><?php echo $emcp_premium ? esc_html__( 'present', 'emcp-tools' ) : esc_html__( 'absent', 'emcp-tools' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'PHP / WordPress', 'emcp-tools' ); ?></td>
							<td><code><?php echo esc_html( PHP_VERSION ); ?></code> / <code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Abilities API (wp_register_ability)', 'emcp-tools' ); ?></td>
							<td><?php echo $emcp_api_ok ? esc_html__( 'available', 'emcp-tools' ) : esc_html__( 'MISSING — WordPress 6.9+ is required', 'emcp-tools' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'MCP Adapter class', 'emcp-tools' ); ?></td>
							<td>
								<?php echo $emcp_adapter_ok ? esc_html__( 'loaded', 'emcp-tools' ) : esc_html__( 'NOT LOADED', 'emcp-tools' ); ?>
								(<code><?php echo esc_html( $emcp_source ); ?></code>)
							</td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Plugin booted', 'emcp-tools' ); ?></td>
							<td><?php echo $emcp_booted ? esc_html__( 'yes', 'emcp-tools' ) : esc_html__( 'no — the bootstrap did not finish', 'emcp-tools' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Admin screens loaded', 'emcp-tools' ); ?></td>
							<td><?php echo $emcp_admin_ok ? esc_html__( 'yes', 'emcp-tools' ) : esc_html__( 'no — nothing registered the menu', 'emcp-tools' ); ?></td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'File integrity', 'emcp-tools' ); ?></h2>
				<table class="widefat striped" style="max-width:900px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Runtime file', 'emcp-tools' ); ?></th>
							<th><?php esc_html_e( 'State', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( self::critical_files() as $emcp_file => $emcp_label ) : ?>
							<?php $emcp_state = self::file_state( $emcp_file ); ?>
							<tr>
								<td>
									<code><?php echo esc_html( $emcp_file ); ?></code><br />
									<span class="description"><?php echo esc_html( $emcp_label ); ?></span>
								</td>
								<td>
									<?php if ( 'ok' === $emcp_state['state'] ) : ?>
										<?php
										printf(
											/* translators: %s: file size in bytes. */
											esc_html__( 'OK (%s bytes)', 'emcp-tools' ),
											esc_html( number_format_i18n( $emcp_state['size'] ) )
										);
										?>
									<?php elseif ( 'empty' === $emcp_state['state'] ) : ?>
										<strong style="color:#b32d2e;"><?php esc_html_e( 'EMPTY (0 bytes) — the file was emptied in place, most likely quarantined by a host malware scanner', 'emcp-tools' ); ?></strong>
									<?php else : ?>
										<strong style="color:#b32d2e;"><?php esc_html_e( 'MISSING', 'emcp-tools' ); ?></strong>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Other active copies of this plugin', 'emcp-tools' ); ?></h2>
				<?php $emcp_own_entries = self::own_active_entries(); ?>
				<?php if ( empty( $emcp_own_entries ) ) : ?>
					<p><?php esc_html_e( 'None.', 'emcp-tools' ); ?></p>
				<?php else : ?>
					<ul style="list-style:disc;margin-left:20px;">
						<?php foreach ( $emcp_own_entries as $emcp_entry ) : ?>
							<li><code><?php echo esc_html( $emcp_entry ); ?></code></li>
						<?php endforeach; ?>
					</ul>
					<p class="description">
						<?php esc_html_e( 'Only one copy of this plugin may be active. A second copy in another folder is deactivated automatically to prevent the MCP server being registered twice.', 'emcp-tools' ); ?>
					</p>
				<?php endif; ?>

				<h2><?php esc_html_e( 'What to do next', 'emcp-tools' ); ?></h2>
				<?php if ( ! empty( $emcp_broken_files ) ) : ?>
					<ol style="list-style:decimal;margin-left:20px;">
						<li>
							<?php esc_html_e( 'Re-upload the plugin files: the list above shows a file that is missing or was emptied.', 'emcp-tools' ); ?>
						</li>
						<li>
							<?php
							printf(
								/* translators: %s: comma-separated list of files. */
								esc_html__( 'If they are emptied again immediately, your host is quarantining them. Ask the host to allowlist %s, or add the plugin folder to the scanner exclusions.', 'emcp-tools' ),
								'<code>' . esc_html( implode( ', ', $emcp_broken_files ) ) . '</code>'
							);
							?>
						</li>
					</ol>
				<?php elseif ( ! $emcp_api_ok ) : ?>
					<p>
						<?php esc_html_e( 'This plugin needs the WordPress Abilities API, which is part of WordPress 6.9 and newer. Update WordPress, then reload this page.', 'emcp-tools' ); ?>
					</p>
				<?php elseif ( ! $emcp_adapter_ok ) : ?>
					<p>
						<?php esc_html_e( 'The bundled MCP Adapter could not be loaded. Reinstall the plugin (the vendor/ directory must be uploaded together with the rest of the plugin), then reload this page.', 'emcp-tools' ); ?>
					</p>
				<?php elseif ( $emcp_admin_ok ) : ?>
					<p>
						<?php esc_html_e( 'Everything the plugin needs is present. Reload the main plugin screen from the Heretek Core menu.', 'emcp-tools' ); ?>
					</p>
				<?php else : ?>
					<p>
						<?php esc_html_e( 'The plugin booted but could not register its admin screens. Please report this page along with the values above.', 'emcp-tools' ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php
		}
	}
}
