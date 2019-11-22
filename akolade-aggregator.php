<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://akolade.com.au
 * @since             1.0.0
 * @package           Akolade_Aggregator
 *
 * @wordpress-plugin
 * Plugin Name:       Akolade Aggregator
 * Plugin URI:        https://akolade.com.au
 * Description:       Aggregates posts from akolade channel websites.
 * Version:           1.0.0
 * Author:            Akolade
 * Author URI:        https://akolade.com.au
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       akolade-aggregator
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'AKOLADE_AGGREGATOR_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-akolade-aggregator-activator.php
 */
function activate_akolade_aggregator() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-akolade-aggregator-activator.php';
	Akolade_Aggregator_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-akolade-aggregator-deactivator.php
 */
function deactivate_akolade_aggregator() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-akolade-aggregator-deactivator.php';
	Akolade_Aggregator_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_akolade_aggregator' );
register_deactivation_hook( __FILE__, 'deactivate_akolade_aggregator' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-akolade-aggregator.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_akolade_aggregator() {

	$plugin = new Akolade_Aggregator();
	$plugin->run();

}

add_action('plugins_loaded', 'run_akolade_aggregator');
