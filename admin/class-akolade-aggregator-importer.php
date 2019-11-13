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
        'post_content' => '',
        'post_title' => '',
        'post_excerpt' => '',
        'post_status' => 'draft',
        'post_name' => '',
        'post_type' => 'post'
    ];

    private $db;

    public function __construct()
    {
        $this->db = new Akolade_Aggregator_DB();
    }

    public function handle()
    {
        $data = $_POST['data'];
        $post = $data['post'];
        $post_name = $post['post_name'];
        $post_type = $post['post_type'];
        $channel = $data['post_channel'];

        $row = [
            'post_title' => $post['post_title'],
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

    public function import($id, $status = 'draft')
    {
        $import_data = $this->db->get_ak_post($id);

        if (! $import_data) {
            return;
        }

        $author_id = $this->importAuthor();
        $term_ids = $this->importTerms();
        $medias = $this->importMedia();
        $this->importPost($import_data, $status);
        $this->assignPostAuthor($author_id);
        $this->assignPostTerms($term_ids);
        $this->assignMediaToPosts($medias);
        $this->importPostMeta();
    }

    private function importPost($import_data, $status = 'draft')
    {
        $post_id = $import_data->post_id;
        $post_name = $import_data->post_name;
        $post_channel = $import_data->channel;
        $post_type = $import_data->post_type;
        $data = json_decode($import_data->data);
        $post = (array)$data->post;
        $post_channel = $data->post_channel;
        $post_meta = $data->post_meta;
        $post_author = $data->post_author;
        $post_terms = $data->post_terms;

        $post['post_status'] = $status;
        var_dump($post);
        die();
        $fillable_post_data = array_intersect_key($post, $this->post_fields);

        $post_id = $this->db->post_exists($post_name, $post_type);
        if ($post_id) {
            $fillable_post_data['ID'] = $post_id;
            wp_update_post($fillable_post_data);
        } else {
            $post_id = wp_insert_post($fillable_post_data);
        }

        $this->db->update_ak_post([
            'post_id' => $post_id,
            'status' => $this->db->get_status_value('up-to-date')
        ], $post_name, $post_type);
    }

    private function importPostMeta()
    {
    }

    private function importPostAuthor()
    {
    }

    private function importPostMedia()
    {
    }

    private function importPostTerms()
    {
    }
}