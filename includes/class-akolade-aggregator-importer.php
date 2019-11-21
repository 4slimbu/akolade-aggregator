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

    /**
     * The post fields that importer needs to import post
     *
     * @var array $post_fields
     */
    private $post_fields = [
        'post_name',
        'post_title',
        'post_excerpt',
        'post_content',
        'post_status',
        'post_type'
    ];

    /**
     * The author fields that importer needs to import author
     *
     * @var array $author_fields
     */
    private $author_fields = [
        'user_pass', 'user_login', 'user_nicename', 'user_email', 'display_name', 'nickname',
        'first_name', 'last_name', 'description', 'role'
    ];

    /**
     * Post types that will be auto published
     *
     * @var array $always_publish
     */
    private $always_publish = ['special-content'];

    /**
     * Meta fields that represents image id
     *
     * @var array $meta_keys_with_image_id_value
     */
    private $meta_keys_with_image_id_value = ['_thumbnail_id', 'menu_thumbnail'];

    /**
     * Meta fields that represents image src
     *
     * @var array
     */
    private $meta_keys_with_image_src_value = [];

    /**
     * Meta fields that represents serialized image src/id
     *
     * @var array
     */
    private $meta_serialized_keys_with_image_value = ['post-sponser-logo'];

    /**
     * Meta fields that represents custom post type id
     *
     * @var array
     */
    private $meta_keys_with_custom_post_type_id = ['event_custom_template', 'event_sponsers'];

    /**
     * Meta fields that represents taxonomy term id
     *
     * @var array
     */
    private $meta_keys_with_term_id = ['event_speakers'];

    /**
     * Meta keys to exclude from importing
     */
    private $meta_keys_to_exclude = ['_edit_last', '_edit_lock', 'post_views_count'];

    /**
     * Database layer
     *
     * @var Akolade_Aggregator_DB
     */
    private $db;

    /**
     * Akolade_Aggregator_Importer constructor.
     */
    public function __construct()
    {
        $this->db = new Akolade_Aggregator_DB();
    }

    /**
     * The main function called by Exporter through ajax.
     *
     * Saves the data received on the request and may import assets and create post depending upon the setting
     *
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

        // If not scheduled
        if (! $this->db->get_option('is_scheduled')) {
            // if auto_publish is set to "Import and save as draft", create draft of imported post
            if ( $this->db->get_option('auto_publish') === '1' ) {
                $this->import($last_id, 'draft');
            }

            // if auto_publish is set to "Import and Publish" Publish imported post
            if (
                $this->db->get_option('auto_publish') === '2' ||
                in_array($post_type, $this->always_publish)
            ) {
                $this->import($last_id, 'publish');
            }
        };

        return $result;
    }

    /**
     * Import Post including all the meta and assets
     *
     * This function is a wrapper function to imports post author, post, post_meta, post_images, post_terms
     *
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
     * Import post
     *
     * This function imports only fields available for Post Object.
     * Meta data, images etc are handled by other respective functions.
     *
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

        // initiate necessary data
        $post_name = $post->post_name;
        $post_type = $post->post_type;
        // keep only the required fields
        $fillable_post_data = $this->filter_fields((array)$post, $this->post_fields);
        // Set post status
        $fillable_post_data['post_status'] = $status;
        // Replace placeholders inside the post content
        $fillable_post_data['post_content'] = $this->replace_embeded_special_content($fillable_post_data['post_content']);
        // Set post author
        $fillable_post_data['post_author'] = $author_id;

        // Check if post that need to be imported, exists in the database
        // and import or update accordingly
        $post_id = $this->db->post_exists($post_name, $post_type);

        if ($post_id) {
            $fillable_post_data['ID'] = $post_id;
            wp_update_post($fillable_post_data);
        } else {
            $post_id = wp_insert_post($fillable_post_data);
        }

        // Update status on Akolade aggregator imported post list table as well
        $this->db->update_ak_post([
            'post_id' => $post_id,
            'status' => $this->db->get_status_value('up-to-date')
        ], $post_name, $post_type);

        // return inserted/updated post_id
        return $post_id;
    }

    /**
     * Assign meta data to post
     *
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
                // Don't assign meta keys that are excluded
                if (in_array($key, $this->meta_keys_to_exclude)) {
                    continue;
                }

                // Replace image_id placeholder
                if (in_array($key, $this->meta_keys_with_image_id_value)) {
                    $img_id = $this->replace_embeded_special_content($value[0]);
                    $post_meta[$key][0] = $img_id;

                //Replace image_src placeholder
                } elseif (in_array($key, $this->meta_keys_with_image_src_value)) {
                    $img_src = $this->replace_embeded_special_content($value[0]);
                    $post_meta[$key][0] = $img_src;

                // Replace image inside serialized object
                } elseif (in_array($key, $this->meta_serialized_keys_with_image_value)) {
                    $item_data = unserialize(str_replace('\\', '', $post_meta[$key][0]));
                    $item_data['url'] = $this->replace_embeded_special_content($item_data['url']);
                    $item_data['id'] = $this->replace_embeded_special_content($item_data['id']);
                    $item_data['thumbnail'] = wp_get_attachment_image_src($item_data['id'], 'thumbnail')[0];
                    $post_meta[$key][0] = $item_data;

                // Replace term_id placeholder
                } elseif (in_array($key, $this->meta_keys_with_term_id)) {
                    $term = $post_meta[$key][0];
                    $term = get_term_by('slug', $term->slug, $term->taxonomy);
                    if ($term instanceof WP_Term) {
                        $post_meta[$key][0] = $term->term_id;
                    } else {
                        $post_meta[$key][0] = '';
                    }

                // Replace custom_post_type_id placeholder
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

                // For serialized imported content, we need to use unserialized first to prevent double serialization
                // of data because wordpress automatically serializes the data.
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
     *
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
     * Import terms
     *
     * This function expects terms to be in the order, such that independent terms like parent terms comes first and
     * children terms at last. It is to make parent_id available for children terms to use and also to remove complex
     * logic and loops to import hierarchical terms.
     *
     * This ordering part is expected to be handled while exporting.
     *
     * @param $terms
     * @return array|bool $response Contains array of terms with "belongs_to_post" key to determine if it can be attached to post
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
                // Get parent term id, if exists
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
     * Assign images, if necessary import, to post
     *
     * @param $post_id
     * @param $post_images
     * @return bool
     */
    private function import_and_assign_images_to_post($post_id, $post_images)
    {
        if (! $post_images) {
            return false;
        }

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
     * Assign terms to post
     *
     * @param $post_id
     * @param $terms
     * @return bool
     */
    private function assign_post_terms($post_id, $terms)
    {
        if (! $terms) {
            return false;
        }

        // Group terms by taxonomy
        $grouped_terms = [];
        if ($terms) {
            foreach ($terms as $term) {
                if (isset($term['belongs_to_post']) && $term['belongs_to_post']) {
                    $grouped_terms[$term['taxonomy']][] = $term['id'];
                }
            }
        }

        // assign grouped terms to post for each taxonomy
        if ($terms) {
            foreach ($grouped_terms as $key => $value) {
                wp_set_post_terms($post_id, $value, $key);
            }
        }
    }

    /**
     * Filters array/object elements using allowed keys list
     *
     * @param $data
     * @param $allowedKeys
     * @return array Filtered data
     */
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

    /**
     * Verifies the access_token passed in the importer ajax call
     *
     * @return bool
     */
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
            $saved_image_id = media_sideload_image(str_replace('akolade.test', '2b5fce3b.ngrok.io', $image_url), $post_id, '', 'id');
            if (is_int($saved_image_id)) {
                $this->db->ak_remember_imported_image($image_url, $saved_image_id);
            }
        }

        return $saved_image_id;
    }

    /**
     * Replace placeholder in content
     *
     * When content with various special placeholder like image id, image src, rev slider etc is passed,
     * this function parses the placeholder, import them if necessary and replace it with respective id, src or content
     *
     * @param $content
     * @return mixed $content Parsed Content
     */
    private function replace_embeded_special_content($content)
    {
        $content = preg_replace_callback(
            '/%akagsrcstart%(.*?)%akagsrcend%/',
            function ($matches) {
                $img_src = $this->db->ak_get_imported_image($matches[1], 'src');

                if (! $img_src) {
                    $saved_image_id = media_sideload_image(str_replace('akolade.test', '2b5fce3b.ngrok.io', $matches[1]), '', '', 'id');
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
                    $saved_image_id = media_sideload_image(str_replace('akolade.test', '2b5fce3b.ngrok.io', $matches[1]), '', '', 'id');
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
                        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-akolade-aggregator-rev-slider.php';
                        $slider = new Akolade_Aggregator_Rev_Slider();

                        try {
                            // if slider exists, delete
                            $slider->initByAlias($alias);
                            if ($slider) {
                                $slider->deleteSlider();
                            }

                            // Then create new one again
                            $slider = new Akolade_Aggregator_Rev_Slider();
                        } catch (\Exception $exception) {
                            // do nothing
                        }

                        $slider->importSlider(str_replace('akolade.test', '2b5fce3b.ngrok.io', $download_url));

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
     * Find term using term_id in an array of WP_Term objects
     *
     * @param $id
     * @param $terms_array
     * @return bool|mixed
     */
    private function find_term_using_id($id, $terms_array)
    {
        if (! is_array($terms_array)) {
            return false;
        }

        // loop through terms list and return the first term whose term_id matches the provided id
        foreach ($terms_array as $term) {
            if ($id === $term->term_id) {
                return $term;
            }
        }

        return false;
    }

    /**
     * Add post tracking info like channel and canonical url
     *
     * @param $post_id
     * @param $post
     */
    private function add_post_tracking_info($post_id, $post)
    {
        // add canonical_url and channel to post_meta
        update_post_meta($post_id, 'canonical_url', $post->canonical_url);
        update_post_meta($post_id, 'channel', $post->channel);

        // Add post to channel term if exist, else create channel term and add post to it
        $existing_term = term_exists($post->channel, 'channel');
        if ($existing_term) {
            wp_set_post_terms($post_id, [$existing_term['term_id']], 'channel', 'true');
        } else {
            $args = [
                'description' => '',
                'slug' => $post->channel,
                'parent' => 0
            ];
            $inserted_term = wp_insert_term($post->channel, 'channel', $args);

            if (is_array($inserted_term) && isset($inserted_term['term_id'])) {
                wp_set_post_terms($post_id, [$inserted_term['term_id']], 'channel', 'true');
            }
        }
    }

    /**
     * Import multiple posts at once
     * Used in scheduled event
     *
     * @param int $count
     */
    public function import_posts_in_batch($count = 1)
    {
        // If not scheduled, abort
        if (! $this->db->get_option('is_scheduled')) {
            return;
        }

        $status = '';
        $pending_posts = $this->db->get_ak_pending_posts($count);

        if ($pending_posts) {
            foreach ($pending_posts as $pending_post) {
                // Skip if importing post status is cancelled
                if ($pending_post->status === $this->db->get_status_value('cancelled')) {
                    continue;
                }

                // If auto_publish is set, directly import else check other things
                if (in_array($pending_post['post_type'], $this->always_publish)) {
                    $status = 'publish';
                } else {
                    // Skip if, saving post is not set
                    if (
                        $this->db->get_option('auto_publish') !== '1' ||
                        $this->db->get_option('auto_publish') !== '2'
                    ) {
                        continue;
                    }

                    // if auto_publish is set to "Import and save as draft", create draft of imported post
                    if ( $this->db->get_option('auto_publish') === '1' ) {
                        $status = 'draft';
                    }

                    // if auto_publish is set to "Import and Publish" Publish imported post
                    if ( $this->db->get_option('auto_publish') === '2' ) {
                        $status = 'publish';
                    }
                }

                // Import post
                if ($status && isset($pending_post['id'])) {
                    $this->import($pending_post['id'], $status);
                }
            }
        }
    }
}