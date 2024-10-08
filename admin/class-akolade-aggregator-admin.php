<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://akolade.com.au
 * @since      1.0.0
 *
 * @package    Akolade_Aggregator
 * @subpackage Akolade_Aggregator/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Akolade_Aggregator
 * @subpackage Akolade_Aggregator/admin
 * @author     Akolade <developer@akolade.com.au>
 */
class Akolade_Aggregator_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

    /**
     * Admin Setting
     *
     * @since 1.0.0
     * @access public
     * @var object $admin_settings The class for managing admin settings
     */
	public $admin_settings;

    /**
     * Posts List
     *
     * @since 1.0.0
     * @access public
     * @var object $posts_list The class for managing aggregated posts
     */
	public $posts_list;

    /**
     * Exporter Instance
     *
     * @since 1.0.0
     * @access public
     * @var object $exporter The class for exporting posts
     */
    public $exporter;

    /**
     * Importer Class Instance
     *
     * @since 1.0.0
     * @access public
     * @var object $exporter The class for importing posts
     */
    public $importer;

    /**
     * Scheduler Class Instance
     *
     * @since 1.0.0
     * @access public
     * @var object $scheduler The class for scheduling events
     */
    public $scheduler;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->load_admin_dependencies();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Akolade_Aggregator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Akolade_Aggregator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/akolade-aggregator-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Akolade_Aggregator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Akolade_Aggregator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/akolade-aggregator-admin.js', array( 'jquery' ), $this->version, false );

	}

    private function load_admin_dependencies() {

        /**
         * The class responsible for managing admin settings
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-akolade-aggregator-admin-settings.php';

        /**
         * The class responsible managing aggregated posts
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-akolade-aggregator-posts-list.php';

        /**
         * The class representing aggregated post
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-akolade-aggregator-post.php';

        /**
         * The class responsible for handling import
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-akolade-aggregator-importer.php';

        /**
         * The class responsible for handling export
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-akolade-aggregator-exporter.php';

        /**
         * The class responsible for handling export
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-akolade-aggregator-scheduler.php';

        $this->admin_settings = new Akolade_Aggregator_Admin_Settings();
        $this->posts_list = new Akolade_Aggregator_Posts_List();
        $this->exporter = new Akolade_Aggregator_Exporter();
        $this->importer = new Akolade_Aggregator_Importer();
        $this->scheduler = new Akolade_Aggregator_Scheduler();
    }

    public function add_menu_page()
    {
        $hook = add_menu_page(
            'Akolade Aggregator',
            'Akolade Aggregator',
            'manage_options',
            'akolade_aggregator',
            array($this->posts_list(), 'render_posts'),
            '',
            3
        );
        add_action( "load-$hook", [ $this->posts_list(), 'screen_option' ] );

        add_submenu_page(
            'akolade_aggregator',
            'Posts', 'Posts',
            'manage_options',
            'akolade_aggregator',
            array($this->posts_list(), 'render_posts')
        );

        add_submenu_page(
            'akolade_aggregator',
            'Settings',
            'Settings',
            'manage_options',
            'akolade_aggregator' . '-settings',
            array($this->admin_settings(), 'render_settings')
        );
    }

    public function admin_settings()
    {
        return $this->admin_settings;
    }

    public function posts_list()
    {
        return $this->posts_list;
    }

    public function exporter()
    {
        return $this->exporter;
    }

    public function importer()
    {
        return $this->importer;
    }

    /**
     * Allow script tags on post content
     *
     * @param $allowed_tags
     * @return mixed
     */
    public function allow_script_tag_on_post($allowed_tags)
    {
        $allowed_tags['script'] = array(
            'type' => true,
            'src' => true,
            'height' => true,
            'width' => true,
        );

        return $allowed_tags;
    }
}
