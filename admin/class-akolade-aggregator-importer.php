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

    public function getStatusValue($status)
    {
        // Check activator file for table schema for more information
        // up-to-date (0), new (1), update (2)
        $status_value = 0;

        switch ($status) {
            case 'up-to-date':
                $status_value = 0;
                break;
            case 'new':
                $status_value = 1;
                break;
            case 'update':
                $status_value = 2;
                break;
            case 'cancelled':
                $status_value = 2;
                break;
            default;
        }

        return $status_value;
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
            'status' => $this->getStatusValue('new'),
        ];

        $post_in_db = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}akolade_aggregator` WHERE `post_name` = %s AND `post_type` = %s",
            $post_name,
            $post_type
        ));

        if ($post_in_db) {
            $row['status'] = $this->getStatusValue('update');
            $result = $wpdb->update(
                $wpdb->prefix . 'akolade_aggregator',
                $row,
                [
                    'post_name' => $post_name,
                    'post_type' => $post_type
                ]
            );

            $last_id = $post_in_db->id;
        } else {
            $result = $wpdb->insert(
                $wpdb->prefix . 'akolade_aggregator',
                $row
            );

            $last_id = $wpdb->insert_id;
        }

        $this->import($last_id);

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

        $this->importPost($post_to_import);
//        $this->importPostMeta();
//        $this->importPostAuthor();
//        $this->importPostMedia();
//        $this->importPostTerms();
    }

    private function importPost($post, $status = 'draft')
    {
        $post = (array)$post;
        unset($post['ID']);
        $post['post_status'] = $status;

        wp_insert_post($post);
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