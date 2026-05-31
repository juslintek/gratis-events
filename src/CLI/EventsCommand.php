<?php declare(strict_types=1);
namespace Gratis\Events\CLI;

final class EventsCommand
{
    /**
     * List upcoming events.
     * @when after_wp_load
     */
    public function list($args, $assoc_args): void
    {
        $events = get_posts(['post_type' => 'gratis_event', 'numberposts' => 20, 'meta_key' => '_start_date', 'orderby' => 'meta_value', 'order' => 'ASC',
            'meta_query' => [['key' => '_start_date', 'value' => gmdate('Y-m-d'), 'compare' => '>=']]]);
        if (empty($events)) { \WP_CLI::log("No upcoming events."); return; }
        $rows = array_map(fn($e) => [
            'ID' => $e->ID, 'Title' => $e->post_title,
            'Date' => get_post_meta($e->ID, '_start_date', true),
            'Venue' => get_post_meta($e->ID, '_venue', true) ?: '-',
            'RSVP' => get_post_meta($e->ID, '_rsvp_count', true) ?: 0,
        ], $events);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Title', 'Date', 'Venue', 'RSVP']);
    }

    /**
     * Export events as iCal.
     * [--output=<file>] : Output file path.
     * @subcommand ical
     * @when after_wp_load
     */
    public function ical($args, $assoc_args): void
    {
        $events = get_posts(['post_type' => 'gratis_event', 'numberposts' => -1]);
        $cal = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Gratis//Events//EN\r\n";
        foreach ($events as $e) {
            $start = get_post_meta($e->ID, '_start_date', true);
            if (!$start) continue;
            $cal .= "BEGIN:VEVENT\r\nDTSTART:" . gmdate('Ymd\THis\Z', strtotime($start)) . "\r\n";
            $cal .= "SUMMARY:" . $e->post_title . "\r\nEND:VEVENT\r\n";
        }
        $cal .= "END:VCALENDAR\r\n";
        $file = $assoc_args['output'] ?? '/tmp/gratis-events.ics';
        file_put_contents($file, $cal);
        \WP_CLI::success("Exported " . count($events) . " events to {$file}");
    }
}
