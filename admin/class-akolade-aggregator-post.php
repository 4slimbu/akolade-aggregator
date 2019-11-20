<?php

class Akolade_Aggregator_Post extends Akolade_Aggregator_WP_List_Table {
    private $db;
    private $importer;

    /** Class constructor */
    public function __construct() {

        parent::__construct( [
            'singular' => __( 'Post', 'akolade-aggregator' ),
            'plural'   => __( 'Posts', 'akolade-aggregator' ),
            'ajax'     => false,
            'screen' => 'wp_screen'
        ] );

        $this->db = new Akolade_Aggregator_DB();
        $this->importer = new Akolade_Aggregator_Importer();
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
            case 'channel':
                return $item[ $column_name ];
                break;
            case 'post_type':
                return $item[ $column_name ];
                break;
            case 'import_status':
                $status_value = '';

                if ($item['status'] == $this->db->get_status_value('up-to-date')) {
                    $status_value = 'Completed';
                }

                if ($item['status'] == $this->db->get_status_value('new')) {
                    $status_value = 'Pending (new)';
                }

                if ($item['status'] == $this->db->get_status_value('update')) {
                    $status_value = 'Pending (update)';
                }

                if ($item['status'] == $this->db->get_status_value('cancelled')) {
                    $status_value = 'Cancelled';
                }

                return $status_value;
                break;
            case 'publish_status':
                $status_value = get_post_status($item['post_id']);

                if (! $status_value) {
                    $status_value = '-';
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
            '<input type="checkbox" name="bulk-action[]" value="%s" />', $item['id']
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

        $action_nonce = wp_create_nonce( 'ak_post_action_nonce' );

        if ($item['post_id']) {
            $title = '<a href="' . get_edit_post_link($item['post_id']) . '" target="_blank"><strong>' . $item['post_title'] . '</strong></a>';
        } else {
            $title = '<strong>' . $item['post_title'] . '</strong>';
        }

        $actions = [];
        if ($item['post_id']) {
            $actions['edit'] = sprintf( '<a href="%s" target="_blank">Edit</a>', get_edit_post_link($item['post_id']));
            $actions['view'] = sprintf( '<a href="%s" target="_blank">View</a>', get_permalink($item['post_id']));
        }

        if ($item['post_canonical_url']) {
            $actions['view-original'] = sprintf( '<a href="%s" target="_blank">View Original</a>', $item['post_canonical_url']);
        }

        $actions['delete'] = sprintf( '<a href="?page=%s&action=%s&post=%s&_wpnonce=%s" class="delete-action">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $action_nonce );
        $actions['save-as-draft'] = sprintf( '<a href="?page=%s&action=%s&post=%s&_wpnonce=%s">Save as draft</a>', esc_attr( $_REQUEST['page'] ), 'save-as-draft', absint( $item['id'] ), $action_nonce );
        $actions['publish'] = sprintf( '<a href="?page=%s&action=%s&post=%s&_wpnonce=%s">Publish</a>', esc_attr( $_REQUEST['page'] ), 'publish', absint( $item['id'] ), $action_nonce );

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
            'channel'    => __( 'Channel', 'akolade-aggregator' ),
            'post_type' => __( 'Post Type', 'akolade-aggregator' ),
            'import_status'    => __( 'Import Status', 'akolade-aggregator' ),
            'publish_status'    => __( 'Publish Status', 'akolade-aggregator' ),
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
            'channel' => array( 'channel', false ),
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

        $per_page     = $this->get_items_per_page( 'posts_per_page', 10 );
        $current_page = $this->get_pagenum();
        $total_items  = $this->db->record_count_with_filter();

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page
        ] );

        $this->items = $this->db->get_ak_posts( $per_page, $current_page );
    }

    public function process_bulk_action() {

        $this->process_delete_action();
        $this->process_save_as_draft_action();
        $this->process_publish_action();
    }

