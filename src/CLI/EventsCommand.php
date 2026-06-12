<?php declare(strict_types=1);

namespace Gratis\Events\CLI;

defined('ABSPATH') || exit;

use Gratis\Events\EventPostType;
use Gratis\Events\ICal;

/**
 * WP-CLI commands for Gratis Events.
 */
final class EventsCommand {

    /**
     * List upcoming events.
     *
     * @when after_wp_load
     */
    public function list($args, $assoc_args): void {
        unset($args, $assoc_args);

        $events = get_posts([
            'post_type'      => EventPostType::POST_TYPE,
            'posts_per_page' => 20,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering events by date.
            'meta_key'       => '_start_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- upcoming-event date filter.
            'meta_query'     => [['key' => '_start_date', 'value' => gmdate('Y-m-d'), 'compare' => '>=']],
        ]);
        if (empty($events)) {
            \WP_CLI::log('No upcoming events.');
            return;
        }
        $rows = array_map(static fn($e) => [
            'ID'    => $e->ID,
            'Title' => $e->post_title,
            'Date'  => get_post_meta($e->ID, '_start_date', true),
            'Venue' => get_post_meta($e->ID, '_venue', true) ?: '-',
            'RSVP'  => get_post_meta($e->ID, '_rsvp_count', true) ?: 0,
        ], $events);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Title', 'Date', 'Venue', 'RSVP']);
    }

    /**
     * Export events as an iCal file.
     *
     * [--output=<file>]
     * : Output file path. Defaults to /tmp/gratis-events.ics.
     *
     * @subcommand ical
     * @when after_wp_load
     */
    public function ical($args, $assoc_args): void {
        unset($args);

        // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded export.
        $events = get_posts(['post_type' => EventPostType::POST_TYPE, 'posts_per_page' => 500]);

        $body = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Gratis//Events//EN\r\n";
        foreach ($events as $e) {
            $start = (string) get_post_meta($e->ID, '_start_date', true);
            if ('' === $start) {
                continue;
            }
            $startTs = strtotime($start) ?: time();
            $body   .= "BEGIN:VEVENT\r\nDTSTART:" . gmdate('Ymd\THis\Z', $startTs) . "\r\n";
            $body   .= 'SUMMARY:' . ICal::escape($e->post_title) . "\r\nEND:VEVENT\r\n";
        }
        $body .= "END:VCALENDAR\r\n";

        $file = isset($assoc_args['output']) ? (string) $assoc_args['output'] : '/tmp/gratis-events.ics';

        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ($wp_filesystem && $wp_filesystem->put_contents($file, $body)) {
            \WP_CLI::success('Exported ' . count($events) . " events to {$file}");
            return;
        }

        \WP_CLI::error("Could not write to {$file}");
    }
}
