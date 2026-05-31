<?php declare(strict_types=1);
namespace Gratis\Events;

final class EventPostType
{
    public static function register(): void
    {
        register_post_type('gratis_event', [
            'labels' => ['name' => 'Events', 'singular_name' => 'Event'],
            'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-calendar',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'has_archive' => true, 'rewrite' => ['slug' => 'events'],
        ]);
        register_taxonomy('event_category', 'gratis_event', [
            'labels' => ['name' => 'Event Categories'], 'hierarchical' => true,
            'public' => true, 'show_in_rest' => true,
        ]);
        register_post_meta('gratis_event', '_start_date', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_event', '_end_date', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_event', '_venue', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_event', '_capacity', ['type' => 'integer', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_event', '_rsvp_count', ['type' => 'integer', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_event', '_recurring', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);

        // RSVP endpoint
        add_action('rest_api_init', function () {
            register_rest_route('gratis-events/v1', '/rsvp/(?P<event_id>\d+)', [
                'methods' => 'POST', 'permission_callback' => '__return_true',
                'callback' => function ($req) {
                    $event_id = (int) $req['event_id'];
                    $data = $req->get_json_params();
                    $count = (int) get_post_meta($event_id, '_rsvp_count', true);
                    $capacity = (int) get_post_meta($event_id, '_capacity', true);
                    if ($capacity && $count >= $capacity) return new \WP_REST_Response(['error' => 'Event full'], 400);
                    update_post_meta($event_id, '_rsvp_count', $count + 1);
                    do_action('gratis_event_rsvp', $event_id, $data);
                    return new \WP_REST_Response(['success' => true, 'attendees' => $count + 1]);
                },
            ]);
        });

        // iCal export
        add_action('template_redirect', function () {
            if (!isset($_GET['gratis_ical'])) return;
            $event_id = (int) $_GET['gratis_ical'];
            $event = get_post($event_id);
            if (!$event) return;
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="event.ics"');
            $start = get_post_meta($event_id, '_start_date', true);
            $end = get_post_meta($event_id, '_end_date', true) ?: $start;
            echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\n";
            echo "DTSTART:" . gmdate('Ymd\THis\Z', strtotime($start)) . "\r\n";
            echo "DTEND:" . gmdate('Ymd\THis\Z', strtotime($end)) . "\r\n";
            echo "SUMMARY:" . $event->post_title . "\r\n";
            echo "LOCATION:" . get_post_meta($event_id, '_venue', true) . "\r\n";
            echo "END:VEVENT\r\nEND:VCALENDAR\r\n";
            exit;
        });
    }
}
