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

    private $post_fields = [
        'post_name',
        'post_title',
        'post_excerpt',
        'post_content',
        'post_status',
        'post_type'
    ];

    private $author_fields = [
        'user_pass', 'user_login', 'user_nicename', 'user_email', 'display_name', 'nickname',
        'first_name', 'last_name', 'description', 'role'
    ];

    private $always_publish = ['special-content'];

    private $meta_keys_with_image_id_value = ['_thumbnail_id', 'menu_thumbnail'];

    private $meta_keys_with_image_src_value = [];

    private $meta_serialized_keys_with_image_value = ['post-sponser-logo'];

    private $meta_keys_with_custom_post_type_id = ['event_custom_template', 'event_sponsers'];

    private $meta_keys_with_term_id = ['event_speakers'];

    private $db;

    public function __construct()
    {
        $this->db = new Akolade_Aggregator_DB();
    }

    /**
     * @return mixed
     */
    public function handle()
    {
        // TODO: this way of verifying access_token won't work for sites without SSL. So use signature or encryption for verification
        //verify access_token
        if (! $this->verify_access_token()) {
            die('Invalid Access Token');
        }

        $data = $_POST['data'];
        $post = $data['post'];
        $post_name = $post['post_name'];
        $post_type = $post['post_type'];

        $row = [
            'post_title' => $post['post_title'],
            'post_canonical_url' => $post['canonical_url'],
            'post_name' => $post_name,
            'channel' => $post['channel'],
            'post_type' => $post_type,
            'status' => $this->db->get_status_value('new'),
            'created_at' => current_time('mysql'),
            'data' => json_encode($data),
        ];

        $post_id = $this->db->count_ak_posts($post_name, $post_type);

        if ($post_id) {
            $row['status'] = $this->db->get_status_value('update');
            $result = $this->db->update_ak_post($row, $post_name, $post_type);
            $last_id = $post_id;
        } else {
            $result = $this->db->insert_ak_post($row);
            $last_id = $this->db->get_last_insert_id();
        }

        // if auto_publish is set to "Import and save as draft", create draft of imported post
        if ($this->db->get_option('auto_publish') === '1') {
            $this->import($last_id, 'draft');
        }

        // if auto_publish is set to "Import and Publish" Publish imported post
        // or if always publish is set for current post type, publish imported post
        if (
            $this->db->get_option('auto_publish') === '2' ||
            in_array($post_type, $this->always_publish)
        ) {
            $this->import($last_id, 'publish');
        }

        return $result;
    }

    /**
     * @param $id
     * @param string $status
     */
    public function import($id, $status = 'draft')
    {
        $import_data = $this->db->get_ak_post($id);

        if (! $import_data) {
            return;
        }

        $data = json_decode($import_data->data);
        $post = $data->post;
        $post_meta = isset($data->post_meta) ? $data->post_meta : null;
        $post_author = isset($data->post_author) ? $data->post_author : null;
        $post_terms = isset($data->post_terms) ? $data->post_terms : null;
        $post_images = isset($data->post_images) ? $data->post_images : null;

        //First, import author because it need to be assigned to post object
        $post_author = $this->import_author($post_author->data);
        // Then its time to import the post object
        $post_id = $this->import_post($post, $status, $post_author);
        // Add canonical url and channel to post
        $this->add_post_tracking_info($post_id, $post);
        // Next import and assign post meta to post
        $this->assign_post_meta($post_id, $post_meta);
        // Import all the post related terms including parent terms
        $post_terms = $this->import_terms($post_terms);
        // Then assign terms that are related to post
        $this->assign_post_terms($post_id, $post_terms);
        // Finally, import and assign images to post
        $this->import_and_assign_images_to_post($post_id, $post_images);
    }

    /**
     * @param $post
     * @param string $status
     * @param string $author_id
     * @return bool|int|WP_Error
     */
    private function import_post($post, $status = 'draft', $author_id = '')
    {
        if (! $post) {
            return false;
        }
        $post_name = $post->post_name;
        $post_type = $post->post_type;
        $fillable_post_data = $this->filter_fields((array)$post, $this->post_fields);

        $post_id = $this->db->post_exists($post_name, $post_type);
        $fillable_post_data['post_status'] = $status;

        if ($post_id) {
            $fillable_post_data['ID'] = $post_id;
            wp_update_post($fillable_post_data);
        } else {
            if ($author_id) {
                $fillable_post_data['author'] = $author_id;
            }
            $post_id = wp_insert_post($fillable_post_data);
        }

        $fillable_post_data['post_content'] = $this->replace_embeded_special_content($fillable_post_data['post_content']);
        wp_update_post($fillable_post_data);

        $this->db->update_ak_post([
            'post_id' => $post_id,
            'status' => $this->db->get_status_value('up-to-date')
        ], $post_name, $post_type);

        return $post_id;
    }

    /**
     * @param $post_id
     * @param $post_meta
     * @return bool
     */
    private function assign_post_meta($post_id, $post_meta)
    {
        if (! $post_meta) {
            return false;
        }

        $post_meta = (array) $post_meta;

        if ($post_meta) {
            foreach ($post_meta as $key => $value) {
                // Reset featured image, it will be set when importing and assigning images
                if (in_array($key, $this->meta_keys_with_image_id_value)) {
                    $img_id = $this->replace_embeded_special_content($value[0]);
                    $post_meta[$key][0] = $img_id;
                } elseif (in_array($key, $this->meta_keys_with_image_src_value)) {
                    $img_src = $this->replace_embeded_special_content($value[0]);
                    $post_meta[$key][0] = $img_src;
                } elseif (in_array($key, $this->meta_serialized_keys_with_image_value)) {
                    $item_data = unserialize(str_replace('\\', '', $post_meta[$key][0]));
                    $item_data['url'] = $this->replace_embeded_special_content($item_data['url']);
                    $item_data['id'] = $this->replace_embeded_special_content($item_data['id']);
                    $item_data['thumbnail'] = wp_get_attachment_image_src($item_data['id'], 'thumbnail')[0];
                    $post_meta[$key][0] = $item_data;
                } elseif (in_array($key, $this->meta_keys_with_term_id)) {
                    $term = $post_meta[$key][0];
                    $term = get_term_by('slug', $term->slug, $term->taxonomy);
                    if ($term instanceof WP_Term) {
                        $post_meta[$key][0] = $term->term_id;
                    } else {
                        $post_meta[$key][0] = '';
                    }
                } elseif (in_array($key, $this->meta_keys_with_custom_post_type_id)) {

                    $linked_posts = get_posts(array(
                        'name' => $post_meta[$key][0]->post_name,
                        'posts_per_page' => 1,
                        'post_type' => $post_meta[$key][0]->post_type,
                        'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit')
                    ));

                    if ($linked_posts && ! $linked_posts instanceof WP_Error) {
                        $post_meta[$key][0] = $linked_posts[0]->ID;
                    } else {
                        $post_meta[$key][0] = '';
                    }

                } else {
                    // do nothing
                }

                if (is_serialized($post_meta[$key][0])) {
                    $array_data = unserialize(str_replace('\\', '', $post_meta[$key][0]));
                    update_post_meta($post_id, $key, $array_data);
                } else {
                    update_post_meta($post_id, $key, $post_meta[$key][0]);
                }
            }
        }

    }

    /**
     * Import author if doesn't exist
     * @param $post_author
     * @return bool|false|int|WP_Error
     */
    private function import_author($post_author)
    {
        // If no post author, return false
        if (! $post_author) {
            return false;
        }

        // check if exist
        if ($id = email_exists($post_author->user_email)) {
            return $id;
        }

        // Else, import user
        $user = $this->filter_fields($post_author, $this->author_fields);
        $user['role'] = 'author';

        $id = wp_insert_user($user);
        return $id;
    }

    /**
     * @param $terms
     * @return array|bool
     */
    private function import_terms($terms)
    {
        if (! $terms) {
            return false;
        }

        $response = [];
        foreach ($terms as $term) {
            // Check if term exists
            $existing_term = term_exists($term->slug, $term->taxonomy);
            if ($existing_term) {
                $response[] = [
                    'id' => (int)$existing_term['term_id'],
                    'taxonomy' => $term->taxonomy,
                    'belongs_to_post' => isset($term->belongs_to_post) ? $term->belongs_to_post : false
                ];
            } else {
                $term_parent = $this->find_term_using_id($term->parent, $terms);
                if ($term_parent) {
                    $term_parent = term_exists($term_parent->slug, $term_parent->taxonomy);
                }

                $args = [
                    'description' => $term->description,
                    'slug' => $term->slug,
                    'parent' => isset($term_parent['term_id']) ? $term_parent['term_id'] : 0
                ];

                $inserted_term = wp_insert_term($term->name, $term->taxonomy, $args);

                if (is_array($inserted_term) && isset($inserted_term['term_id'])) {
                    $response[] = [
                        'id' => (int)$inserted_term['term_id'],
                        'taxonomy' => $term->taxonomy,
                        'belongs_to_post' => isset($term->belongs_to_post) ? $term->belongs_to_post : false
                    ];
                }
            }
        }

        return $response;
    }

    /**
     * @param $post_id
     * @param $post_images
     * @return bool
     */
    private function import_and_assign_images_to_post($post_id, $post_images)
    {
        if (! $post_images) {
            return false;
        }

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        if (is_array($post_images) && !empty($post_images)) {
            foreach ($post_images as $image) {
                $image_url = $image->guid;
                $saved_image_id = $this->save_image_to_post($image_url, $post_id);

                if ($saved_image_id && (! $saved_image_id instanceof WP_Error) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $post_id
     * @param $terms
     * @return bool
     */
    private function assign_post_terms($post_id, $terms)
    {
        if (! $terms) {
            return false;
        }

        $grouped_terms = [];
        if ($terms) {
            foreach ($terms as $term) {
                if (isset($term['belongs_to_post']) && $term['belongs_to_post']) {
                    $grouped_terms[$term['taxonomy']][] = $term['id'];
                }
            }
        }


        if ($terms) {
            foreach ($grouped_terms as $key => $value) {
                wp_set_post_terms($post_id, $value, $key);
            }
        }
    }

    private function filter_fields($data, $allowedKeys)
    {
        $data = (array) $data;
        $filteredData = [];
        if ($data) {
            foreach ($allowedKeys as $allowedKey) {
                if (isset($data[$allowedKey])) {
                    $filteredData[$allowedKey] = $data[$allowedKey];
                }
            }
        }

        return $filteredData;
    }

    private function verify_access_token()
    {
        if (! isset($_POST['access_token'])) {
            return false;
        }

        $access_token = $_POST['access_token'];

        $current_site_access_token = $this->db->get_option('access_token');

        return $access_token === $current_site_access_token;
    }

    /**
     * Save only new images and cache it
     * return cached image id if present, else save image and return its id
     *
     * @param $image_url
     * @param $post_id
     * @return bool|string|WP_Error
     */
    private function save_image_to_post($image_url, $post_id = '')
    {
        // Check if it exists in the imported images list
        $saved_image_id = $this->db->ak_get_imported_image($image_url);

        // If not import and cache it in the imported images list
        if (! $saved_image_id) {
            $saved_image_id = media_sideload_image(str_replace('akolade.test', '6acdb85c.ngrok.io', $image_url), $post_id, '', 'id');
            if (is_int($saved_image_id)) {
                $this->db->ak_remember_imported_image($image_url, $saved_image_id);
            }
        }

        return $saved_image_id;
    }

    private function replace_embeded_special_content($content)
    {
        $content = preg_replace_callback(
            '/%akagsrcstart%(.*?)%akagsrcend%/',
            function ($matches) {
                $img_src = $this->db->ak_get_imported_image($matches[1], 'src');

                if (! $img_src) {
                    $saved_image_id = media_sideload_image(str_replace('akolade.test', '6acdb85c.ngrok.io', $matches[1]), '', '', 'id');
                    if (is_int($saved_image_id)) {
                        $this->db->ak_remember_imported_image($matches[1], $saved_image_id);
                        $img_src = wp_get_attachment_url($saved_image_id);
                    } else {
                        // return un-parsed string
                        $img_src = $matches[0];
                    }
                }

                return $img_src;
            },
            $content
        );

        $content = preg_replace_callback(
            '/%akagidstart%(.*?)%akagidend%/',
            function ($matches) {
                $img_id = $this->db->ak_get_imported_image($matches[1], 'id');
                if (! $img_id) {
                    $saved_image_id = media_sideload_image(str_replace('akolade.test', '6acdb85c.ngrok.io', $matches[1]), '', '', 'id');
                    if (is_int($saved_image_id)) {
                        $this->db->ak_remember_imported_image($matches[1], $saved_image_id);
                        $img_id = $saved_image_id;
                    } else {
                        // return un-parsed string
                        $img_id = $matches[0];
                    }
                }

                return $img_id;
            },
            $content
        );

        // download rev_slider on [rev_slider alias="event-slider" download-url="slider_url"]
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

                    if (isset($attributes['download-url']) && isset($attributes['alias'])) {
                        $alias = $attributes['alias'];
                        $download_url = $attributes['download-url'];

                        /**
                         * The class for exporting and importing rev slider
                         */
                        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-akolade-aggregator-rev-slider.php';
                        $slider = new Akolade_Aggregator_Rev_Slider();
                        try {
                            // if slider exists, delete
                            $slider->initByAlias($alias);
                            $slider->deleteSlider();

                            // Then create new one again
                            $slider = new Akolade_Aggregator_Rev_Slider();
                        } catch (\Exception $exception) {
                            // do nothing
                        }

                        $slider->importSlider(str_replace('akolade.test', '6acdb85c.ngrok.io', $download_url));

                        return '[rev_slider alias="' . $alias . '"]';
                    }

                    return $matches[0];
                },
                $content
            );
        }

        return $content;
    }

    /**
     * @param $id
     * @param $terms_array
     * @return bool|mixed
     */
    private function find_term_using_id($id, $terms_array)
    {
        if (! is_array($terms_array)) {
            return false;
        }

        foreach ($terms_array as $term) {
            if ($id === $term->term_id) {
                return $term;
            }
        }

        return false;
    }

    private function add_post_tracking_info($post_id, $post)
    {
        update_post_meta($post_id, 'canonical_url', $post->canonical_url);
        update_post_meta($post_id, 'channel', $post->channel);

        $existing_term = term_exists($post->channel, 'channel');
        if ($existing_term) {
            wp_set_post_terms($post_id, [$existing_term['term_id']], 'channel', 'true');
        }
    }
}