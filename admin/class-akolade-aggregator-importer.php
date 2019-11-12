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

    private $post_fields = [
        'post_content' => '',
        'post_title' => '',
        'post_excerpt' => '',
        'post_status' => 'draft',
        'post_name' => ''
    ];

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

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}akolade_aggregator` WHERE `post_name` = %s AND `post_type` = %s",
            $post_name,
            $post_type
        ));

        if ($post_id) {
            $row['status'] = $this->getStatusValue('update');
            $result = $wpdb->update(
                $wpdb->prefix . 'akolade_aggregator',
                $row,
                [
                    'post_name' => $post_name,
                    'post_type' => $post_type
                ]
            );
            $last_id = $post_id;
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
        $import_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}akolade_aggregator` WHERE `id` = %s",
            $id
        ));

        if (! $import_data) {
            return;
        }

        $this->importPost($import_data);
//        $this->importPostMeta();
//        $this->importPostAuthor();
//        $this->importPostMedia();
//        $this->importPostTerms();
    }

    private function importPost($import_data)
    {
        $post_id = $import_data->post_id;
        $post_name = $import_data->post_name;
        $post_origin = $import_data->origin;
        $post_type = $import_data->post_type;
        $data = json_decode($import_data->data);
        $post = (array)$data->post;
        $post_origin = $data->post_origin;
        $post_meta = $data->post_meta;
        $post_author = $data->post_author;
        $post_terms = $data->post_terms;

//        $post['post_status'] = $this->getOption('auto_publish') ? 'published' : 'draft';
        $post['post_status'] = 'published';
        $fillable_post_data = array_intersect_key($post, $this->post_fields);

        wp_insert_post($fillable_post_data);
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