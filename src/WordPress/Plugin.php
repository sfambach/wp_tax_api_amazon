<?php declare(strict_types=1);

namespace TaxApiAmazon\WordPress;

use TaxApiAmazon\Core\AmazonMailParser;
use Taxmod\Core\Import\Mail\OrderMailParser;

/**
 * The boundary: registers the Amazon mail reader with Taxmod Mail (filter `taxmail_parsers`) and a tab on the
 * modeller's Configuration page whose part «Mail» names what it reads (D-1006, D-1007).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class Plugin
{
    public const VERSION = '0.0.1';
    public const TAB     = 'amazon';

    public static function boot(): void
    {
        add_action('plugins_loaded', static function (): void {
            load_plugin_textdomain('taxapiamazon', false, dirname(plugin_basename(dirname(__DIR__, 2) . '/wp-tax-api-amazon.php')) . '/languages');

            // The contract lives in the modeller (0.1.81 or later); without it there is nothing to register with.
            if (! interface_exists(OrderMailParser::class)) {
                add_action('admin_notices', static fn () => print '<div class="notice notice-warning"><p><strong>Taxmod Amazon:</strong> '
                    . esc_html__('needs the Taxonomy Modeller 0.1.81 or later and Taxmod Mail 0.6.0 or later.', 'taxapiamazon') . '</p></div>');

                return;
            }

            $reader = new AmazonMailParser(self::VERSION);
            add_filter('taxmail_parsers', static fn (array $parsers): array => [...$parsers, $reader]);
            add_filter('taxmod_config_tabs', static fn (array $tabs): array => $tabs + [self::TAB => [
                'title'   => 'Amazon',
                'version' => self::VERSION,
                'reader'  => $reader,
                'guide'   => self::guide(...),
                'render'  => static fn (): string => '',
                'save'    => static function (array $post): void {
                },
            ]], 30);
        });
    }

    /** The box «How to connect» beside the tab (D-963). */
    public static function guide(): string
    {
        return '<ol><li>' . esc_html__('Let Amazon send its order mails to the mailbox of Taxmod Mail (or forward them there).', 'taxapiamazon') . '</li><li>'
            . esc_html__('Keep this module active: Taxmod Mail hands every Amazon mail to it, and the proposals appear in the Inbox.', 'taxapiamazon') . '</li></ol><p>'
            . esc_html__('The mails name no tracking number and no shipping cost. The order history from before the mails is uploaded in the Mail module for now.', 'taxapiamazon') . '</p>';
    }
}
