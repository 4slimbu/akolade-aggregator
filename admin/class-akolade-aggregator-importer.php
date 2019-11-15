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

    /**
     * List of field type for special meta field only.
     * Fields not specified can be assumed as string.
     *
     * @var array
     */
    private $meta_field_type = [
        '_thumbnail_id' => 'img_id',
//        '_thumbnail_id' => 'img_src',
//        '_thumbnail_id' => 'serialized_img_object',
    ];

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
        $channel = $data['channel'];
        $post = $data['post'];
        $post_name = $post['post_name'];
        $post_type = $post['post_type'];
        $post_canonical_url = $data['post_canonical_url'];

        $row = [
            'post_title' => $post['post_title'],
            'post_canonical_url' => $post_canonical_url,
            'post_name' => $post_name,
            'channel' => $channel,
            'post_type' => $post_type,
            'data' => json_encode($data),
            'status' => $this->db->get_status_value('new'),
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

        if ($this->db->get_option('auto_publish')) {
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
        $post_meta = $data->post_meta ?? null;
        $post_author = $data->post_author ?? null;
        $post_terms = $data->post_terms ?? null;
        $post_images = $data->post_images ?? null;

        $post_id = $this->import_post($post, $status, $this->import_author($post_author->data));
        // Set channel
        $this->assign_post_meta($post_id, $post_meta);
        $this->assign_post_terms($post_id, $this->import_terms($post_terms));
        $this->import_and_assign_images_to_post($post_id, $post_images, $post_meta);
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
        if ($post_id) {
            $fillable_post_data['ID'] = $post_id;
            wp_update_post($fillable_post_data);
        } else {
            $fillable_post_data['post_status'] = $status;
            if ($author_id) {
                $fillable_post_data['author'] = $author_id;
            }
            $post_id = wp_insert_post($fillable_post_data);
        }

        $fillable_post_data['post_content'] = $this->replace_embeded_image_placeholder($fillable_post_data['post_content']);
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
                if ($key === '_thumbnail_id') {
                    update_post_meta($post_id, $key, '');
                } else {
                    update_post_meta($post_id, $key, $value[0]);
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

        //TODO: Make more flexible to address parent term issues
        foreach ($terms as $term) {
            // Check if term exists
            $existing_term = term_exists($term->slug, $term->taxonomy);
            if ($existing_term) {
                $response[] = [
                    'id' => (int)$existing_term['term_id'],
                    'taxonomy' => $term->taxonomy
                ];
            } else {
                $args = [
                    'description' => $term->description,
                    'slug' => $term->slug,
                ];

                // Check if parent id exists
                $parent_term_id = null;
                if ($term->parent) {
                    // Check if parent exists in imported list
                    $existing_parent_term = term_exists($term->parent, $term->taxonomy);

                    if ($existing_parent_term) {
                        $args['parent'] = $existing_parent_term['term_id'];
                    }
                }

                $inserted_term = wp_insert_term($term->name, $term->taxonomy, $args);

                if (is_array($inserted_term) && isset($inserted_term['term_id'])) {
                    $response[] = [
                        'id' => (int)$inserted_term['term_id'],
                        'taxonomy' => $term->taxonomy
                    ];
                }
            }
        }

        return $response;
    }

    /**
     * @param $post_id
     * @param $post_images
     * @param null $post_meta
     * @return bool
     */
    private function import_and_assign_images_to_post($post_id, $post_images, $post_meta = null)
    {
        if (! $post_images || ! $post_meta) {
            return false;
        }

        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $featured_image_id = null;
        if (isset($post_meta->_thumbnail_id)) {
            $featured_image_id = $post_meta->_thumbnail_id[0];
        }

        if (is_array($post_images) && !empty($post_images)) {
            foreach ($post_images as $image) {
                $image_url = $image->guid;
                $saved_image_id = $this->save_image_to_post($image_url, $post_id);

                // If image is featured image
                if ($image->ID === $featured_image_id && is_int($saved_image_id)) {
                    update_post_meta($post_id, '_thumbnail_id', $saved_image_id);
                }
            }
        }
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

        if ($terms) {
            foreach ($terms as $term) {
                wp_set_object_terms($post_id, $term['id'], $term['taxonomy']);
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
//                $image_url = "http://048d46e7.ngrok.io/wp-content/uploads/2019/06/blake-wisz-1554907-unsplash.jpg";
            $saved_image_id = media_sideload_image($image_url, $post_id, '', 'id');
            if (is_int($saved_image_id)) {
                $this->db->ak_remember_imported_image($image_url, $saved_image_id);
            }
        }

        return $saved_image_id;
    }

    private function replace_embeded_image_placeholder($content)
    {
        $content = preg_replace_callback(
            '/%akagsrcstart%(.*?)%akagsrcend%/',
            function ($matches) {
                $img_src = $this->db->ak_get_imported_image($matches[1], 'src');

                if (! $img_src) {
                    $saved_image_id = media_sideload_image($matches[1], '', '', 'id');
                    if (is_int($saved_image_id)) {
                        $this->db->ak_remember_imported_image($matches[1], $saved_image_id);
                    }

                    $img_src = wp_get_attachment_url($saved_image_id);
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
                    $saved_image_id = media_sideload_image($matches[1], '', '', 'id');
                    if (is_int($saved_image_id)) {
                        $this->db->ak_remember_imported_image($matches[1], $saved_image_id);
                    }

                    $img_id = $saved_image_id;
                }

                return $img_id;
            },
            $content
        );

        return $content;
    }
}