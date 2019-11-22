<?php

/**
 * Class Akolade_Aggregator_Scheduler
 */
class Akolade_Aggregator_Scheduler {
    const WP_CRON_HOOK = 'akolade_aggregator_scheduler_run_queue';

    const WP_CRON_SCHEDULE = 'every_minute';

    private $importer;

    /**
     * Akolade_Aggregator_Scheduler constructor.
     */
    public function __construct() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-akolade-aggregator-importer.php';
        $this->importer = new Akolade_Aggregator_Importer();
        $this->init();
    }

    /**
     * @codeCoverageIgnore
     */
    public function init() {
        add_filter( 'cron_schedules', array( $this, 'add_wp_cron_schedule' ) );

        if ( !wp_next_scheduled(self::WP_CRON_HOOK) ) {
            $schedule = apply_filters( 'action_scheduler_run_schedule', self::WP_CRON_SCHEDULE );
            wp_schedule_event( time(), $schedule, self::WP_CRON_HOOK );
        }

        add_action( self::WP_CRON_HOOK, array( $this, 'run' ) );
    }

    /**
     * Run scheduled tasks
     */
    public function run() {
        $this->importer->import_posts_in_batch(1);
    }

    public function add_wp_cron_schedule( $schedules ) {
        $schedules['every_minute'] = array(
            'interval' => 60, // in seconds
            'display'  => __( 'Every minute' ),
        );

        return $schedules;
    }

    public function destroy()
    {
        $timestamp = wp_next_scheduled( self::WP_CRON_HOOK );
        wp_unschedule_event( $timestamp, self::WP_CRON_HOOK );
    }
}
