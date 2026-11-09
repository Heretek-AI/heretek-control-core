<?php
/**
 * One-click install and activation of the official MCP Adapter plugin.
 *
 * EMCP runs its MCP server on the MCP Adapter (wordpress.org slug
 * `mcp-adapter`). The official plugin serves when it is active; otherwise EMCP's
 * own copy does, a permanent fallback (see EMCP_Tools_Adapter_Bootstrap). An
 * admin notice appears only when something needs the admin: no adapter loads,
 * the official plugin is too old, or another plugin's copy is in use. Its one
 * button installs the plugin from wordpress.org when needed and activates it,
 * and the Connection screen offers the same button as an option. The notice can
 * be dismissed per user until the state changes. On hosts where WordPress
 * cannot write files without FTP credentials the button sends the admin to
 * core's own installer, which asks for them.
 *
 * @package EMCP_Tools
 * @since   3.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Install/activate action and the admin banner for the MCP Adapter.
 */
final class EMCP_Tools_Adapter_Installer {

	/** admin-post action and nonce action. */
	const ACTION = 'emcp_tools_install_mcp_adapter';

	/** Query arg carrying the outcome back to the screen the admin came from. */
	const RESULT_ARG = 'emcp_mcp_adapter';

	/** admin-post action and nonce action for "Not now". */
	const DISMISS_ACTION = 'emcp_tools_dismiss_mcp_adapter';

	/** User meta holding the state a user dismissed the recommendation for. */
	const DISMISSED_META = 'emcp_tools_mcp_adapter_dismissed';

	/** Wire the action and the notices. Runs in wp-admin only. */
	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'dismiss' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		// The Dashboard caches its attention items; the adapter state changes here.
		foreach ( array( 'activated_plugin', 'deactivated_plugin' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush_attention' ) );
		}
	}

	/** Drop the cached Dashboard attention items. */
	public static function flush_attention(): void {
		if ( class_exists( 'EMCP_Tools_Attention' ) ) {
			EMCP_Tools_Attention::flush();
		}
	}

