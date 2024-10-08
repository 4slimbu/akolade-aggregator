<?php

class Akolade_Aggregator_Admin_Settings
{
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

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'akolade-aggregator',
            'akolade-aggregator',
            array( $this, 'sanitize' )
        );

        add_settings_section(
            'main-section',
            '',
            '',
            'akolade-aggregator-settings'
        );

        add_settings_field(
            'type',
            'Type',
            array( $this, 'type_callback' ),
            'akolade-aggregator-settings',
            'main-section',
            array('class' => 'akag-type')
        );

        add_settings_field(
            'access_token',
            'Access Token',
            array( $this, 'access_token_callback' ),
            'akolade-aggregator-settings',
            'main-section',
            array('class' => 'parent-importer')
        );

        add_settings_field(
            'network_sites',
            'Network Sites',
            array( $this, 'network_sites_callback' ),
            'akolade-aggregator-settings',
            'main-section',
            array('class' => 'parent-exporter')
        );

        add_settings_field(
            'auto_publish',
            'Auto Publish',
            array( $this, 'auto_publish_callback' ),
            'akolade-aggregator-settings',
            'main-section',
            array('class' => 'parent-importer')
        );

        add_settings_field(
            'is_scheduled',
            'Schedule',
            array( $this, 'is_scheduled_callback' ),
            'akolade-aggregator-settings',
            'main-section',
            array('class' => 'parent-importer')
        );

    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     *
     * @return array
     */
    public function sanitize( $input )
    {
        $new_input = array();

        if( isset( $input['type'] ) )
            $new_input['type'] = sanitize_text_field( $input['type'] );

        if( isset( $input['access_token'] ) )
            $new_input['access_token'] = sanitize_text_field( $input['access_token'] );

        if( isset( $input['network_sites'] ) ) {
            foreach ($input['network_sites'] as $network_site) {
                if (! empty($network_site['title']) || ! empty($network_site['url']) || ! empty($network_site['access_token'])) {
                    $new_input['network_sites'][] = $network_site;
                }
            }
        }

        if( isset( $input['auto_publish'] ) )
            $new_input['auto_publish'] = sanitize_text_field( $input['auto_publish'] );

        if( isset( $input['is_scheduled'] ) )
            $new_input['is_scheduled'] = sanitize_text_field( $input['is_scheduled'] );

        return $new_input;
    }

    /**
     * Get the type ( exporter or importer ) that plugin will function
     */
    public function type_callback()
    {
        ?>
        <select name="akolade-aggregator[type]" id="type" value="<?php echo $this->getOption('type')?>">
            <option value="exporter" <?php echo $this->getOption('type') === "exporter" ? 'selected': '' ?>>Exporter</option>
            <option value="importer" <?php echo $this->getOption('type') === "importer" ? 'selected': '' ?>>Importer</option>
        </select>
        <?php
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function access_token_callback()
    {
        printf(
            '<input type="text" id="access_token" class="form-input" name="akolade-aggregator[access_token]" value="%s"  /><button class="ak-generate-token" type="button">Generate New</button><br /><small>Use this access token on other site to post data on this site. If new access token is generated, sites using previous access token won\'t be able to post on this site.</small>',
            $this->getOption('access_token') ? esc_attr( $this->getOption('access_token')) : ''
        );
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function network_sites_callback()
    {
        ob_start();
        $current_index = 0;
        ?>
        <table style="width:100%" class="ak-network-sites">
            <tr>
                <th>Site Title</th>
                <th>Site Url</th>
                <th>Access Token</th>
            </tr>
                <?php if($this->getOption('network_sites')): ?>
                <?php foreach($this->getOption('network_sites') as $key => $network_site): ?>
                <tr>
                    <td>
                        <input type="text"  class="form-input" name="akolade-aggregator[network_sites][<?php echo $key; ?>][title]"
                               placeholder="Remote site title" value="<?php echo $network_site['title']; ?>"/>
                    </td>
                    <td>
                        <input type="text"  class="form-input" name="akolade-aggregator[network_sites][<?php echo $key; ?>][url]"
                               placeholder="Remote site url" value="<?php echo $network_site['url']; ?>"/>
                    </td>
                    <td>
                        <input type="text" class="form-input" name="akolade-aggregator[network_sites][<?php echo $key; ?>][access_token]"
                               placeholder="Access token" value="<?php echo $network_site['access_token']; ?>"/>
                    </td>
                </tr>
                <?php $current_index++; endforeach; ?>
                <?php endif; ?>
        </table>

        <button class="ak-add-new-element" type="button" data-current-index="<?php echo $current_index; ?>">Add New +</button>
        <br><small>Add sites to post data from this site.</small>
        <?php
        $content = ob_get_clean();
        echo $content;
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function auto_publish_callback()
    {
        ?>
        <select name="akolade-aggregator[auto_publish]" id="auto_publish" value="<?php echo $this->getOption('auto_publish')?>">
            <option value="0" <?php echo $this->getOption('auto_publish') === "0" ? 'selected': '' ?>>Import Only</option>
            <option value="1" <?php echo $this->getOption('auto_publish') === "1" ? 'selected': '' ?>>Import And Save as Draft</option>
            <option value="2" <?php echo $this->getOption('auto_publish') === "2" ? 'selected': '' ?>>Import And Publish</option>
        </select>
        <?php
    }

    /**
     * Is scheduled checkbox
     */
    public function is_scheduled_callback()
    {
        ?>
        <input type="checkbox" name="akolade-aggregator[is_scheduled]" value="1" <?php echo $this->getOption('is_scheduled') === "1" ? 'checked' : '' ?>> Schedule
        <?php
    }

    public function render_settings()
    {
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields( 'akolade-aggregator' );
                do_settings_sections( 'akolade-aggregator-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}