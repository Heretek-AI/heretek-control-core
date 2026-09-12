<?php
/**
 * Plugin Name:       Heretek Control Core
 * Plugin URI:        https://github.com/Heretek-AI/heretek-control-core
 * Description:       Autonomous Model Context Protocol (MCP) server connecting site builders, themes, and content management to AI assistants (Claude, Cursor, ChatGPT, Antigravity).
 * Version:           3.16.1
 * Requires at least: 6.9
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            Heretek AI (originally by Mian Shahzad Raza)
 * Author URI:        https://github.com/Heretek-AI
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       emcp-tools
 * Domain Path:       /languages
 *
 * This file is the bootstrap ONLY: plugin header, the legacy-rename guard,
 * constants, the Freemius SDK helper, the uninstall hook, and the entry point
 * that hands off to EMCP_Tools_Bootstrap. All feature logic lives in classes
 * under includes/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free ⇄ Premium single-instance guard.
 *
 * The free build (folder `heretek-control-core`) and the premium build
 * are the SAME plugin codebase differing only by the `pro/*` overlay
 * and the `.emcp-pro` marker.
 */
if ( ! function_exists( 'emcp_tools_retire_sibling' ) ) {
	/**
	 * Deactivate a sibling EMCP / Heretek build on the next admin_init.
	 *
	 * @param string $basename Plugin basename to deactivate.
	 */
	function emcp_tools_retire_sibling( $basename ) {
		// NEVER retire ourselves. The free/premium folder names this plugin
		// defines are also the folder names it is legitimately installed under,
		// so a build whose basename matches one of them deactivated ITSELF on the
		// next admin_init and vanished the instant it was activated. Guarded here
		// as well as at the call sites: this is the difference between resolving
		// a sibling conflict and committing suicide.
		$emcp_tools_self = defined( 'EMCP_TOOLS_BASENAME' )
			? EMCP_TOOLS_BASENAME
			: plugin_basename( __FILE__ );

		if ( ! is_string( $basename ) || '' === $basename || $basename === $emcp_tools_self ) {
			return;
		}

		add_action(
			'admin_init',
			function () use ( $basename ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				if ( is_plugin_active( $basename ) ) {
					deactivate_plugins( $basename );
					set_transient( 'emcp_tools_free_retired', 1, 60 );
				}
			}
		);
	}
}

/*
 * This plugin's own admin URL must ALWAYS resolve.
 *
 * The `emcp-tools` page is registered only when the full bootstrap succeeds. If
 * it does not — a missing dependency, an admin class that loaded but declared
 * nothing, or a host malware scanner that quarantines a file by ZEROING it in
 * place so require_once succeeds silently (issue #100) — then nothing owns the
 * slug and WordPress core ends the request from wp-admin/includes/menu.php with:
 *
 *     wp_die( 'Sorry, you are not allowed to access this page.', 403 );
 *
 * Every link into the plugin then dead-ends with no explanation, and the
 * post-activation redirect lands on exactly that URL. Registering a fallback
 * diagnostics page for the slug turns that 403 into a readable cause. It is a
 * no-op whenever the real menu is registered, so it is safe to arm this before
 * any of the bail-outs below.
 */
if ( is_admin() && ! class_exists( 'EMCP_Tools_Admin_Fallback' ) ) {
	require_once __DIR__ . '/includes/admin/class-admin-fallback.php';
	EMCP_Tools_Admin_Fallback::init();
}

$emcp_tools_is_premium_build = file_exists( __DIR__ . '/.emcp-pro' );
$emcp_tools_dir_slug         = basename( __DIR__ );
$emcp_tools_free_basename    = 'heretek-control-core/heretek-control-core.php';
$emcp_tools_premium_basename = 'emcp-pro/emcp-tools.php';

/*
 * Basenames of OTHER folders that may hold a second copy of this plugin.
 * Anything inside our own folder is dropped: that is this very plugin (loaded
 * twice it is deduplicated by require_once), and retiring it would only ever
 * switch the site's single working copy off. Only another folder holds a real
 * second copy, which would register the MCP server twice.
 */
$emcp_tools_other_folders_only = static function ( array $basenames ) use ( $emcp_tools_dir_slug ) {
	return array_values(
		array_filter(
			array_unique( $basenames ),
			static function ( $basename ) use ( $emcp_tools_dir_slug ) {
				return 0 !== strpos( (string) $basename, $emcp_tools_dir_slug . '/' );
			}
		)
	);
};

// Legacy folders / older names of this plugin — retired whichever build runs.
$emcp_tools_legacy_basenames = $emcp_tools_other_folders_only(
	array(
		'emcp-tools/emcp-tools.php',            // Upstream free build.
		'emcp-tools/heretek-control-core.php',  // This fork installed under upstream's folder name.
		'elementor-mcp/emcp-tools.php',         // v2 folder name.
		'elementor-mcp/elementor-mcp.php',      // v1 folder name.
	)
);

