<?php
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_heading(
        'mod_bunnystream/heading',
        get_string('settings_heading', 'mod_bunnystream'),
        get_string('settings_heading_desc', 'mod_bunnystream')
    ));

    // Library ID (numeric).
    $settings->add(new admin_setting_configtext(
        'mod_bunnystream/library_id',
        get_string('setting_library_id', 'mod_bunnystream'),
        get_string('setting_library_id_desc', 'mod_bunnystream'),
        '',
        PARAM_ALPHANUMEXT
    ));

    // API key (encrypted at rest via \core\encryption).
    $settings->add(new admin_setting_encryptedpassword(
        'mod_bunnystream/api_key',
        get_string('setting_api_key', 'mod_bunnystream'),
        get_string('setting_api_key_desc', 'mod_bunnystream')
    ));

    // Security / Token Authentication key (encrypted).
    $settings->add(new admin_setting_encryptedpassword(
        'mod_bunnystream/security_key',
        get_string('setting_security_key', 'mod_bunnystream'),
        get_string('setting_security_key_desc', 'mod_bunnystream')
    ));

    // CDN hostname (vz-xxxx.b-cdn.net).
    $settings->add(new admin_setting_configtext(
        'mod_bunnystream/cdn_hostname',
        get_string('setting_cdn_hostname', 'mod_bunnystream'),
        get_string('setting_cdn_hostname_desc', 'mod_bunnystream'),
        '',
        PARAM_HOST
    ));

    // Default completion %.
    $settings->add(new admin_setting_configtext(
        'mod_bunnystream/completion_percent',
        get_string('setting_completion_percent', 'mod_bunnystream'),
        get_string('setting_completion_percent_desc', 'mod_bunnystream'),
        '90',
        PARAM_INT,
        5
    ));

    // Webhook URL — read-only display, derived from the singleton row.
    $webhookurl = \mod_bunnystream\config::webhook_url();
    $webhookdesc = $webhookurl
        ? '<code>' . s($webhookurl) . '</code><br>' . get_string('setting_webhook_url_desc', 'mod_bunnystream')
        : get_string('setting_webhook_url_pending', 'mod_bunnystream');
    $settings->add(new admin_setting_description(
        'mod_bunnystream/webhook_url_display',
        get_string('setting_webhook_url', 'mod_bunnystream'),
        $webhookdesc
    ));
}
