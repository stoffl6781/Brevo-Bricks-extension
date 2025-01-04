<?php
/*
    Plugin Name: Brevo - Bricks extension
    Plugin URI: purin.at
    Version: 1.0.0
    Author: Christoph Purin
    Author URI: www.purin.at
*/

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use Brevo\Client\Configuration;
use Brevo\Client\Api\ContactsApi;
use Brevo\Client\Model\CreateContact;

function create_brevo_contact($data) {
    if (empty($data['email'])) {
        error_log('Brevo API request failed: No email address provided');
        return;
    }
    $api_key = get_option('bbe_brevo_api_key');
    $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', $api_key);

    $apiInstance = new ContactsApi(new GuzzleHttp\Client(), $config);

    $attributes = array(
        'FIRSTNAME' => $data['FIRSTNAME'],
        'LASTNAME' => $data['LASTNAME'],
        'isVIP' => true
    );

    if (strpos($data['list'], ',') === false) {
        $list_ids = [(int) $data['list']];
    } else {
        $list_ids = explode(',', $data['list']);
        $list_ids = array_map('intval', $list_ids);
    }
    
    $createContact = new CreateContact([
        'email' => $data['email'],
        'updateEnabled' => true,
        'attributes' => $attributes,
        'listIds' => $list_ids
    ]);

    try {
        $result = $apiInstance->createContact($createContact);
    } catch (Exception $e) {
        error_log('Exception when calling ContactsApi->createContact: ' . $e->getMessage());
    }
}


add_filter( 'bricks/form/custom_action', function( $form ) {
    $form_fields = $form->get_fields();


    $form_settings = $form->get_settings();
    $fields = $form_settings['fields'];

    $desired_labels = array(
        'Vorname' => '',
        'Nachname' => '',
        'E-Mail' => '',
        'BrevoListe' => ''
    );

    $field_ids = array();

    foreach ($fields as $field) {
        $label = $field['label'];
        if (array_key_exists($label, $desired_labels)) {
            $field_id = $field['id'];
            $field_ids[$label] = $field_id;
        }
    }

    $first_name = isset( $form_fields['form-field-' . $field_ids['Vorname']]) ? $form_fields['form-field-' . $field_ids['Vorname']] : '';
    $last_name = isset( $form_fields['form-field-' . $field_ids['Nachname']]) ? $form_fields['form-field-' . $field_ids['Nachname']] : '';
    $email = isset( $form_fields['form-field-' . $field_ids['E-Mail']]) ? $form_fields['form-field-' . $field_ids['E-Mail']] : '';
    $brevo_list = isset( $form_fields['form-field-' . $field_ids['BrevoListe']]) ? $form_fields['form-field-' . $field_ids['BrevoListe']] : '';

    if ( $brevo_list){
        $brevo_data = array(
            'FIRSTNAME' => $first_name,
            'LASTNAME' => $last_name,
            'email' => $email,
            'list' => $brevo_list
        );

        create_brevo_contact($brevo_data);
    }

    return $form;
} );

// Füge eine neue Admin-Seite hinzu
function bbe_brevo_settings_page() {
    add_menu_page(
        'Bricks Brevo Einstellungen',
        'Bricks Brevo Einstellungen', 
        'manage_options',
        'brevo-settings',
        'bbe_brevo_settings_page_content',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'bbe_brevo_settings_page');

function bbe_brevo_settings_page_content() {
    ?>
    <div class="wrap">
        <h1>Brevo Einstellungen</h1>
        <form method="post" action="options.php">
            <?php settings_fields('bbe-brevo-settings-group'); ?>
            <?php do_settings_sections('bbe-brevo-settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function bbe_brevo_register_settings() {
    register_setting(
        'bbe-brevo-settings-group', 
        'bbe_brevo_api_key' 
    );

    register_setting(
        'bbe-brevo-settings-group', 
        'bbe_brevo_bricks_form'
    );

    add_settings_section(
        'bbe-brevo-settings-section', 
        'API Key Einstellungen', 
        'bbe_brevo_settings_section_content',
        'bbe-brevo-settings' 
    );

    add_settings_field(
        'bbe-brevo-api-key-field', 
        'Brevo API Key', 
        'bbe_brevo_api_key_field_callback', 
        'bbe-brevo-settings', 
        'bbe-brevo-settings-section' 
    );

    add_settings_field(
        'bbe-brevo-bricks-form', 
        'Bricks Form', 
        'bbe_brevo_bricks_form_callback', 
        'bbe-brevo-settings', 
        'bbe-brevo-settings-section' 
    );

}
add_action('admin_init', 'bbe_brevo_register_settings');

function bbe_brevo_settings_section_content() {
    echo '<p>Hier kannst du deine Brevo API Key-Einstellungen vornehmen.</p>';
}

function bbe_brevo_api_key_field_callback() {
    $api_key = get_option('bbe_brevo_api_key');
    echo '<input type="text" name="bbe_brevo_api_key" value="' . esc_attr($api_key) . '" />';
}

function bbe_brevo_bricks_form_callback() {
    $bricks_form_id = get_option('bbe_brevo_bricks_form');
    echo '<input type="text" name="bbe_brevo_bricks_form" value="' . esc_attr($bricks_form_id) . '" />';
}
