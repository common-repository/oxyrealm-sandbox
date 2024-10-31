<?php

namespace Oxyrealm\Loader;

use Automatic_Upgrader_Skin;
use Composer\Semver\Comparator;
use InvalidArgumentException;
use Plugin_Upgrader;
use WP_Error;

class Aether {
	/**
	 * Slug for the Aether plugin.
	 *
	 * @var string
	 */
	protected $aether_plugin_path = 'aether/aether.php';

	protected $aether_latest_version_download_url = 'https://downloads.wordpress.org/plugin/aether.latest-stable.zip';

	protected $notices = [];

	protected $module_id;

	protected $upgrader = null;

	public function __construct( string $module_id ) {
		$this->module_id = $module_id;

		include_once ABSPATH . 'wp-includes/pluggable.php';
		include_once ABSPATH . 'wp-admin/includes/misc.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$skin        	= new Automatic_Upgrader_Skin();
		$this->upgrader = new Plugin_Upgrader( $skin );
	}

	/**
	 * Deactivate the plugin
	 */
	public function deactivate_module( string $file ): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		deactivate_plugins( plugin_basename( $file ) );
	}

	/**
	 * Check if the Aether plugin is activated
	 */
	public function is_aether_activated(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		return is_plugin_active($this->aether_plugin_path) && file_exists( WP_PLUGIN_DIR . '/' .  $this->aether_plugin_path);
	}

	/**
	 * Check if the Aether plugin is installed
	 */
	public function is_aether_installed(): bool {
		if ( $this->is_aether_activated() ) {
			return true;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();

		return array_key_exists( $this->aether_plugin_path, $installed_plugins );
	}

	/**
	 * Install the Aether plugin from the wordpress.org repository.
	 *
	 * @return bool|WP_Error
	 */
	public function install_aether() {
		return $this->upgrader->install( $this->aether_latest_version_download_url );
	}

	public function upgrade_aether() {
		return $this->upgrader->upgrade( $this->aether_plugin_path );
	}

	/**
	 * Activate the Aether plugin.
	 *
	 * @return null|WP_Error
	 */
	public function activate_aether() {
		return activate_plugin( $this->aether_plugin_path );
	}

	/**
	 * Check if the minimum aether plugin version is installed
	 *
	 * @throws InvalidArgumentException
	 */
	public function minimum_aether_version( string $aether_minimum_version ): bool {
		return Comparator::greaterThanOrEqualTo( AETHER_VERSION, $aether_minimum_version );
	}

	/**
	 * Check if the Aether plugin is being deactivated.
	 */
	public function is_aether_being_deactivated(): bool {
		if ( ! is_admin() ) {
			return false;
		}

		$action = isset( $_REQUEST['action'] ) && $_REQUEST['action'] != - 1 ? $_REQUEST['action'] : '';
		if ( ! $action ) {
			$action = isset( $_REQUEST['action2'] ) && $_REQUEST['action2'] != - 1 ? $_REQUEST['action2'] : '';
		}
		$plugin  = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
		$checked = isset( $_POST['checked'] ) && is_array( $_POST['checked'] ) ? $_POST['checked'] : [];

		$deactivate          = 'deactivate';
		$deactivate_selected = 'deactivate-selected';
		$actions             = [ $deactivate, $deactivate_selected ];

		if ( ! in_array( $action, $actions, true ) ) {
			return false;
		}

		if ( $action === $deactivate && $plugin !== $this->aether_plugin_path ) {
			return false;
		}

		if ( $action === $deactivate_selected && ! in_array( $this->aether_plugin_path, $checked, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the Aether plugin is being updated.
	 */
	public function is_aether_being_updated(): bool {
		$action  = isset( $_POST['action'] ) && $_POST['action'] != - 1 ? $_POST['action'] : '';
		$plugins = isset( $_POST['plugin'] ) ? (array) $_POST['plugin'] : [];
		if ( empty( $plugins ) ) {
			$plugins = isset( $_POST['plugins'] ) ? (array) $_POST['plugins'] : [];
		}

		$update_plugin   = 'update-plugin';
		$update_selected = 'update-selected';
		$actions         = [ $update_plugin, $update_selected ];

		if ( ! in_array( $action, $actions, true ) ) {
			return false;
		}

		return in_array( $this->aether_plugin_path, $plugins, true );
	}

	/**
	 * Check if the plugin requirement is satisfied
	 *
	 * @throws InvalidArgumentException
	 */
	public function are_requirements_met( string $file, string $aether_minimum_version ): bool {
		if ( $this->is_aether_being_deactivated() ) {
			$this->notices[] = [
				'level'   => 'error',
				'message' => '<a href="https://wordpress.org/plugins/aether" target="_blank">Aether plugin</a> is required to run this plugins, Both plugins are now disabled.'
			];
		} elseif ( $this->is_aether_being_updated() ) {
			return false;
		} else {
			if ( ! $this->is_aether_installed() ) {
				if ( ! $this->install_aether() ) {
					$this->notices[] = [
						'level'   => 'error',
						'message' => '<a href="https://wordpress.org/plugins/aether" target="_blank">Aether plugin</a> is required to run this plugin, but it could not be installed automatically or being updated. Please install and activate the Aether plugin first or wait for plugin update process finished.'
					];
					add_action( 'admin_notices', [ $this, 'admin_notices' ] );
				}
				return false;
			}

			if ( ! $this->is_aether_activated() ) {
				if ( ! $this->activate_aether() ) {
					$this->notices[] = [
						'level'   => 'error',
						'message' => '<a href="https://wordpress.org/plugins/aether" target="_blank">Aether plugin</a> is required to run this plugin, but it could not be activated automatically or being updated. Please install and activate the Aether plugin first or wait for plugin update process finished.'
					];
					add_action( 'admin_notices', [ $this, 'admin_notices' ] );
				}
				return false;
			}

			if ( ! class_exists('Composer\Semver\Comparator') || ! $this->minimum_aether_version( $aether_minimum_version ) ) {
				if ( ! $this->upgrade_aether() ) {
					$this->notices[] = [
						'level'   => 'error',
						'message' => 'The latest <a href="https://wordpress.org/plugins/aether" target="_blank">Aether plugin</a> version (v' . $aether_minimum_version . ') is required to run this plugin, version ' . AETHER_VERSION . ' is installed. Please update the Aether plugin to the latest version.'
					];
					$this->notices[] = [
						'level'   => 'info',
						'message' => 'Automatic plugin upgrade was triggered. Please wait a minute and try again.'
					];
					add_action( 'admin_notices', [ $this, 'admin_notices' ] );
				} 
				return false;
			}
		}

		if ( empty( $this->notices ) ) {
			return true;
		}

		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		// $this->deactivate_module( $file );
		return false;
	}

	public function admin_notices() {
		foreach ( $this->notices as $notice ) {
			echo sprintf(
				'<div class="notice notice-%s is-dismissible"><p><b>%s</b>: %s</p></div>',
				$notice['level'],
				str_replace( 'aether_m_', '', $this->module_id ),
				$notice['message']
			);
		}
	}
}
