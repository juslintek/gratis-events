<?php declare(strict_types=1);

namespace Gratis\Events;

defined('ABSPATH') || exit;

/**
 * Global settings for Gratis Events.
 */
final class Settings {

    private const GROUP = 'gratis_events_settings_group';
    private const PAGE  = 'gratis-events-settings';

    private const OPT_PER_PAGE = 'gratis_events_archive_per_page';
    private const OPT_HIDE_PAST = 'gratis_events_hide_past';

    /** Register the settings submenu under the Events menu. */
    public static function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=' . EventPostType::POST_TYPE,
            __('Event Settings', 'gratis-events'),
            __('Settings', 'gratis-events'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /** Register options. */
    public static function register_settings(): void {
        register_setting(self::GROUP, self::OPT_PER_PAGE, [
            'type'              => 'integer',
            'sanitize_callback' => static fn($v) => max(1, min(100, absint($v) ?: 10)),
            'default'           => 10,
        ]);
        register_setting(self::GROUP, self::OPT_HIDE_PAST, [
            'type'              => 'boolean',
            'sanitize_callback' => static fn($v) => (bool) $v,
            'default'           => true,
        ]);
    }

    /** Events shown per archive page. */
    public static function perPage(): int {
        return max(1, min(100, (int) get_option(self::OPT_PER_PAGE, 10)));
    }

    /** Whether past events are hidden from the archive. */
    public static function hidePast(): bool {
        return (bool) get_option(self::OPT_HIDE_PAST, true);
    }

    /** Render the settings screen. */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-events'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Event Settings', 'gratis-events') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="ge_per_page">' . esc_html__('Events per archive page', 'gratis-events') . '</label></th><td>';
        echo '<input type="number" min="1" max="100" id="ge_per_page" class="small-text" name="' . esc_attr(self::OPT_PER_PAGE) . '" value="' . esc_attr((string) self::perPage()) . '"></td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Hide past events', 'gratis-events') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPT_HIDE_PAST) . '" value="1"' . checked(self::hidePast(), true, false) . '> ';
        echo esc_html__('Exclude events whose start date has passed from listings.', 'gratis-events') . '</label></td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save settings', 'gratis-events'));
        echo '</form>';
        echo '</div>';
    }
}
