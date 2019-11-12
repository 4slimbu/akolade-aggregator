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

    public function handle()
    {
        global $wpdb;

        $data = $_POST['data'];
        $post = $data['post'];
        $post_name = $post['post_name'];
        $post_type = $post['post_type'];
        $origin = $data['post_origin'];

        $row = [
            'post_title' => $post['post_title'],
            'post_name' => $post_name,
            'origin' => $origin,
            'post_type' => $post_type,
            'data' => json_encode($data),
            'status' => 0
        ];

        $post_in_db = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}akolade_aggregator` WHERE `post_name` = %s AND `post_type` = %s",
            $post_name,
            $post_type
        ));

        if ($post_in_db) {
            $result = $wpdb->update(
                $wpdb->prefix . 'akolade_aggregator',
                $row,
                [
                    'post_name' => $post_name,
                    'post_type' => $post_type
                ]
            );
        } else {
            $result = $wpdb->insert(
                $wpdb->prefix . 'akolade_aggregator',
                $row
            );
        }

        return $result;
    }

    public function import($id)
    {
        global $wpdb;
        $post_to_import = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}akolade_aggregator` WHERE `id` = %s",
            $id
        ));

        if (! $post_to_import) {
            return;
        }

        $this->importPost();
        $this->importPostMeta();
        $this->importPostAuthor();
        $this->importPostMedia();
        $this->importPostTerms();
    }

    private function importPost()
    {
    }

    private function importPostMeta()
    {
    }

    private function importPostAuthor()
    {
    }

    private function importPostMedia()
    {
    }

    private function importPostTerms()
    {
    }
}