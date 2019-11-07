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
     * @access protected
     * @var object $admin_settings The class for managing admin settings
     */
	protected $admin_settings;

    /**
     * Posts List
     *
     * @since 1.0.0
     * @access protected
     * @var object $posts_list The class for managing aggregated posts
     */
	protected $posts_list;

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
//        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-akolade-aggregator-posts-list.php';

        $this->admin_settings = new Akolade_Aggregator_Admin_Settings();
//        $this->posts_list = new Akolade_Aggregator_Posts_list();

    }

    public function add_menu_page()
    {
        add_menu_page(
            'Akolade Aggregator',
            'Akolade Aggregator',
            'manage_options',
            'akolade_aggregator',
            array($this, 'render_posts'),
            '',
            3
        );

        add_submenu_page('akolade_aggregator', 'Posts', 'Posts', 'manage_options', 'akolade_aggregator', array($this, 'render_posts'));
        add_submenu_page('akolade_aggregator', 'Settings', 'Settings', 'manage_options', 'akolade_aggregator' . '-settings', array($this->admin_settings, 'render_settings'));
    }

    public function admin_settings()
    {
        return $this->admin_settings;
    }

    public function render_posts()
    {
        echo 'here';
    }
}
