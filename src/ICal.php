<?php declare(strict_types=1);

namespace Gratis\Events;

defined('ABSPATH') || exit;

/**
 * Single-event iCal (.ics) export, triggered by a `?gratis_ical=<id>` query.
 */
final class ICal {

    /** Stream an .ics file when the export query var is present. */
    public static function maybeExport(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only export, no state change.
        if (!isset($_GET['gratis_ical'])) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only export, no state change.
        $event_id = absint(wp_unslash($_GET['gratis_ical']));

        $event = get_post($event_id);
        if (!$event || EventPostType::POST_TYPE !== $event->post_type) {
            return;
        }

        $start = (string) get_post_meta($event_id, '_start_date', true);
        $end   = (string) get_post_meta($event_id, '_end_date', true) ?: $start;
        $venue = (string) get_post_meta($event_id, '_venue', true);

        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-' . $event_id . '.ics"');

        echo wp_kses_post(self::build($event->post_title, $start, $end, $venue, $event_id));
        exit;
    }

    /** Build a single-event VCALENDAR document. */
    public static function build(string $title, string $start, string $end, string $venue, int $uid): string {
        $startTs = strtotime($start) ?: time();
        $endTs   = strtotime($end) ?: $startTs;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Gratis//Events//EN',
            'BEGIN:VEVENT',
            'UID:gratis-event-' . $uid . '@' . wp_parse_url(home_url(), PHP_URL_HOST),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . gmdate('Ymd\THis\Z', $startTs),
            'DTEND:' . gmdate('Ymd\THis\Z', $endTs),
            'SUMMARY:' . self::escape($title),
            'LOCATION:' . self::escape($venue),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    /** Escape a value for an iCal text field (RFC 5545). */
    public static function escape(string $value): string {
        $value = str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $value);
        return $value;
    }
}
