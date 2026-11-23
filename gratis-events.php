<?php
/**
 * Plugin Name: Gratis Events
 * Plugin URI: https://github.com/juslintek/gratis-events
 * Description: Event management with calendar views, RSVP, recurring events.
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

/**
 * Inject Event JSON-LD on the events archive page via the gratis-seo graph filter.
 * Emits a schema.org/Event ItemList for all upcoming events with start/end dates.
 */
add_filter('gratis_seo_schema_graph', function (array $graph): array {
    if (!is_post_type_archive(\Gratis\Events\EventPostType::POST_TYPE)) {
        return $graph;
    }

    $events = get_posts([
        'post_type'      => \Gratis\Events\EventPostType::POST_TYPE,
        'posts_per_page' => 50,
        'post_status'    => 'publish',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        'orderby'        => 'meta_value',
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        'meta_key'       => '_start_date',
        'order'          => 'ASC',
    ]);

    if (empty($events)) {
        return $graph;
    }

    $items = [];
    foreach ($events as $position => $event) {
        $start  = (string) get_post_meta($event->ID, '_start_date', true);
        $end    = (string) get_post_meta($event->ID, '_end_date', true);
        $venue  = (string) get_post_meta($event->ID, '_venue', true);
        $url    = (string) get_permalink($event->ID);

        $item = [
            '@type'       => 'Event',
            'name'        => get_the_title($event->ID),
            'url'         => $url,
            'description' => wp_strip_all_tags(get_the_excerpt($event->ID)),
            'organizer'   => [
                '@type' => 'Organization',
                'name'  => (string) get_bloginfo('name'),
                'url'   => home_url('/'),
            ],
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/MixedEventAttendanceMode',
        ];

        if ('' !== $start) {
            $ts = strtotime($start);
            if (false !== $ts) {
                $item['startDate'] = gmdate('c', $ts);
            }
        }
        if ('' !== $end) {
            $ts = strtotime($end);
            if (false !== $ts) {
                $item['endDate'] = gmdate('c', $ts);
            }
        }
        if ('' !== $venue) {
            $item['location'] = [
                '@type' => 'Place',
                'name'  => $venue,
            ];
        }

        if (has_post_thumbnail($event->ID)) {
            $img = get_the_post_thumbnail_url($event->ID, 'large');
            if (is_string($img) && '' !== $img) {
                $item['image'] = $img;
            }
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position + 1,
            'item'     => $item,
        ];
    }

    $graph[] = [
        '@type'           => 'ItemList',
        'name'            => __('GRATIS Community Events', 'gratis-events'),
        'itemListElement' => $items,
    ];

    return $graph;
});
