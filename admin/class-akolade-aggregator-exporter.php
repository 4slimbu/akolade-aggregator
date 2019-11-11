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

    public function __construct()
    {
        $this->options = get_option( 'akolade-aggregator' );
    }

    public function getOption($option)
    {
        return isset($this->options[$option]) ? $this->options[$option] : null;
    }

    public function export($post_id, $post, $update)
    {
        $data = [];

        $data['post'] = $post;
        $data['post_meta'] = get_post_meta($post->ID);

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

        $network_sites = $this->getOption('network_sites');
        if ($network_sites) {
            foreach ($network_sites as $network) {
                $url = trailingslashit($network['url']) . '/wp-admin/admin-ajax.php';
                $response = wp_remote_post( $url, array(
                    'body'    => [
                        'action' => 'akolade_aggregator_import',
                        'data' => $data
                    ],
                    'headers' => array(
                    ),
                ) );
            }
        }
    }

    public function postToNetworkSites()
    {

    }
}