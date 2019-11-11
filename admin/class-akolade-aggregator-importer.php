<?php

/**
 * Handles post import functionality of the plugin
 *
 * @link       https://akolade.com.au
 * @since      1.0.0
 *
 * @package    Akolade_Aggregator
 * @subpackage Akolade_Aggregator/admin
 * @author     Akolade <developer@akolade.com.au>
 */
class Akolade_Aggregator_Importer {

    /**
     * Options
     *
     * @var mixed|void $options Stores options for plugin setting
     */
    private $options;

    public function __construct()
    {
        $this->options = get_option( 'akolade-aggregator' );
    }

    public function getOption($option)
    {
        return isset($this->options[$option]) ? $this->options[$option] : null;
    }

    public function import()
    {
        global $wpdb;

        $data = $_POST['data'];
        $post = $data['post'];

        $wpdb->insert(
            $wpdb->prefix . 'akolade_aggregator',
            [
                'origin' => 'akolade',
                'post_type' => $post->post_type,
                'post_title' => $post->post_title,
                'data' => $data,
                'status' => 0
            ]
        );
    }

    public function postToNetworkSites()
    {

    }
}