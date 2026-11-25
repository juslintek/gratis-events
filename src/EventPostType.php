<?php declare(strict_types=1);

namespace Gratis\Events;

defined('ABSPATH') || exit;

/**
 * Registers the event post type, taxonomy, meta, the RSVP REST endpoint and the
 * `[gratis_events]` listing shortcode.
 */
final class EventPostType {

    public const POST_TYPE = 'gratis_event';
    public const TAXONOMY  = 'event_category';

    /** Register the post type, taxonomy, meta and shortcodes. */
    public static function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels'      => [
                'name'          => __('Events', 'gratis-events'),
                'singular_name' => __('Event', 'gratis-events'),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-calendar',
            'supports'     => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'events'],
        ]);

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, [
            'labels'       => ['name' => __('Event Categories', 'gratis-events')],
            'hierarchical' => true,
            'public'       => true,
            'show_in_rest' => true,
        ]);

        $meta = [
            '_start_date' => 'string',
            '_end_date'   => 'string',
            '_venue'      => 'string',
            '_recurring'  => 'string',
            '_capacity'   => 'integer',
            '_rsvp_count' => 'integer',
        ];
        foreach ($meta as $key => $type) {
            register_post_meta(self::POST_TYPE, $key, [
                'type'          => $type,
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => static fn() => current_user_can('edit_posts'),
            ]);
        }

        add_shortcode('gratis_events', [__CLASS__, 'shortcode']);
        add_shortcode('gratis-events', [__CLASS__, 'shortcode']);
    }

    /** Register the RSVP REST route. */
    public static function registerAPI(): void {
        register_rest_route('gratis-events/v1', '/rsvp/(?P<event_id>\d+)', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => [__CLASS__, 'restRsvp'],
        ]);
    }

    /** Handle an RSVP, enforcing published status, rate limiting, and capacity. */
    public static function restRsvp(\WP_REST_Request $req): \WP_REST_Response {
        $event_id = (int) $req['event_id'];
        if (self::POST_TYPE !== get_post_type($event_id) || 'publish' !== get_post_status($event_id)) {
            return new \WP_REST_Response(['error' => __('Unknown event.', 'gratis-events')], 404);
        }

        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
        $ip       = (string) filter_var($ip, FILTER_VALIDATE_IP);
        $rate_key = 'gratis_ev_rsvp_' . $event_id . '_' . substr(hash('sha256', wp_salt('nonce') . '|' . gmdate('Y-m-d') . '|' . $ip), 0, 12);
        if (get_transient($rate_key)) {
            return new \WP_REST_Response(['error' => __('You have already RSVPed for this event recently.', 'gratis-events')], 429);
        }

        $raw      = (array) $req->get_json_params();
        $data     = [
            'name'  => sanitize_text_field((string) ($raw['name'] ?? '')),
            'email' => sanitize_email((string) ($raw['email'] ?? '')),
        ];
        $count    = (int) get_post_meta($event_id, '_rsvp_count', true);
        $capacity = (int) get_post_meta($event_id, '_capacity', true);

        if ($capacity && $count >= $capacity) {
            return new \WP_REST_Response(['error' => __('Event is full.', 'gratis-events')], 400);
        }

        set_transient($rate_key, 1, HOUR_IN_SECONDS);
        update_post_meta($event_id, '_rsvp_count', $count + 1);
        do_action('gratis_event_rsvp', $event_id, $data);

        return new \WP_REST_Response(['success' => true, 'attendees' => $count + 1]);
    }

    /**
     * Render an upcoming-events list.
     *
     * @param array<string,mixed>|string $atts Shortcode attributes.
     */
    public static function shortcode($atts): string {
        $atts = shortcode_atts([
            'limit'    => 10,
            'category' => '',
            'past'     => 'no',
        ], $atts, 'gratis_events');

        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => max(1, min(100, absint($atts['limit']))),
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- ordering events by their date.
            'meta_key'       => '_start_date',
            'orderby'        => 'meta_value',
            'order'          => 'yes' === $atts['past'] ? 'DESC' : 'ASC',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- date-window filter is the intended behaviour.
            'meta_query'     => [[
                'key'     => '_start_date',
                'value'   => gmdate('Y-m-d'),
                'compare' => 'yes' === $atts['past'] ? '<' : '>=',
            ],],
        ];

        if ('' !== $atts['category']) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- category-filtered event listing.
            $args['tax_query'] = [[
                'taxonomy' => self::TAXONOMY,
                'field'    => 'slug',
                'terms'    => sanitize_title((string) $atts['category']),
            ],];
        }

        $events = get_posts($args);
        if (empty($events)) {
            return '<p class="gratis-events-empty">' . esc_html__('No upcoming events.', 'gratis-events') . '</p>';
        }

        $out = '<ul class="gratis-events-list">';
        foreach ($events as $event) {
            $start = (string) get_post_meta($event->ID, '_start_date', true);
            $venue = (string) get_post_meta($event->ID, '_venue', true);

            $out .= '<li class="gratis-event">';
            $out .= '<a class="gratis-event__title" href="' . esc_url((string) get_permalink($event->ID)) . '">' . esc_html($event->post_title) . '</a>';
            if ('' !== $start) {
                $out .= ' <span class="gratis-event__date">' . esc_html(self::formatDate($start)) . '</span>';
            }
            if ('' !== $venue) {
                $out .= ' <span class="gratis-event__venue">' . esc_html($venue) . '</span>';
            }
            $out .= '</li>';
        }
        $out .= '</ul>';

        return $out;
    }

    /** Format a stored date string using the site's date format. */
    public static function formatDate(string $value): string {
        $ts = strtotime($value);
        if (false === $ts) {
            return $value;
        }
        return wp_date(get_option('date_format', 'Y-m-d'), $ts) ?: $value;
    }
}
