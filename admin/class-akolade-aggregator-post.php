<?php

class Akolade_Aggregator_Post extends Akolade_Aggregator_WP_List_Table {

    /** Class constructor */
    public function __construct() {

        parent::__construct( [
            'singular' => __( 'Post', 'akolade-aggregator' ),
            'plural'   => __( 'Posts', 'akolade-aggregator' ),
            'ajax'     => false,
            'screen' => 'wp_screen'
        ] );

    }


    /**
     * Retrieve posts data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_posts( $per_page = 5, $page_number = 1 ) {

        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}akolade_aggregator";

        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
            $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
        }

        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


        $result = $wpdb->get_results( $sql, 'ARRAY_A' );

        return $result;
    }


    /**
     * Delete a post record.
     *
     * @param int $id post ID
     */
    public static function delete_post( $id ) {
        global $wpdb;

        $wpdb->delete(
            "{$wpdb->prefix}akolade_aggregator",
            [ 'id' => $id ],
            [ '%d' ]
        );
    }


    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}akolade_aggregator";

        return $wpdb->get_var( $sql );
    }


    /** Text displayed when no post data is available */
    public function no_items() {
        _e( 'No posts avaliable.', 'akolade-aggregator' );
    }


    /**
     * Render a column when no column specific method exist.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'post_title':
                return $item[ $column_name ];
                break;
            case 'origin':
                return $item[ $column_name ];
                break;
            case 'post_type':
                return $item[ $column_name ];
                break;
            case 'status':
                $status_value = '';

                if ((int)$item[ $column_name ] === 1) {
                    $status_value = 'New';
                }

                if ((int) $item[ $column_name ] === 2) {
                    $status_value = 'Update';
                }

                return $status_value;
                break;
            case 'created_at':
                return $item[ $column_name ];
                break;
            default:
                return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
        );
    }


    /**
     * Method for post_title column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_post_title( $item ) {

        $delete_nonce = wp_create_nonce( 'ak_post_action_nonce' );

        $title = '<strong>' . $item['post_title'] . '</strong>';

        $actions = [
            'delete' => sprintf( '<a href="?page=%s&action=%s&post=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce ),
            'save-as-draft' => sprintf( '<a href="?page=%s&action=%s&post=%s&_wpnonce=%s">Save as draft</a>', esc_attr( $_REQUEST['page'] ), 'save-as-draft', absint( $item['id'] ), $delete_nonce ),
            'publish' => sprintf( '<a href="?page=%s&action=%s&post=%s&_wpnonce=%s">Publish</a>', esc_attr( $_REQUEST['page'] ), 'publish', absint( $item['id'] ), $delete_nonce )
        ];

        return $title . $this->row_actions( $actions );
    }


    /**
     *  Associative array of columns
     *
     * @return array
     */
    function get_columns() {
        $columns = [
            'cb'      => '<input type="checkbox" />',
            'post_title'    => __( 'Title', 'akolade-aggregator' ),
            'origin'    => __( 'Origin', 'akolade-aggregator' ),
            'post_type' => __( 'Post Type', 'akolade-aggregator' ),
            'status'    => __( 'Status', 'akolade-aggregator' ),
            'created_at'    => __( 'Date', 'akolade-aggregator' ),
        ];

        return $columns;
    }


    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'post_title' => array( 'post_title', true ),
            'origin' => array( 'origin', false ),
            'post_type' => array( 'post_type', false )
        );

        return $sortable_columns;
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions() {
        $actions = [
            'bulk-delete' => 'Delete',
            'bulk-save-as-draft' => 'Save as Draft',
            'bulk-publish' => 'Publish'
        ];

        return $actions;
    }


    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items() {

        $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        $this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'posts_per_page', 5 );
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        $this->set_pagination_args( [
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page //WE have to determine how many items to show on a page
        ] );

        $this->items = self::get_posts( $per_page, $current_page );
    }

    public function process_bulk_action() {

        //Detect when a bulk action is being triggered...
        if ( 'delete' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'ak_post_action_nonce' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                self::delete_post( absint( $_GET['post'] ) );

                // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
                // add_query_arg() return the current url
                wp_redirect( esc_url_raw(add_query_arg()) );
                exit;
            }

        }

        // If the delete bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
            || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
        ) {

            $delete_ids = esc_sql( $_POST['bulk-delete'] );

            // loop over the array of record IDs and delete them
            foreach ( $delete_ids as $id ) {
                self::delete_post( $id );

            }

            // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
            // add_query_arg() return the current url
            wp_redirect( esc_url_raw(add_query_arg()) );
            exit;
        }
    }

    protected function get_views() {
        $views = array();
        $current = ( !empty($_REQUEST['status']) ? $_REQUEST['status'] : 'all');

        //All
        $class = ($current == 'all' ? ' class="current"' :'');
        $all_url = remove_query_arg('status');
        $views['all'] = "<a href='{$all_url }' {$class} >All</a>";

        //Pending
        $foo_url = add_query_arg('status','pending');
        $class = ($current == 'pending' ? ' class="current"' :'');
        $views['pending'] = "<a href='{$foo_url}' {$class} >Pending</a>";

        //Completed
        $bar_url = add_query_arg('status','completed');
        $class = ($current == 'completed' ? ' class="current"' :'');
        $views['completed'] = "<a href='{$bar_url}' {$class} >Completed</a>";

        return $views;
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination
     *
     * @since 3.1.0
     *
     * @param string $which
     */
    protected function extra_tablenav( $which ) {
        ?>
        <?php if ( $this->has_items() ) : ?>
            <div class="alignleft actions bulkactions">
                <?php $this->post_filter( $which ); ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Display the post filter dropdown.
     *
     * @since 3.1.0
     *
     * @param string $which The location of the post filter: 'top' or 'bottom'.
     *                      This is designated as optional for backward compatibility.
     */
    protected function post_filter( $which = '' ) {
        if ( is_null( $this->_actions ) ) {
            $this->_actions = $this->get_post_filter_actions();
            $two = '';
        } else {
            $two = '2';
        }

        if ( empty( $this->_actions ) ) {
            return;
        }

        echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action' ) . '</label>';
        echo '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";
        echo '<option value="-1">' . __( 'All Channels' ) . "</option>\n";

        foreach ( $this->_actions as $name => $title ) {
            $class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

            echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
        }

        echo "</select>\n";

        submit_button( __( 'Filter' ), 'action', '', false, array( 'id' => "doaction$two" ) );
        echo "\n";
    }

}