// The free build — retired only BY a premium build (which takes precedence).
$emcp_tools_free_basenames = $emcp_tools_other_folders_only( array( $emcp_tools_free_basename ) );

if ( function_exists( 'emcp_tools_retire_sibling' ) ) {
	foreach ( $emcp_tools_legacy_basenames as $emcp_tools_sibling ) {
		emcp_tools_retire_sibling( $emcp_tools_sibling );
	}
}

// Last-resort redeclare net: another copy already booted this request.
if ( defined( 'EMCP_TOOLS_VERSION' ) ) {
	if ( $emcp_tools_is_premium_build ) {
		foreach ( $emcp_tools_free_basenames as $emcp_tools_free_sibling ) {
			emcp_tools_retire_sibling( $emcp_tools_free_sibling );
		}
	}
	return;
}

// The free build yields to an active premium sibling (bail before defining or
// requiring anything). A persistent notice tells the admin to remove the free copy.
if ( ! $emcp_tools_is_premium_build ) {
	$emcp_tools_premium_active = in_array( $emcp_tools_premium_basename, (array) get_option( 'active_plugins', array() ), true )
		|| ( is_multisite() && array_key_exists( $emcp_tools_premium_basename, (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	if ( $emcp_tools_premium_active ) {
		add_action(
			'admin_notices',
			function () {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-warning"><p>';
				echo wp_kses(
					__( '<strong>Heretek Control Core Pro is active.</strong> The free version stays paused to avoid a conflict &mdash; you can safely <strong>deactivate and delete</strong> it. Everything is handled by Pro.', 'emcp-tools' ),
					array( 'strong' => array() )
				);
				echo '</p></div>';
			}
		);
		return;
	}
}

// The premium build retires an active free sibling.
if ( $emcp_tools_is_premium_build ) {
	foreach ( $emcp_tools_free_basenames as $emcp_tools_free_sibling ) {
		emcp_tools_retire_sibling( $emcp_tools_free_sibling );
	}
	add_action(
		'admin_notices',
		function () {
			if ( get_transient( 'emcp_tools_free_retired' ) && current_user_can( 'activate_plugins' ) ) {
				delete_transient( 'emcp_tools_free_retired' );
				echo '<div class="notice notice-info is-dismissible"><p>';
				echo esc_html__( 'Heretek Control Core: the free version was deactivated because the Pro version is active.', 'emcp-tools' );
				echo '</p></div>';
			}
		}
	);
}

/**
 * Legacy coexistence guard.
 */
require_once __DIR__ . '/includes/class-migration.php';

if ( EMCP_Tools_Migration::is_legacy_plugin_active() ) {
	if ( is_admin() ) {
		EMCP_Tools_Migration::migrate();
	}
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>';
			echo wp_kses(
				__( '<strong>Heretek Control Core:</strong> A previous version of this plugin (folder <code>elementor-mcp</code>) is still active. Heretek Control Core has replaced it &mdash; please <strong>deactivate and delete</strong> the old plugin to finish the upgrade. Your settings carry over automatically.', 'emcp-tools' ),
				array(
					'strong' => array(),
					'code'   => array(),
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

// Plugin constants.
define( 'EMCP_TOOLS_VERSION', '3.16.1' );
define( 'HERETEK_CONTROL_CORE_VERSION', EMCP_TOOLS_VERSION );
define( 'EMCP_TOOLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'HERETEK_CONTROL_CORE_DIR', EMCP_TOOLS_DIR );
define( 'EMCP_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'HERETEK_CONTROL_CORE_URL', EMCP_TOOLS_URL );
define( 'EMCP_TOOLS_BASENAME', plugin_basename( __FILE__ ) );
define( 'HERETEK_CONTROL_CORE_BASENAME', EMCP_TOOLS_BASENAME );

// Claim the WP\MCP namespace for our bundled MCP Adapter copy, at file-load.
require_once EMCP_TOOLS_DIR . 'includes/class-mcp-adapter-bootstrap.php';
EMCP_Tools_Adapter_Bootstrap::preload_bundled_namespace();

if ( ! function_exists( 'emcp_tools_fs' ) ) {
	if ( file_exists( dirname( __FILE__ ) . '/includes/vendors/fremius/start.php' ) && ! defined( 'EMCP_WPORG_BUILD' ) ) {
		function emcp_tools_fs() {
			global $emcp_tools_fs;

			if ( ! isset( $emcp_tools_fs ) ) {
				if ( ! defined( 'WP_FS__PRODUCT_30577_MULTISITE' ) ) {
					define( 'WP_FS__PRODUCT_30577_MULTISITE', true );
				}

				require_once dirname( __FILE__ ) . '/includes/vendors/fremius/start.php';

				$emcp_tools_is_premium = file_exists( dirname( __FILE__ ) . '/.emcp-pro' );

				$emcp_tools_fs = fs_dynamic_init( array(
					'id'                  => '30577',
					'slug'                => 'emcp-tools',
					'premium_slug'        => 'emcp-pro',
					'type'                => 'plugin',
					'public_key'          => 'pk_2b2a026d5c27655581635abcd4556',
					'is_premium'          => $emcp_tools_is_premium,
					'premium_suffix'      => 'Pro',
					'has_premium_version' => false,
					'has_addons'          => false,
					'has_paid_plans'      => false,
					'is_org_compliant'    => false,
					'has_affiliation'     => false,
					'menu'                => array(
						'slug'           => 'emcp-tools',
						'support'        => false,
					),
				) );
			}

			return $emcp_tools_fs;
		}

		emcp_tools_fs();
		do_action( 'emcp_tools_fs_loaded' );

		/*
		 * This build answers Freemius's licensing questions itself — the bundled
		 * SDK's is_premium()/can_use_premium_code() are hard-wired to true — so
		 * the SDK must not own the admin UX:
		 *
		 *  - `_prepare_admin_menu` swaps the render callback of our own
		 *    `emcp-tools` page for its opt-in/connect screen while the site has
		 *    never been connected (`is_activation_mode()`), hiding the plugin's
		 *    real screens;
		 *  - its post-activation redirect sends the admin to
		 *    admin.php?page=emcp-tools, a URL that exists only if this plugin
		 *    finished booting — the dead end in the activation bug report.
		 *
		 * Both exist to sell a license this fork doesn't use, so both are
		 * dropped. Nothing else depends on them: every licensing answer is
		 * already hard-coded.
		 */
		if ( defined( 'WP_FS__LOWEST_PRIORITY' ) ) {
			$emcp_tools_fs_instance = emcp_tools_fs();

			if ( is_object( $emcp_tools_fs_instance ) && method_exists( $emcp_tools_fs_instance, '_prepare_admin_menu' ) ) {
				remove_action( 'admin_menu', array( $emcp_tools_fs_instance, '_prepare_admin_menu' ), WP_FS__LOWEST_PRIORITY );
				remove_action( 'network_admin_menu', array( $emcp_tools_fs_instance, '_prepare_admin_menu' ), WP_FS__LOWEST_PRIORITY );
			}
		}

		emcp_tools_fs()->add_filter( 'redirect_on_activation', '__return_false' );

		if ( is_admin() ) {
			add_action(
				'admin_init',
				static function () {
					$updates = get_site_transient( 'update_plugins' );
					if ( is_object( $updates ) && isset( $updates->response[ EMCP_TOOLS_BASENAME ] ) ) {
						$entry = $updates->response[ EMCP_TOOLS_BASENAME ];
						if ( empty( $entry->package ) || ( isset( $entry->url ) && false !== strpos( $entry->url, 'freemius' ) ) ) {
							unset( $updates->response[ EMCP_TOOLS_BASENAME ] );
							set_site_transient( 'update_plugins', $updates );
						}
					}
				},
				5
			);
		}

		emcp_tools_fs()->add_filter(
			'is_submenu_visible',
			function ( $is_visible, $menu_id ) {
				if ( 'contact' === $menu_id || 'affiliation' === $menu_id ) {
					return false;
				}
				if ( 'pricing' === $menu_id && emcp_tools_fs()->can_use_premium_code() ) {
					return false;
				}
				return $is_visible;
			},
			10,
			2
		);
	} else {
		// Zero-dependency unlocked stub for official WordPress.org distribution.
		function emcp_tools_fs() {
			static $emcp_fs_stub = null;
			if ( null === $emcp_fs_stub ) {
				$emcp_fs_stub = new class {
					public function can_use_premium_code(): bool { return true; }
					public function is_premium(): bool { return true; }
					public function is_plan(): bool { return true; }
					public function has_features(): bool { return true; }
					public function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {}
					public function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): void {}
					public function _get_license() { return (object) array( 'secret_key' => 'unlocked' ); }
					public function get_upgrade_url(): string { return 'https://github.com/Heretek-AI/heretek-control-core'; }
				};
			}
			return $emcp_fs_stub;
		}

		do_action( 'emcp_tools_fs_loaded' );
	}
}

// Uninstall cleanup runs via Freemius's after_uninstall action or WordPress uninstaller.
require_once EMCP_TOOLS_DIR . 'includes/class-uninstaller.php';
if ( function_exists( 'emcp_tools_fs' ) && method_exists( emcp_tools_fs(), 'add_action' ) ) {
	emcp_tools_fs()->add_action( 'after_uninstall', array( 'EMCP_Tools_Uninstaller', 'run' ) );
}

if ( ! function_exists( 'emcp_tools_upgrade_url' ) ) {
	function emcp_tools_upgrade_url(): string {
		return 'https://github.com/Heretek-AI/heretek-control-core';
	}
}

// Hand off to the bootstrap (loads classes + wires hooks) once dependencies are available.
require_once EMCP_TOOLS_DIR . 'includes/class-bootstrap.php';
add_action( 'plugins_loaded', array( 'EMCP_Tools_Bootstrap', 'boot' ), 20 );
