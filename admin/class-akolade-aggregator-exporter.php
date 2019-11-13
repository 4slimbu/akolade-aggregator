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
class Akolade_Aggregator_Exporter {

    /**
     * Options
     *
     * @var mixed|void $options Stores options for plugin setting
     */
    private $options;

    private $exportable = ['post', 'jobs', 'retail_events'];

    public function __construct()
    {
        $this->options = get_option( 'akolade-aggregator' );
    }

    public function getOption($option)
    {
        return isset($this->options[$option]) ? $this->options[$option] : null;
    }

    public function handle($post_id, $post, $update)
    {
        // Check to see if we are autosaving
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;

        // Check if new unpublished post
        if (!$post->post_name) {
            return;
        }

        // Check if exportable
        if (! in_array($post->post_type, $this->exportable)) {
            return;
        }

        // data to post
        $data = [];
        $data['post'] = $post;
        $data['post_origin'] = $this->getOption('origin');
        $data['post_meta'] = get_post_meta($post->ID);

        // Author
        $data['post_author'] = get_userdata($post->post_author);

        // Terms
        $taxonomies = get_taxonomies();
        if ($taxonomies) {
            foreach (get_taxonomies() as $taxonomy) {
                $terms = wp_get_post_terms($post->ID, $taxonomy);
                if ($terms) {
                    foreach ($terms as $term) {
                        $data['post_terms'][] = $term;
                    }
                }
            }
        }

        // Images
        $attachments= get_attached_media( 'image', $post->ID );
        foreach($attachments as $att_id => $attachment) {
            $data['post_images'] = wp_get_attachment_url($attachment->ID);
        }

        // Post to network sites
        $network_sites = $this->getOption('network_sites');
        $this->postToNetworkSites($network_sites, $data);
    }

    public function postToNetworkSites($network_sites, $data)
    {
        if ($network_sites) {
            foreach ($network_sites as $network) {
                $url = trailingslashit($network['url']) . 'wp-admin/admin-ajax.php';
                $response = wp_remote_post( $url, array(
                    'method' => 'POST',
                    'timeout' => 5,
                    'redirection' => 5,
                    'blocking' => false,
                    'body'    => [
                        'action' => 'akolade_aggregator_import',
                        'data' => $data,
                    ],
                    'headers' => array(
                        'Content-type' => 'application/x-www-form-urlencoded'
                    ),
                ) );

                if ( is_wp_error( $response ) ) {
                    error_log($response->get_error_message());
                } else {
//                    var_dump($response, wp_remote_retrieve_body($response));
//                    die();
                }
            }
        }
    }
}