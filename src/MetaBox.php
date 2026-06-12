<?php declare(strict_types=1);

namespace Gratis\Events;

defined('ABSPATH') || exit;

/**
 * Per-event details meta box: dates, venue, capacity and recurrence.
 */
final class MetaBox {

    private const NONCE_ACTION = 'gratis_events_save_meta';
    private const NONCE_FIELD  = 'gratis_events_meta_nonce';

    /** Register the meta box. */
    public static function register(): void {
        add_meta_box(
            'gratis-events-details',
            __('Event Details', 'gratis-events'),
            [__CLASS__, 'render'],
            EventPostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    /** Render the meta box. */
    public static function render(\WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $start     = (string) get_post_meta($post->ID, '_start_date', true);
        $end       = (string) get_post_meta($post->ID, '_end_date', true);
        $venue     = (string) get_post_meta($post->ID, '_venue', true);
        $capacity  = (int) get_post_meta($post->ID, '_capacity', true);
        $rsvp      = (int) get_post_meta($post->ID, '_rsvp_count', true);
        $recurring = (string) get_post_meta($post->ID, '_recurring', true) ?: 'none';

        $recurrences = [
            'none'    => __('Does not repeat', 'gratis-events'),
            'daily'   => __('Daily', 'gratis-events'),
            'weekly'  => __('Weekly', 'gratis-events'),
            'monthly' => __('Monthly', 'gratis-events'),
        ];

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="ge_start">' . esc_html__('Start date', 'gratis-events') . '</label></th><td>';
        echo '<input type="datetime-local" id="ge_start" name="ge_start" value="' . esc_attr(self::toLocalInput($start)) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="ge_end">' . esc_html__('End date', 'gratis-events') . '</label></th><td>';
        echo '<input type="datetime-local" id="ge_end" name="ge_end" value="' . esc_attr(self::toLocalInput($end)) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="ge_venue">' . esc_html__('Venue', 'gratis-events') . '</label></th><td>';
        echo '<input type="text" id="ge_venue" class="regular-text" name="ge_venue" value="' . esc_attr($venue) . '"></td></tr>';

        echo '<tr><th scope="row"><label for="ge_capacity">' . esc_html__('Capacity', 'gratis-events') . '</label></th><td>';
        echo '<input type="number" min="0" id="ge_capacity" class="small-text" name="ge_capacity" value="' . esc_attr((string) $capacity) . '"> ';
        echo '<span class="description">' . esc_html__('0 = unlimited.', 'gratis-events') . '</span></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('RSVPs', 'gratis-events') . '</th><td><strong>' . absint($rsvp) . '</strong></td></tr>';

        echo '<tr><th scope="row"><label for="ge_recurring">' . esc_html__('Recurrence', 'gratis-events') . '</label></th><td>';
        echo '<select id="ge_recurring" name="ge_recurring">';
        foreach ($recurrences as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($recurring, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '</tbody></table>';
    }

    /** Normalise a stored date to a value an `<input type="datetime-local">` accepts. */
    private static function toLocalInput(string $value): string {
        if ('' === $value) {
            return '';
        }
        $ts = strtotime($value);
        return false === $ts ? $value : gmdate('Y-m-d\TH:i', $ts);
    }

    /** Persist the meta box values. */
    public static function save(int $post_id, \WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        $nonce = isset($_POST[self::NONCE_FIELD])
            ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]))
            : '';
        if ('' === $nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id) || EventPostType::POST_TYPE !== $post->post_type) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
        update_post_meta($post_id, '_start_date', isset($_POST['ge_start']) ? sanitize_text_field(wp_unslash($_POST['ge_start'])) : '');
        update_post_meta($post_id, '_end_date', isset($_POST['ge_end']) ? sanitize_text_field(wp_unslash($_POST['ge_end'])) : '');
        update_post_meta($post_id, '_venue', isset($_POST['ge_venue']) ? sanitize_text_field(wp_unslash($_POST['ge_venue'])) : '');
        update_post_meta($post_id, '_capacity', isset($_POST['ge_capacity']) ? absint(wp_unslash($_POST['ge_capacity'])) : 0);

        $recurring = isset($_POST['ge_recurring']) ? sanitize_key(wp_unslash($_POST['ge_recurring'])) : 'none';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $recurring = in_array($recurring, ['none', 'daily', 'weekly', 'monthly'], true) ? $recurring : 'none';
        update_post_meta($post_id, '_recurring', $recurring);
    }
}
