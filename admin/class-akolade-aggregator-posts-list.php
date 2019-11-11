<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Handles post listing functionality of the plugin
 *
 * @link       https://akolade.com.au
 * @since      1.0.0
 *
 * @package    Akolade_Aggregator
 * @subpackage Akolade_Aggregator/admin
 * @author     Akolade <developer@akolade.com.au>
 */
class Akolade_Aggregator_Posts_List {

    // customer WP_List_Table object
    public $aggregated_post;

    /**
     * Akolade_Aggregator_Posts_List constructor.
     */
    public function __construct() {
    }


    public static function set_screen( $status, $option, $value ) {
        return $value;
    }


    /**
     * Plugin settings page
     */
    public function render_posts() {
        ?>
        <div class="wrap">
            <h2>Aggregated Posts</h2>

            <div class="meta-box-sortables ui-sortable">
                <form method="post">
                    <?php
                    $this->aggregated_post->prepare_items();
                    $this->aggregated_post->display(); ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Screen options
     */
    public function screen_option() {
        $option = 'per_page';
        $args   = [
            'label'   => 'Aggregated Posts',
            'default' => 15,
            'option'  => 'aggregated_posts_per_page'
        ];

        add_screen_option( $option, $args );
        $this->aggregated_post = new Akolade_Aggregator_Post();
    }

}