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
            'access_token',
            'Access Token',
            array( $this, 'access_token_callback' ),
            'akolade-aggregator-settings',
            'main-section'
        );

        add_settings_field(
            'network_sites',
            'Network Sites',
            array( $this, 'network_sites_callback' ),
            'akolade-aggregator-settings',
            'main-section'
        );

        add_settings_field(
            'auto_publish',
            'Auto Publish',
            array( $this, 'auto_publish_callback' ),
            'akolade-aggregator-settings',
            'main-section'
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
        if( isset( $input['access_token'] ) )
            $new_input['access_token'] = sanitize_text_field( $input['access_token'] );

        if( isset( $input['network_sites'] ) )
            $new_input['network_sites'] = sanitize_text_field( $input['network_sites'] );

        if( isset( $input['auto_publish'] ) )
            $new_input['auto_publish'] = sanitize_text_field( $input['auto_publish'] );

        return $new_input;
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function access_token_callback()
    {
        printf(
            '<input type="text" id="access_token" name="akolade-aggregator[access_token]" value="%s"  /><br /><small>Leave blank if you want it to be valid for all date and time.</small>',
            $this->getOption('access_token') ? esc_attr( $this->getOption('access_token')) : ''
        );
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function network_sites_callback()
    {
        ob_start();
        ?>
        <table style="width:100%">
            <tr>
                <th>Site Url</th>
                <th>Access Token</th>
            </tr>
            <?php if ($this->getOption('network_sites')): ?>
                <?php foreach($this->getOption('network_sites') as $network_site): ?>
                <tr>
                    <td><?php echo $network_site->url; ?></td>
                    <td><?php echo $network_site->access_token; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>

        </table>

        <input type="text" id="network_sites" name="akolade-aggregator[network_sites]"
               value="<?php echo $this->getOption('network_sites') ? esc_attr( $this->getOption('network_sites')) : ''; ?>"  />

        <?php
        $content = ob_get_clean();
        echo $content;
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function auto_publish_callback()
    {
        printf(
            '<input type="checkbox" id="auto_publish" name="akolade-aggregator[auto_publish]" value="1"  %s/><br><small>If checked, the popup will be shown on front page only</small>',
            ($this->getOption('auto_publish')) ? 'checked' : ''
        );
    }

    public function admin_add_scripts($hook) {
        if ( 'akolade_aggregator_settings' != $hook ) {
            return;
        }

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