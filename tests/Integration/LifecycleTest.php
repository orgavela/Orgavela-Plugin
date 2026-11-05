<?php
namespace UOP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use UOP\Core\Bootstrap;

final class LifecycleTest extends TestCase {
    private const PLUGIN = 'uop-core/uop-core.php';
    public function test_wordpress_activation_and_deactivation_preserve_data(): void {
        deactivate_plugins(self::PLUGIN, true);
        update_option('uop_test_sentinel', 'retained', false);
        self::assertSame([], Bootstrap::errors());
        ob_start();
        $result = activate_plugin(self::PLUGIN, '', false, false);
        $output = ob_get_clean();
        self::assertNull($result, is_wp_error($result) ? $result->get_error_message() : '');
        self::assertSame('', $output);
        self::assertTrue(is_plugin_active(self::PLUGIN));
        deactivate_plugins(self::PLUGIN, true);
        self::assertSame('retained', get_option('uop_test_sentinel'));
        delete_option('uop_test_sentinel');
    }
    public function test_unsupported_wordpress_activation_does_not_mutate_schema(): void {
        global $wpdb, $wp_version;
        Bootstrap::deactivate();
        $before = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'uop_') . '%'));
        $original = $wp_version;
        $handler = static fn () => static function ($message) { throw new \RuntimeException($message); };
        add_filter('wp_die_handler', $handler);
        try {
            $wp_version = '6.8';
            try { Bootstrap::activate(); self::fail('Unsupported activation succeeded'); }
            catch (\RuntimeException $e) { self::assertStringContainsString('wordpress_minimum', $e->getMessage()); }
            self::assertSame($before, $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'uop_') . '%')));
        } finally { $wp_version = $original; remove_filter('wp_die_handler', $handler); }
    }
}