    protected function get_views() {
        $views = array();
        $current = ( !empty($_REQUEST['status']) ? $_REQUEST['status'] : 'all');

        //All
        $record_count = $this->db->record_count();
        $class = ($current == 'all' ? ' class="current"' :'');
        $all_url = remove_query_arg('status');
        $views['all'] = "<a href='{$all_url }' {$class} >All (" . $record_count . ")</a>";

        //Pending
        $pending_count = $this->db->pending_record_count();
        $pending_url = add_query_arg('status','pending');
        $class = ($current == 'pending' ? ' class="current"' :'');
        $views['pending'] = "<a href='{$pending_url}' {$class} >Pending (" . $pending_count . ")</a>";

        //Completed
        $completed_count = $this->db->completed_record_count();
        $completed_url = add_query_arg('status','completed');
        $class = ($current == 'completed' ? ' class="current"' :'');
        $views['completed'] = "<a href='{$completed_url}' {$class} >Completed (" . $completed_count . ")</a>";

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
        $post_types = $this->db->get_ak_post_types();
        $channels = $this->db->get_ak_channels();
        ?>
        <label for="channel-selector" class="screen-reader-text">Select Channel</label>
        <select name="channel" id="channel-selector" value="<?php echo isset($_GET['channel']) ? $_GET['channel'] : '-1'; ?>">
            <option value="" <?php echo isset($_GET['channel']) ? $_GET['channel'] : ''; ?>>All Channels</option>
            <?php foreach ($channels as $channel): ?>
                <option value="<?php echo $channel; ?>" <?php echo isset($_GET['channel']) && $_GET['channel'] === $channel ? 'selected' : '' ?>><?php echo $channel; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="post-type-selector" class="screen-reader-text">Select Post Type</label>
        <select name="post_type" id="post-type-selector" value="<?php echo isset($_GET['post_type']) ? $_GET['post_type'] : ''; ?>">
            <option value="">All Post Types</option>
            <?php foreach ($post_types as $post_type): ?>
                <option value="<?php echo $post_type; ?>" <?php echo isset($_GET['post_type']) && $_GET['post_type'] === $post_type ? 'selected' : '' ?>><?php echo $post_type; ?></option>
            <?php endforeach; ?>
        </select>
<!--        <input type="submit" class="button" value="Filter">-->
        <?php
    }

    private function process_delete_action()
    {
        //Detect when a bulk action is being triggered...
        if ( 'delete' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'ak_post_action_nonce' ) ) {
                die( 'Go get a life script kiddies' );
            }
            if ( ! wp_verify_nonce( $nonce, 'ak_post_action_nonce' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                $this->db->delete_post( absint( $_GET['post'] ) );
            }

        }

        // If the delete bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
            || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
        ) {
            $delete_ids = esc_sql( $_POST['bulk-action'] );

            // loop over the array of record IDs and delete them
            foreach ( $delete_ids as $id ) {
                $this->db->delete_post( $id );

            }
        }
    }

    private function process_save_as_draft_action()
    {
        //Detect when a bulk action is being triggered...
        if ( 'save-as-draft' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'ak_post_action_nonce' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                $this->importer->import( absint( $_GET['post'] ) , 'draft');
            }

        }

        // If the save as draft bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-save-as-draft' )
            || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-save-as-draft' )
        ) {
            $ak_post_ids = esc_sql( $_POST['bulk-action'] );

            // loop over the array of record IDs and delete them
            foreach ( $ak_post_ids as $id ) {
                $this->importer->import( absint( $id ) , 'draft');
            }
        }
    }

    private function process_publish_action()
    {
        //Detect when a bulk action is being triggered...
        if ( 'publish' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, 'ak_post_action_nonce' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                $this->importer->import( absint( $_GET['post'] ) , 'publish');
            }

        }

        // If the publish bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-publish' )
            || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-publish' )
        ) {

            $ak_post_ids = esc_sql( $_POST['bulk-action'] );

            // loop over the array of record IDs and delete them
            foreach ( $ak_post_ids as $id ) {
                $this->importer->import( absint( $id ) , 'publish');
            }
        }
    }

}