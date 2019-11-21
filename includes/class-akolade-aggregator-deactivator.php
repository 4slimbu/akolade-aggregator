<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://akolade.com.au
 * @since      1.0.0
 *
 * @package    Akolade_Aggregator
 * @subpackage Akolade_Aggregator/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Akolade_Aggregator
 * @subpackage Akolade_Aggregator/includes
 * @author     Akolade <developer@akolade.com.au>
 */
class Akolade_Aggregator_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
        self::destroy_akolade_aggregator_scheduler();
	}

    private static function destroy_akolade_aggregator_scheduler()
    {
        /**
         * The class responsible for managing schedules
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-akolade-aggregator-scheduler.php';

        $scheduler = new Akolade_Aggregator_Scheduler();
        $scheduler->destroy();
    }

}
