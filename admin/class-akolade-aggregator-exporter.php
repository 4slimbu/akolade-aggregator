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

    private $exportable = ['post', 'jobs', 'retail_events', 'special-content'];

    public function __construct()
    {
        $this->options = get_option( 'akolade-aggregator' );
    }

    public function get_option($option)
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
        $data = $this->prepare_data($post);

        // Post to network sites
        $network_sites = $this->get_option('network_sites');
        $this->postToNetworkSites($network_sites, $data);
    }

    public function postToNetworkSites($network_sites, $data)
    {
        if ($network_sites) {
            foreach ($network_sites as $network) {
                $url = trailingslashit($network['url']) . 'wp-admin/admin-ajax.php';
                $response = wp_remote_post( $url, array(
                    'method' => 'POST',
                    'timeout' => 15,
                    'redirection' => 5,
//                    'blocking' => false,
                    'body'    => [
                        'action' => 'akolade_aggregator_import',
                        'data' => $data,
                        'access_token' => $network['access_token']
                    ],
                    'headers' => array(
                        'Content-type' => 'application/x-www-form-urlencoded'
                    ),
                ) );

                if ( is_wp_error( $response ) ) {
                    error_log($response->get_error_message());
                    echo '<pre>';
                    var_dump( $response);
                    die();
                } else {
                    echo '<pre>';
                    var_dump( wp_remote_retrieve_body($response));
                    die();
                }
            }
        }
    }

    private function parse_domain_from_url($site_url)
    {
        $domain_name = '';
        $pieces = parse_url($site_url);
        $domain = isset($pieces['host']) ? $pieces['host'] : '';

        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            $domain_name = strstr( $regs['domain'], '.', true );
        }

        return $domain_name;
    }

    private function prepare_data($post)
    {
        $data = [];
        $post->post_content = $this->replace_embeded_images_with_placeholder($post->post_content);
        $post->channel = $this->parse_domain_from_url(site_url());;
        $post->canonical_url = get_permalink($post->ID);
        $data['post'] = $post;
        $data['post_meta'] = $this->replace_meta_field_image_id_with_src(get_post_meta($post->ID));

        // Author
        $data['post_author'] = get_userdata($post->post_author);

        // Terms
        $taxonomies = get_taxonomies();
        if ($taxonomies) {
            foreach (get_taxonomies() as $taxonomy) {
                $terms = wp_get_post_terms($post->ID, $taxonomy);
                echo "<pre>";
                var_dump($terms);
                die();
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
            $data['post_images'][] = $attachment;
        }

        return $data;
    }

    private function replace_embeded_images_with_placeholder($content)
    {
        // replace src="image_url" with placeholder
        $content = preg_replace_callback(
            '/src\s?=\s?"(.*?)"/',
            function ($matches) {
                // Create unique placeholder for matched image urls
                $placeholder = '%akagsrcstart%' . $matches[1] . '%akagsrcend%';

                return 'src="' . $placeholder . '"';
            },
            $content
        );

        // replace image="2983" images="2323,3232" ids="2323,2342" with placeholder
        $content = preg_replace_callback(
            '/(ids|image|images)\s?=\s?"([0-9,]*?)"/',
            function ($matches) {
                $image_ids = explode(',', $matches[2]);
                $sources = [];

                foreach ($image_ids as $image_id) {
                    // Get image source
                    $img_src = wp_get_attachment_url($image_id);

                    if ($img_src) {
                        $sources[] = '%akagidstart%' . $img_src . '%akagidend%';
                    }
                }

                // Create unique placeholder for matched image urls
                $placeholder = implode(",", $sources);

                return $matches[1] . '="' . $placeholder . '"';
            },
            $content
        );

        return $content;
    }

    private function replace_meta_field_image_id_with_src($post_meta)
    {
        $keys_with_image_id_value = [];
        $keys_with_image_src_value = [];
        $serialized_keys_with_image_value = [];

        foreach ($post_meta as $key => $item) {
            if (in_array($key, $keys_with_image_id_value)) {
                $img_src = wp_get_attachment_url($item[0]);
                $post_meta[$key][0] = '%akagidstart%' . $img_src . '%akagidend%';
            }

            if (in_array($key, $keys_with_image_src_value)) {
                $img_src = wp_get_attachment_url($item[0]);
                $post_meta[$key][0] = '%akagsrcstart%' . $img_src . '%akagsrcend%';
            }

            if (in_array($key, $serialized_keys_with_image_value)) {
//                $img_src = wp_get_attachment_url($item[0]);
//                $post_meta[$key][0] = '%akagidstart%' . $img_src . '%akagidend%';
            }
        }

        return $post_meta;
    }

}