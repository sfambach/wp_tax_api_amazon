<?php declare(strict_types=1);

namespace TaxApiAmazon\WordPress;

use TaxApiAmazon\Core\AmazonExportReader;
use TaxApiAmazon\Core\AmazonMailParser;
use Taxmod\Core\Import\Mail\OrderMailParser;

/**
 * The boundary: registers the Amazon mail reader with Taxmod Mail (filter `taxmail_parsers`) and a tab on the
 * modeller's Configuration page whose part «Mail» names what it reads (D-1006, D-1007). The tab also takes the owner's
 * own order export; its orders go into Taxmod Mail's export queue, which hands them to the inbox a slice per fetch, so
 * the inbox's duplicate check and the owner's take decide what is written (architecture thread, 2026-11-13).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class Plugin
{
    public const VERSION = '0.0.3';

    /** The REST route an agent fills the queue through, with the files' text instead of an upload. */
    public const ROUTE_NAMESPACE = 'taxapiamazon/v1';
    public const ROUTE           = '/export';
    public const TAB     = 'amazon';

    /** The admin-post action and the id of its form, printed outside the Configuration page's own form. */
    public const UPLOAD = 'taxapiamazon_export';

    /** What the last upload did, for the page after the redirect. */
    private const UPLOAD_DONE = 'taxapiamazon_export_done_';

    private const CAPABILITY = 'manage_options';

    /** The largest file taken; the biggest year of his export is under 100 KB. */
    private const MAX_BYTES = 10 * MB_IN_BYTES;

    /** Taxmod Mail's queue of uploaded export orders (0.6.0 or later). */
    private const QUEUE = 'TaxMail\\WordPress\\ExportQueue';

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

            add_action('admin_post_' . self::UPLOAD, self::act(self::handleUpload(...)));
            add_action('admin_footer', self::uploadForm(...));
            add_action('rest_api_init', self::registerRoute(...));

            $reader = new AmazonMailParser(self::VERSION);
            add_filter('taxmail_parsers', static fn (array $parsers): array => [...$parsers, $reader]);
            add_filter('taxmod_config_tabs', static fn (array $tabs): array => $tabs + [self::TAB => [
                'title'   => 'Amazon',
                'version' => self::VERSION,
                'reader'  => $reader,
                'guide'   => self::guide(...),
                'render'  => self::render(...),
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
            . esc_html__('The mails name no tracking number and no shipping cost. The orders from before the mails come from the export: upload its CSV files under «Order export».', 'taxapiamazon') . '</p>';
    }

    /** The part «Order export» of the tab; its fields belong to the form uploadForm() prints after the page's own one. */
    public static function render(): string
    {
        $html = '<h2>' . esc_html__('Order export', 'taxapiamazon') . '</h2><p>'
            . esc_html__('The orders from before the mails: upload the files «amazon_<year>_positionen.csv» and «amazon_<year>_bestellungen.csv» of your export, several at once. Each fetch of the mail import hands the next 100 orders to the inbox; an order the model has already is not proposed again. Taking an order in the inbox is the confirmation — stock and goods receipt follow only from there. The export has no shipping or delivery date, so none is written.', 'taxapiamazon')
            . '</p>';
        $done = get_transient(self::UPLOAD_DONE . get_current_user_id());
        delete_transient(self::UPLOAD_DONE . get_current_user_id());

        if (is_string($done)) {
            $html .= '<div class="notice notice-info inline"><p>' . esc_html($done) . '</p></div>';
        }

        if (! class_exists(self::QUEUE)) {
            return $html . '<p>' . esc_html__('Needs Taxmod Mail 0.6.0 or later: its fetch hands the orders to the inbox.', 'taxapiamazon') . '</p>';
        }

        $form = esc_attr(self::UPLOAD);

        return $html . '<p><button type="submit" class="button button-secondary" form="' . $form . '">' . esc_html__('Read the files', 'taxapiamazon') . '</button></p>'
            . '<table class="form-table" role="presentation">'
            . '<tr><th scope="row"><label for="taxapiamazon-export-files">' . esc_html__('Files', 'taxapiamazon') . '</label></th>'
            . '<td><input type="file" id="taxapiamazon-export-files" name="export[]" multiple accept=".csv,text/csv" form="' . $form . '"></td></tr>'
            . '<tr><th scope="row"><label for="taxapiamazon-export-since">' . esc_html__('Ordered from', 'taxapiamazon') . '</label></th>'
            . '<td><input type="date" id="taxapiamazon-export-since" name="since" form="' . $form . '"> '
            . '<span class="description">' . esc_html__('Empty: every year.', 'taxapiamazon') . '</span></td></tr></table>';
    }

    /** The upload form, empty, after the page: a form cannot hold another. */
    public static function uploadForm(): void
    {
        // Read-only look at which screen this is; nothing is acted on.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

        if ($page !== 'taxmod-settings' || $tab !== self::TAB || ! current_user_can(self::CAPABILITY)) {
            return;
        }

        echo '<form id="' . esc_attr(self::UPLOAD) . '" method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(self::UPLOAD) . '">' . wp_nonce_field(self::UPLOAD, '_wpnonce', true, false) . '</form>';
    }

    /**
     * CD-5: capability, nonce, then each file checked (arrived, CSV, size) before it is read. Only the proposals are kept
     * in the queue; the uploads are PHP's temporary files, gone after the request.
     */
    public static function handleUpload(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            self::refuse(__('You may not import orders.', 'taxapiamazon'));
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_POST['_wpnonce'] ?? ''))), self::UPLOAD)) {
            self::refuse(__('The form has expired. Please try again.', 'taxapiamazon'));
        }

        if (! class_exists(self::QUEUE)) {
            self::refuse(__('Needs Taxmod Mail 0.6.0 or later: its fetch hands the orders to the inbox.', 'taxapiamazon'));
        }

        $since = isset($_POST['since']) ? sanitize_text_field(wp_unslash((string) $_POST['since'])) : '';
        $since = preg_match('/^\d{4}-\d{2}-\d{2}$/', $since) === 1 ? $since : null;
        $texts = [];

        foreach (self::uploads() as $file) {
            $name = sanitize_file_name((string) $file['name']);

            if ((int) $file['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file((string) $file['tmp_name'])) {
                /* translators: %s: file name */
                self::refuse(sprintf(__('%s did not arrive.', 'taxapiamazon'), $name));
            }

            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv' || (int) $file['size'] > self::MAX_BYTES) {
                /* translators: %s: file name */
                self::refuse(sprintf(__('%s is not a CSV file of the export, or larger than 10 MB.', 'taxapiamazon'), $name));
            }

            $texts[] = (string) file_get_contents((string) $file['tmp_name']);
        }

        if ($texts === []) {
            self::refuse(__('No file arrived.', 'taxapiamazon'));
        }

        try {
            $read = self::queue($texts, $since);
        } catch (\InvalidArgumentException | \RuntimeException $failure) {
            self::refuse($failure->getMessage());
        }

        set_transient(self::UPLOAD_DONE . get_current_user_id(), sprintf(
            /* translators: 1: orders, 2: items */
            __('%1$d orders with %2$d items read; each fetch of the mail import hands the next 100 to the inbox.', 'taxapiamazon'),
            $read['orders'],
            $read['items']
        ), 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(self::tabUrl());
        exit;
    }

    /**
     * POST taxapiamazon/v1/export with `files` (the CSV texts) and optionally `since` («Y-m-d») and `fetch` (true: run the
     * inbox's fetch right away, a slice of 100 per run, until the queue is handed on). The same as the upload, for an agent
     * that holds the files but no browser (his word, 2026-11-13: «Ich dachte, du importierst einfach die CSV»).
     */
    public static function registerRoute(): void
    {
        register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE, [
            'methods'             => 'POST',
            'permission_callback' => static fn (): bool => current_user_can(self::CAPABILITY),
            'args'                => [
                'files' => ['type' => 'array', 'items' => ['type' => 'string'], 'required' => true],
                'since' => ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$'],
                'fetch' => ['type' => 'boolean', 'default' => false],
            ],
            'callback'            => static function (\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
                if (! class_exists(self::QUEUE)) {
                    return new \WP_Error('taxapiamazon_no_mail', __('Needs Taxmod Mail 0.6.0 or later: its fetch hands the orders to the inbox.', 'taxapiamazon'), ['status' => 409]);
                }

                $texts = array_values(array_filter(array_map('strval', (array) $request->get_param('files')), static fn (string $text): bool => $text !== ''));
                $since = $request->get_param('since');

                try {
                    $read = self::queue($texts, is_string($since) && $since !== '' ? $since : null);
                } catch (\InvalidArgumentException | \RuntimeException $failure) {
                    return new \WP_Error('taxapiamazon_export', $failure->getMessage(), ['status' => 400]);
                }

                $read['filed'] = (bool) $request->get_param('fetch') ? self::fetch() : null;
                $queue = self::QUEUE;

                return new \WP_REST_Response($read + ['queue' => $queue::progress()]);
            },
        ]);
    }

    /**
     * Reads the files into Taxmod Mail's export queue.
     *
     * @param list<string> $texts
     * @return array{orders: int, items: int}
     * @throws \InvalidArgumentException when a text is not a file of the export
     * @throws \RuntimeException when the queue cannot be stored
     */
    private static function queue(array $texts, ?string $since): array
    {
        try {
            $proposals = (new AmazonExportReader($since))->read($texts);
        } catch (\InvalidArgumentException $failure) {
            /* translators: %s: why */
            throw new \InvalidArgumentException(sprintf(__('That is not the export: %s', 'taxapiamazon'), $failure->getMessage()), 0, $failure);
        }

        $queue = self::QUEUE;

        if (! $queue::store($proposals)) {
            throw new \RuntimeException(__('The orders could not be stored under wp-content.', 'taxapiamazon'));
        }

        return ['orders' => count($proposals), 'items' => array_sum(array_map(static fn ($proposal): int => count($proposal->lines), $proposals))];
    }

    /** The inbox's fetch, run until the queue is handed on — at most as many runs as slices of 100, plus one. */
    private static function fetch(): int
    {
        if (! class_exists(\Taxmod\WordPress\Import\Inbox::class)) {
            return 0;
        }

        $queue = self::QUEUE;
        $inbox = new \Taxmod\WordPress\Import\Inbox();
        $filed = 0;

        for ($run = 0; $run < 20; $run++) {
            $progress = $queue::progress();

            if ($progress['handed'] >= $progress['total']) {
                break;
            }

            $filed += $inbox->runAll();
        }

        return $filed;
    }

    /**
     * The uploaded files of «export[]», one entry each.
     *
     * @return list<array{name: string, tmp_name: string, error: int, size: int}>
     */
    private static function uploads(): array
    {
        $files = $_FILES['export'] ?? null;

        if (! is_array($files) || ! is_array($files['name'] ?? null)) {
            return [];
        }

        $uploads = [];

        foreach (array_keys($files['name']) as $key) {
            if ((int) ($files['error'][$key] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $uploads[] = [
                'name'     => (string) $files['name'][$key],
                'tmp_name' => (string) ($files['tmp_name'][$key] ?? ''),
                'error'    => (int) ($files['error'][$key] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($files['size'][$key] ?? 0),
            ];
        }

        return $uploads;
    }

    /**
     * The handler as an act (D-1254): an exception becomes a message on the tab with the way back, never an empty page.
     * ListPage is named only here, inside the handler, so the boot does not compile it (D-1244).
     */
    private static function act(\Closure $handler): \Closure
    {
        return static function () use ($handler): void {
            if (! method_exists(\Taxmod\WordPress\Admin\ListPage::class, 'act')) {
                $handler();

                return;
            }

            \Taxmod\WordPress\Admin\ListPage::act(static function () use ($handler): string {
                $handler();

                return '';
            }, self::tabUrl());
        };
    }

    /** A refusal as a message on the tab instead of a bare error page (D-1254). */
    private static function refuse(string $message): never
    {
        if (method_exists(\Taxmod\WordPress\Admin\ListPage::class, 'fail')) {
            \Taxmod\WordPress\Admin\ListPage::fail($message, self::tabUrl());
        }

        wp_die(esc_html($message), '', ['response' => 403, 'back_link' => true]);
    }

    private static function tabUrl(): string
    {
        return admin_url('admin.php?page=taxmod-settings&tab=' . self::TAB);
    }
}
