<?php
/**
 * Plugin Name: Gratis Events
 * Plugin URI: https://github.com/juslintek/gratis-events
 * Description: Event management with calendar views, RSVP, recurring events. Replaces The Events Calendar Pro ($99/yr).
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-events
 *
 * @package Gratis\Events
 */

defined('ABSPATH') || exit;

define('GRATIS_EVENTS_VERSION', '1.0.0');
define('GRATIS_EVENTS_FILE', __FILE__);
define('GRATIS_EVENTS_DIR', plugin_dir_path(__FILE__));
define('GRATIS_EVENTS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('init', [\Gratis\Events\EventPostType::class, 'register']);
add_action('rest_api_init', [\Gratis\Events\EventPostType::class, 'registerAPI']);
add_action('template_redirect', [\Gratis\Events\ICal::class, 'maybeExport']);

add_action('add_meta_boxes', [\Gratis\Events\MetaBox::class, 'register']);
add_action('save_post_gratis_event', [\Gratis\Events\MetaBox::class, 'save'], 10, 2);

add_action('admin_menu', [\Gratis\Events\Settings::class, 'register_menu']);
add_action('admin_init', [\Gratis\Events\Settings::class, 'register_settings']);

add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-events', \Gratis\Events\CLI\EventsCommand::class);
    }
});
