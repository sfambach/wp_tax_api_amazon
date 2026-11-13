<?php declare(strict_types=1);

namespace TaxApiAmazon\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiAmazon\Core\AmazonExportReader;

/**
 * The owner's yearly export (synthetic sample in fixtures): one delivered proposal per order, no invented date, the picture address with each line.
 */
final class AmazonExportReaderTest extends TestCase
{
    /** @return list<string> */
    private static function files(): array
    {
        return [
            (string) file_get_contents(__DIR__ . '/fixtures/amazon_2026_positionen.csv'),
            (string) file_get_contents(__DIR__ . '/fixtures/amazon_2026_bestellungen.csv'),
        ];
    }

    public function testEveryOrderIsOneDeliveredProposalOldestFirst(): void
    {
        $orders = array_map(static fn ($order): array => $order->toArray(), (new AmazonExportReader())->read(self::files()));

        self::assertSame(['302-2222222-2222222', '302-1111111-1111111'], array_column($orders, 'order_number'));
        self::assertSame(['Amazon', 'delivered', '2026-02-03', '0', '12.49', null, null], [$orders[1]['platform'], $orders[1]['kind'],
            $orders[1]['order_date'], $orders[1]['shipping_cost'], $orders[1]['total'], $orders[1]['shipped_date'], $orders[1]['counterparty']]);
        self::assertSame(['amazon-export', 'amazon-export:302-1111111-1111111'], [$orders[1]['source'], $orders[1]['external_id']]);
        self::assertSame(['3.99', '22.99'], [$orders[0]['shipping_cost'], $orders[0]['total']]);
    }

    public function testLinesKeepTheirPositionAndAPriceFromTheSum(): void
    {
        $order = (new AmazonExportReader())->read(self::files())[1]->toArray();

        self::assertSame([
            ['title' => 'USB-C Kabel 1 m', 'quantity' => 1.0, 'unit_price' => '5.49', 'article_number' => 'B000000001', 'image_url' => 'https://example.invalid/i/1.jpg'],
            ['title' => 'Lötzinn 0,8 mm; 100 g', 'quantity' => 2.0, 'unit_price' => '3.5', 'article_number' => 'B000000002', 'image_url' => 'https://example.invalid/i/2.jpg'],
        ], $order['lines']);
    }

    public function testAnItemWithoutAsinHasNoArticleNumberAndNoPicture(): void
    {
        $order = (new AmazonExportReader())->read(self::files())[0]->toArray();

        self::assertSame([['title' => 'ESP32 DevKit', 'quantity' => 1.0, 'unit_price' => '5', 'article_number' => null]], $order['lines']);
    }

    public function testSinceLeavesOutOlderOrders(): void
    {
        self::assertSame(['302-1111111-1111111'], array_map(static fn ($order): string => $order->orderNumber,
            (new AmazonExportReader('2026-01-01'))->read(self::files())));
    }

    public function testItemsAloneAreEnoughWithoutTotals(): void
    {
        $order = (new AmazonExportReader())->read([self::files()[0]])[0];

        self::assertSame([null, null], [$order->shippingCost, $order->total]);
    }

    public function testAnotherFileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AmazonExportReader())->read(["Order ID,Order Date\n1,2026-01-01\n"]);
    }

    public function testOrdersWithoutItemsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AmazonExportReader())->read([self::files()[1]]);
    }
}
