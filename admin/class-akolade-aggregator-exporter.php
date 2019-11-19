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
class Akolade_Aggregator_Exporter
{

    /**
     * Options
     *
     * @var mixed|void $options Stores options for plugin setting
     */
    private $options;

    private $exportable = ['post', 'jobs', 'retail_events', 'special-content', 'retail_speakers', 'courses'];

    private $meta_keys_with_image_id_value = ['_thumbnail_id', 'menu_thumbnail'];

    private $meta_keys_with_image_src_value = [];

    private $meta_serialized_keys_with_image_value = ['post-sponser-logo'];

    private $meta_keys_with_custom_post_type_id = ['event_custom_template', 'event_sponsers'];

    private $meta_keys_with_term_id = ['event_speakers'];

    public function __construct()
    {
        $this->options = get_option('akolade-aggregator');
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
        if (!in_array($post->post_type, $this->exportable)) {
            return;
        }

        // Prevent exporting loop when post saved from this plugin
        if (get_current_screen() && get_current_screen()->parent_base === 'akolade_aggregator') {
            return;
        }

        $this->export($post);
    }

    public function export($post)
    {
        if (!$post) {
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
                $response = wp_remote_post($url, array(
                    'method' => 'POST',
                    'timeout' => 30,
                    'redirection' => 5,
//                    'blocking' => false,
                    'body' => [
                        'action' => 'akolade_aggregator_import',
                        'data' => $data,
                        'access_token' => $network['access_token']
                    ],
                    'headers' => array(
                        'Content-type' => 'application/x-www-form-urlencoded'
                    ),
                ));

                if (is_wp_error($response)) {
                    error_log($response->get_error_message());
                    echo '<pre>';
                    var_dump($response);
                    die();
                } else {
//                    echo '<pre>';
//                    var_dump( wp_remote_retrieve_body($response));
//                    die();
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
            $domain_name = strstr($regs['domain'], '.', true);
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
        $data['post_meta'] = $this->replace_meta_field_special_content(get_post_meta($post->ID));

        // Author
        $data['post_author'] = get_userdata($post->post_author);

        // Terms
        $taxonomies = get_taxonomies();
        $data['post_terms'] = [];
        if ($taxonomies) {
            foreach (get_taxonomies() as $taxonomy) {
                $terms = wp_get_post_terms($post->ID, $taxonomy);
                if ($terms) {
                    foreach ($terms as $term) {
                        $term->belongs_to_post = true;
                        array_unshift($data['post_terms'], $term);
                        $parent_term_id = $term->parent;

                        while ($parent_term_id) {
                            $current_term = get_term($parent_term_id, $taxonomy);
                            $parent_term_id = $current_term->parent;
                            array_unshift($data['post_terms'], $current_term);
                        }
                    }
                }
            }
        }

        // Images
        $attachments = get_attached_media('image', $post->ID);
        foreach ($attachments as $att_id => $attachment) {
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

        // replace [rev_slider alias="event-slider"] with placeholder
        //Import Revolution Slider
        if ( class_exists( 'RevSlider' ) ) {
            $content = preg_replace_callback(
                '/\[rev_slider(.*?)\]/',
                function ($matches) {
                    $match_array = preg_split( '/(="|" )/', str_replace('\\', '', $matches[1]));

                    $attributes = [];
                    for ($i = 0; $i < count($match_array); $i += 2) {
                        if (isset($match_array[$i]) && isset($match_array[$i + 1])) {
                            $key = str_replace('"', '', $match_array[$i]);
                            $key = str_replace(' ', '', $key);
                            $value = str_replace('"', '', $match_array[$i + 1]);
                            $value = str_replace(' ', '', $value);
                            $attributes[$key] = $value;
                        }
                    }

                    if (isset($attributes['alias'])) {
                        $alias = $attributes['alias'];
                        /**
                         * The class for exporting and importing rev slider
                         */
                        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-akolade-aggregator-rev-slider.php';
                        $slider = new Akolade_Aggregator_Rev_Slider();
                        $slider->initByAlias($alias);
                        $slider_path = $slider->exportSlider();

                        return '[rev_slider alias="' . $alias . '" download-url="' . $slider_path . '"]';
                    }

                    return $matches[0];
                },
                $content
            );
        }

        var_dump($content);
        die();

        return $content;
    }

    /**
     * @param $post_meta
     * @return mixed
     */
    private function replace_meta_field_special_content($post_meta)
    {
        foreach ($post_meta as $key => $item) {
            // Replace image id
            if (in_array($key, $this->meta_keys_with_image_id_value)) {
                $img_src = wp_get_attachment_url($item[0]);
                $post_meta[$key][0] = '%akagidstart%' . $img_src . '%akagidend%';
            }

            // Replace image src
            if (in_array($key, $this->meta_keys_with_image_src_value)) {
                $img_src = wp_get_attachment_url($item[0]);
                $post_meta[$key][0] = '%akagsrcstart%' . $img_src . '%akagsrcend%';
            }

            // Replace images inside serialized object
            if (in_array($key, $this->meta_serialized_keys_with_image_value)) {
                $item_data = unserialize(str_replace('\\', '', $post_meta[$key][0]));
                $item_data['url'] = '%akagsrcstart%' . $item_data['url'] . '%akagsrcend%';
                $item_data['id'] = '%akagidstart%' . $item_data['url'] . '%akagidend%';
                $item_data['thumbnail'] = '';

                $post_meta[$key][0] = serialize($item_data);
            }

            // Replace custom post id, with custom post info array
            if (in_array($key, $this->meta_keys_with_custom_post_type_id)) {
                $linked_post = get_post($post_meta[$key][0]);

                $this->export($linked_post);

                $post_meta[$key][0] = [
                    'post_name' => $linked_post->post_name,
                    'post_type' => $linked_post->post_type
                ];
            }

            // Replace term id with term object
            if (in_array($key, $this->meta_keys_with_term_id)) {
                $post_meta[$key][0] = get_term($post_meta[$key][0]);
            }
        }

        return $post_meta;
    }

}