	/**
	 * Whether the current user can complete the action for this state.
	 *
	 * @param string $state EMCP_Tools_Adapter_Bootstrap::status() state.
	 */
	public static function can( string $state ): bool {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}
		// Network-wide activation (EMCP is network-activated) is a network admin's call:
		// activate_plugin() checks no capability itself, so a site admin must not reach it.
		if ( self::network_wide() && ! current_user_can( 'manage_network_plugins' ) ) {
			return false;
		}
		// Installing or updating needs more than activating.
		if ( 'missing' === $state ) {
			return current_user_can( 'install_plugins' );
		}
		if ( 'old' === $state ) {
			return current_user_can( 'update_plugins' );
		}
		return true;
	}

	/** The one-click URL (nonce-protected admin-post GET). */
	public static function action_url(): string {
		$network = self::network_wide() ? network_admin_url( 'admin-post.php' ) : admin_url( 'admin-post.php' );
		return wp_nonce_url( add_query_arg( 'action', self::ACTION, $network ), self::ACTION );
	}

	/** @var bool|null Test seam: forces network_wide(). */
	private static $network_wide = null;

	/** Test seam: force network_wide() (null restores detection). */
	public static function use_network_wide( ?bool $network_wide ): void {
		self::$network_wide = $network_wide;
	}

	/** Whether to activate network-wide: EMCP itself is network-activated. */
	private static function network_wide(): bool {
		if ( null !== self::$network_wide ) {
			return self::$network_wide;
		}
		if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
			return false;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return defined( 'EMCP_TOOLS_BASENAME' ) && is_plugin_active_for_network( EMCP_TOOLS_BASENAME );
	}

	/**
	 * The banner for a status.
	 *
	 * @param array $status EMCP_Tools_Adapter_Bootstrap::status().
	 * @param bool  $loaded Whether any adapter copy is loaded.
	 * @return array{type: string, message: string, button: string, dismissible: bool}|null
	 *   Null when nothing is needed. `error` only when no adapter loads at all.
	 */
	public static function banner( array $status, bool $loaded ): ?array {
		$state = (string) ( $status['state'] ?? 'ok' );
		if ( 'ok' === $state ) {
			return null;
		}
		$button = array(
			'missing'  => __( 'Install and activate MCP Adapter', 'emcp-tools' ),
			'inactive' => __( 'Activate MCP Adapter', 'emcp-tools' ),
			'old'      => __( 'Update MCP Adapter', 'emcp-tools' ),
		)[ $state ] ?? '';
		if ( ! $loaded ) {
			$message = __( 'EMCP Tools needs the free MCP Adapter plugin from WordPress.org for its MCP server. Until it is active, AI clients cannot connect to this site.', 'emcp-tools' );
			return array( 'type' => 'error', 'message' => $message, 'button' => $button, 'dismissible' => false );
		}
		if ( 'old' === $state ) {
			/* translators: 1: installed version, 2: minimum version. */
			$message = sprintf( __( 'The MCP Adapter plugin is version %1$s; EMCP Tools uses it from version %2$s. Until it is updated, EMCP Tools keeps using its own copy, and your AI connections keep working.', 'emcp-tools' ), (string) $status['version'], EMCP_Tools_Adapter_Bootstrap::MIN_VERSION );
			return array( 'type' => 'warning', 'message' => $message, 'button' => $button, 'dismissible' => true );
		}
		if ( '' === (string) ( $status['plugin'] ?? '' ) ) {
			// EMCP's own copy serves, a permanent fallback: nothing to ask of the admin.
			return null;
		}
		/* translators: %s: plugin folder of the copy in use. */
		$message = sprintf( __( 'Your AI connections work, using the copy of MCP Adapter that %s bundles. You can install the official MCP Adapter plugin from WordPress.org instead.', 'emcp-tools' ), (string) $status['plugin'] );
		return array( 'type' => 'info', 'message' => $message, 'button' => $button, 'dismissible' => true );
	}

	/**
	 * The optional one-click offer on the Connection screen: shown whenever the
	 * official plugin is not the copy in use, banner or not.
	 *
	 * @param array $status EMCP_Tools_Adapter_Bootstrap::status().
	 * @param bool  $loaded Whether any adapter copy is loaded.
	 * @return array{label: string, optional: bool}|null Null when the official plugin serves.
	 *   `optional` is true when no banner asks for it (EMCP's own copy serves).
	 */
	public static function offer( array $status, bool $loaded ): ?array {
		$state = (string) ( $status['state'] ?? 'ok' );
		$label = array(
			'missing'  => __( 'Install and activate MCP Adapter', 'emcp-tools' ),
			'inactive' => __( 'Activate MCP Adapter', 'emcp-tools' ),
			'old'      => __( 'Update MCP Adapter', 'emcp-tools' ),
		)[ $state ] ?? '';
		if ( '' === $label ) {
			return null;
		}
		return array( 'label' => $label, 'optional' => null === self::banner( $status, $loaded ) );
	}

	/**
	 * What a dismissal applies to: a new state brings the notice back.
	 *
	 * @param array $status EMCP_Tools_Adapter_Bootstrap::status().
	 */
	public static function signature( array $status ): string {
		return (string) ( $status['state'] ?? '' ) . '|' . (string) ( $status['plugin'] ?? '' );
	}

	/** The "Not now" URL. */
	public static function dismiss_url(): string {
		return wp_nonce_url( add_query_arg( 'action', self::DISMISS_ACTION, self_admin_url( 'admin-post.php' ) ), self::DISMISS_ACTION );
	}

	/** admin-post "Not now": remember the dismissed state for this user. */
	public static function dismiss(): void {
		check_admin_referer( self::DISMISS_ACTION );
		update_user_meta( get_current_user_id(), self::DISMISSED_META, self::signature( EMCP_Tools_Adapter_Bootstrap::status() ) );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : self_admin_url() );
		exit;
	}

	/** Print the banner, or the outcome of the last click. */
	public static function notice(): void {
		$result = isset( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( 'activated' === $result ) {
			$done = __( 'MCP Adapter is active. Your AI clients can connect to this site.', 'emcp-tools' );
			if ( self::on_frame() ) {
				self::frame_notice( 'success', $done, '', '', '', '' );
			} else {
				wp_admin_notice( esc_html( $done ), array( 'type' => 'success', 'dismissible' => true ) );
			}
			return;
		}

		$status = EMCP_Tools_Adapter_Bootstrap::status();
		$banner = self::banner( $status, EMCP_Tools_Adapter_Bootstrap::is_loaded() );
		if ( null === $banner || ! self::can( (string) $status['state'] ) ) {
			return;
		}
		if ( $banner['dismissible'] && '' === $result && self::signature( $status ) === (string) get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}

		$error = '';
		if ( '' !== $result ) {
			$error = (string) get_transient( self::error_key() );
			delete_transient( self::error_key() );
		}

		$dismiss = $banner['dismissible'] ? self::dismiss_url() : '';
		if ( self::on_frame() ) {
			self::frame_notice( $banner['type'], $banner['message'], $error, $banner['button'], self::action_url(), $dismiss );
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $banner['type'] ) . ' emcp-mcp-adapter-notice"><p><strong>' . esc_html__( 'MCP Adapter', 'emcp-tools' ) . ':</strong> ' . esc_html( $banner['message'] ) . '</p>';
		if ( '' !== $error ) {
			echo '<p>' . esc_html( $error ) . '</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( self::action_url() ) . '">' . esc_html( $banner['button'] ) . '</a>';
		if ( '' !== $dismiss ) {
			echo ' <a class="button button-link" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Not now', 'emcp-tools' ) . '</a>';
		}
		echo '</p></div>';
	}

	/** Whether this request renders an EMCP admin screen (the frame). */
	private static function on_frame(): bool {
		$page = isset( $GLOBALS['plugin_page'] ) ? (string) $GLOBALS['plugin_page'] : '';
		return class_exists( 'EMCP_Tools_Admin' ) && class_exists( 'EMCP_Tools_Admin_Icons' )
			&& ( EMCP_Tools_Admin::PAGE_SLUG === $page || 0 === strpos( $page, EMCP_Tools_Admin::PAGE_SLUG . '-' ) );
	}

	/**
	 * The notice in the plugin's own look, for EMCP screens. It keeps core's
	 * `notice` class so core still moves it after `.wp-header-end`.
	 *
	 * @param string $type    info, warning, error or success.
	 * @param string $message Text.
	 * @param string $error   Last failure, '' for none.
	 * @param string $button  Button label, '' for none.
	 * @param string $url     Button URL.
	 * @param string $dismiss "Not now" URL, '' for none.
	 */
	private static function frame_notice( string $type, string $message, string $error, string $button, string $url, string $dismiss ): void {
		$tone = array( 'error' => 'danger', 'warning' => 'warning', 'success' => 'success' )[ $type ] ?? 'info';
		$icon = array( 'danger' => 'circle-alert', 'warning' => 'triangle-alert', 'success' => 'circle-check' )[ $tone ] ?? 'plug';
		echo '<div class="notice emcp-frame-notice eui-notice eui-notice--' . esc_attr( $tone ) . ' emcp-mcp-adapter-notice" role="status">';
		echo '<span class="eui-notice__icon">' . EMCP_Tools_Admin_Icons::svg( $icon, 16 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted SVG.
		echo '<div class="eui-notice__body"><strong class="eui-notice__title">' . esc_html__( 'MCP Adapter', 'emcp-tools' ) . '</strong> ' . esc_html( $message );
		if ( '' !== $error ) {
			echo '<br>' . esc_html( $error );
		}
		echo '</div>';
		if ( '' !== $button || '' !== $dismiss ) {
			echo '<div class="eui-notice__actions">';
			if ( '' !== $button ) {
				echo '<a class="eui-btn eui-btn--primary eui-btn--sm" href="' . esc_url( $url ) . '"><span class="eui-btn__label">' . esc_html( $button ) . '</span></a>';
			}
			if ( '' !== $dismiss ) {
				echo '<a class="eui-btn eui-btn--ghost eui-btn--sm" href="' . esc_url( $dismiss ) . '"><span class="eui-btn__label">' . esc_html__( 'Not now', 'emcp-tools' ) . '</span></a>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/** Per-user transient holding the last failure message. */
	private static function error_key(): string {
		return 'emcp_mcp_adapter_error_' . get_current_user_id();
	}

	/** The admin-post handler. */
	public static function handle(): void {
		check_admin_referer( self::ACTION );
		$status = EMCP_Tools_Adapter_Bootstrap::status();
		$state  = (string) $status['state'];
		if ( ! self::can( $state ) ) {
			wp_die( esc_html__( 'You are not allowed to install or activate plugins.', 'emcp-tools' ), 403 );
		}

		$result = self::run( $state );
		if ( is_string( $result ) ) {
			// A URL: core's installer, which asks for FTP credentials itself.
			wp_safe_redirect( $result );
			exit;
		}
		if ( is_wp_error( $result ) ) {
			set_transient( self::error_key(), $result->get_error_message(), 10 * MINUTE_IN_SECONDS );
		}
		$back = wp_get_referer();
		$back = $back ? $back : ( self::network_wide() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) );
		wp_safe_redirect( add_query_arg( self::RESULT_ARG, is_wp_error( $result ) ? 'failed' : 'activated', $back ) );
		exit;
	}

	/**
	 * Install (or update) when needed, then activate.
	 *
	 * @param string $state status() state.
	 * @return true|\WP_Error|string True when active, an error, or a URL to
	 *                               core's installer when files need credentials.
	 */
	public static function run( string $state ) {
		if ( ! class_exists( 'EMCP_Tools_Package_Guard' ) ) {
			require_once EMCP_TOOLS_DIR . 'includes/class-package-guard.php';
		}
		EMCP_Tools_Package_Guard::load_upgrader_deps();

		if ( 'missing' === $state || 'old' === $state ) {
			if ( true !== EMCP_Tools_Package_Guard::filesystem_ready() ) {
				return 'missing' === $state
					? wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . EMCP_Tools_Adapter_Bootstrap::SLUG ), 'install-plugin_' . EMCP_Tools_Adapter_Bootstrap::SLUG )
					: wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( EMCP_Tools_Adapter_Bootstrap::PLUGIN_FILE ) ), 'upgrade-plugin_' . EMCP_Tools_Adapter_Bootstrap::PLUGIN_FILE );
			}
			$skin     = EMCP_Tools_Package_Guard::make_skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			if ( 'missing' === $state ) {
				$api = plugins_api( 'plugin_information', array( 'slug' => EMCP_Tools_Adapter_Bootstrap::SLUG, 'fields' => array( 'sections' => false ) ) );
				if ( is_wp_error( $api ) ) {
					return $api;
				}
				$done = $upgrader->install( $api->download_link );
			} else {
				wp_update_plugins();
				$done = $upgrader->upgrade( EMCP_Tools_Adapter_Bootstrap::PLUGIN_FILE );
			}
			if ( is_wp_error( $done ) ) {
				return $done;
			}
			if ( ! $done ) {
				$messages = EMCP_Tools_Package_Guard::skin_messages( $skin );
				return new \WP_Error( 'mcp_adapter_install_failed', $messages ? (string) end( $messages ) : __( 'MCP Adapter could not be installed.', 'emcp-tools' ) );
			}
			if ( 'old' === $state ) {
				// An update leaves the plugin as it was: active.
				return true;
			}
		}

		if ( '' === EMCP_Tools_Adapter_Bootstrap::installed_file() ) {
			return new \WP_Error( 'mcp_adapter_not_installed', __( 'MCP Adapter was not found in the plugins folder after installing it.', 'emcp-tools' ) );
		}
		$active = activate_plugin( EMCP_Tools_Adapter_Bootstrap::PLUGIN_FILE, '', self::network_wide() );
		return is_wp_error( $active ) ? $active : true;
	}
}
