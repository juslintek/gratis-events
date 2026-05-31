<?php
/**
 * Plugin Name: Gratis Events
 * Description: Event management with calendar views, RSVP, recurring events. Replaces The Events Calendar Pro ($99/yr).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';
add_action('init', [\Gratis\Events\EventPostType::class, 'register']);
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('gratis-events', \Gratis\Events\CLI\EventsCommand::class);
});
