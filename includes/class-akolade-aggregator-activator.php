<?php

/**
 * Fired during plugin activation
 *
 * @link       https://akolade.com.au
 * @since      1.0.0
 *
 * @package    Akolade_Aggregator
 * @subpackage Akolade_Aggregator/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Akolade_Aggregator
 * @subpackage Akolade_Aggregator/includes
 * @author     Akolade <developer@akolade.com.au>
 */
class Akolade_Aggregator_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        self::create_plugin_database_table();
	}

    public static function create_plugin_database_table()
    {
        global $table_prefix, $wpdb;

        $tblname = 'akolade_aggregator';
        $wp_track_table = $table_prefix . $tblname;

        #Check to see if the table exists already, if not, then create it
        if($wpdb->get_var( "show tables like '$wp_track_table'" ) != $wp_track_table) {
            $sql = "CREATE TABLE `". $wp_track_table . "` ( ";
            $sql .= "  `id`  int(11)   NOT NULL auto_increment, ";
            $sql .= "  `post_id`  varchar(128)   NULL, ";
            $sql .= "  `post_canonical_url`  varchar(255)  NULL, ";
            $sql .= "  `post_title`  varchar(128)   NOT NULL, ";
            $sql .= "  `post_name`  varchar(128)   NOT NULL, ";
            $sql .= "  `channel`  varchar(128)   NOT NULL, ";
            $sql .= "  `post_type`  varchar(128)   NOT NULL, ";
            $sql .= "  `data`  mediumtext   NOT NULL, ";
            $sql .= "  `status`  tinyint(4)   NOT NULL, "; // up-to-date (0), new (1), update (2)
            $sql .= "  `created_at`  timestamp   NOT NULL, ";
            $sql .= "  PRIMARY KEY (`id`) ";
            $sql .= "); ";

            require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
            dbDelta($sql);
        }

    }

}
