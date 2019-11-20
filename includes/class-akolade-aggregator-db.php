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
class Akolade_Aggregator_DB {

    /**
     * Options
     *
     * @var mixed|void $options Stores options for plugin setting
     */
    public $options;
    public $akolade_aggregator_posts;
    public $akolade_aggregator_cache;
    public $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->akolade_aggregator_posts = $wpdb->prefix . 'akolade_aggregator_posts';
        $this->akolade_aggregator_cache = $wpdb->prefix . 'akolade_aggregator_cache';
        $this->options = get_option( 'akolade-aggregator' );
    }

    public function get_status_value($status)
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
                $status_value = 3;
                break;
            default;
        }

        return $status_value;
    }

    public function get_option($option)
    {
        return isset($this->options[$option]) ? $this->options[$option] : null;
    }

    public function count_ak_posts($post_name, $post_type)
    {
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM `{$this->akolade_aggregator_posts}` WHERE `post_name` = %s AND `post_type` = %s",
            $post_name,
            $post_type
        ));
    }

    public function get_ak_post($post_id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM `{$this->akolade_aggregator_posts}` WHERE `id` = %s",
            $post_id
        ));
    }

    public function insert_ak_post($row)
    {
        return $this->wpdb->insert(
            $this->akolade_aggregator_posts,
            $row
        );
    }

    public function update_ak_post_using_id($row, $id)
    {
        return $this->wpdb->update(
            $this->akolade_aggregator_posts,
            $row,
            [
                'id' => $id,
            ]
        );
    }

    public function update_ak_post($row, $post_name, $post_type)
    {
        return $this->wpdb->update(
            $this->akolade_aggregator_posts,
            $row,
            [
                'post_name' => $post_name,
                'post_type' => $post_type
            ]
        );
    }

    public function get_last_insert_id()
    {
        return $this->wpdb->insert_id;
    }

    public function get_ak_post_types()
    {
        $data = [];

        $result = $this->wpdb->get_results("SELECT DISTINCT `post_type` FROM {$this->akolade_aggregator_posts}");

        if ($result) {
            foreach ($result as $item) {
                $data[] = $item->post_type;
            }
        }

        return $data;
    }

    public function get_ak_channels()
    {
        $data = [];

        $result = $this->wpdb->get_results("SELECT DISTINCT `channel` FROM {$this->akolade_aggregator_posts}");

        if ($result) {
            foreach ($result as $item) {
                $data[] = $item->channel;
            }
        }

        return $data;
    }

    /**
     * Retrieve posts data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public function get_ak_posts( $per_page = 5, $page_number = 1 ) {

        $sql = "SELECT * FROM {$this->akolade_aggregator_posts}";

        $isWhere = false;
        if (! empty( $_REQUEST['channel'])) {
            $sql .= ($isWhere ? ' AND ' : ' WHERE ') . ' `channel` = ' . esc_sql( $_REQUEST['channel'] );
            $isWhere = true;
        }

        if (! empty( $_REQUEST['post_type'])) {
            $sql .= ($isWhere ? ' AND ' : ' WHERE ') . ' `post_type` = ' . esc_sql( $_REQUEST['post_type'] );
            $isWhere = true;
        }

        if (! empty( $_REQUEST['status'])) {
            if ($_REQUEST['status'] === 'pending') {
                $sql .= ($isWhere ? ' AND ' : ' WHERE ') . '( `status` = "1" OR `status` = "2" ) ';
            }

            if ($_REQUEST['status'] === 'completed') {
                $sql .= ($isWhere ? ' AND ' : ' WHERE ') . ' `status` = "0"';
            }
            $isWhere = true;
        }

        if (! empty( $_REQUEST['s'])) {
            $sql .= ($isWhere ? ' AND ' : ' WHERE ') . ' `post_title` LIKE "%' . esc_sql( $_REQUEST['s'] ) . '%"';
            $isWhere = true;
        }

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
            $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
        } else {
            $sql .= ' ORDER BY created_at DESC ';
        }

        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


        $result = $this->wpdb->get_results( $sql, 'ARRAY_A' );

        return $result;
    }

    /**
     * Check if post exists by slug.
     */
    function post_exists( $post_name, $post_type ) {
        $loop_posts = new WP_Query( array( 'post_type' => $post_type, 'post_status' => 'any', 'name' => $post_name, 'posts_per_page' => 1, 'fields' => 'ids' ) );
        return ( $loop_posts->have_posts() ? $loop_posts->posts[0] : false );
    }

    /**
     * Check if img exists by src.
     * @param $src
     * @param string $return 'id', 'src'
     * @return bool|false|string
     */
    function ak_get_imported_image( $src , $return = 'id') {
        $row =  $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM `{$this->akolade_aggregator_cache}` WHERE `key` = %s",
            $src
        ));

        if (! $row) {
            return false;
        }

        if ($return === 'id') {
            return $row->value;
        }

        if ($return === 'src') {
            return wp_get_attachment_url($row->value);
        }

        return false;
    }

    /**
     * Create a mapping of imported external image source with the id that is obtained after importing it.
     * This will help to prevent duplicate import
     *
     * @param $src string external image source
     * @param $mapped_id int id which is obtained after importing image
     * @return false|int
     */
    function ak_remember_imported_image($src, $mapped_id)
    {
        $row = [
            'key' => $src,
            'value' => $mapped_id
        ];

        return $this->wpdb->insert(
            $this->akolade_aggregator_cache,
            $row
        );
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public function record_count_with_filter() {
        global $wpdb;

        $sql = "SELECT count(*) FROM {$this->akolade_aggregator_posts}";

        $isWhere = false;
        if (! empty( $_REQUEST['channel'])) {
            $sql .= ($isWhere ? ' AND ' : ' WHERE ') . ' `channel` = ' . esc_sql( $_REQUEST['channel'] );
            $isWhere = true;
        }

        if (! empty( $_REQUEST['post_type'])) {
            $sql .= ($isWhere ? ' AND ' : ' WHERE ') . ' `post_type` = ' . esc_sql( $_REQUEST['post_type'] );
            $isWhere = true;
        }

        if (! empty( $_REQUEST['status'])) {
            if ($_REQUEST['status'] === 'pending') {
                $sql .= ($isWhere ? ' AND ' : ' WHERE ') . '( `status` = "1" OR `status` = "2" ) ';
            }

            if ($_REQUEST['status'] === 'completed') {
                $sql .= ($isWhere ? ' AND ' : ' WHERE ') . ' `status` = "0"';
            }
            $isWhere = true;
        }

        if (! empty( $_REQUEST['s'])) {
            $sql .= ($isWhere ? ' AND ' : ' WHERE ') . ' `post_title` LIKE "%' . esc_sql( $_REQUEST['s'] ) . '%"';
            $isWhere = true;
        }

        return $wpdb->get_var( $sql );
    }


    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public function record_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->akolade_aggregator_posts}";

        return $wpdb->get_var( $sql );
    }

    /**
     * Returns the count of pending records in the database.
     *
     * @return null|string
     */
    public function pending_record_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->akolade_aggregator_posts} WHERE `status` = '1' OR `status` = '2'";

        return $wpdb->get_var( $sql );
    }

    /**
     * Returns the count of pending records in the database.
     *
     * @return null|string
     */
    public function completed_record_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$this->akolade_aggregator_posts} WHERE `status` = '0'";

        return $wpdb->get_var( $sql );
    }

    /**
     * Delete a post record.
     *
     * @param int $id post ID
     */
    public function delete_post( $id ) {
        global $wpdb;

        $wpdb->delete(
            "{$this->akolade_aggregator_posts}",
            [ 'id' => $id ],
            [ '%d' ]
        );
    }
}