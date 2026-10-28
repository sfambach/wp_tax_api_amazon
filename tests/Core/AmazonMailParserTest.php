<?php declare(strict_types=1);

namespace TaxApiAmazon\Tests\Core;

use PHPUnit\Framework\TestCase;
use TaxApiAmazon\Core\AmazonMailParser;
use Taxmod\Core\Import\Mail\MimeParser;

/**
 * Each expectation is what the Python importer (legacy/ in Taxmod Mail) read from the same synthetic mail.
 */
final class AmazonMailParserTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function read(string $fixture): array
    {
        $parser = new AmazonMailParser('0.0.1');
        $mail = (new MimeParser())->parse(1, (string) file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.eml'));
        self::assertTrue($parser->recognises($mail), 'not recognised: ' . $fixture);

        return $parser->parse($mail)->toArray();
    }

    public function testAmazonConfirmationHoldsEveryOrderWithFullTitles(): void
    {
        $mail = (new MimeParser())->parse(1, (string) file_get_contents(__DIR__ . '/fixtures/z1.eml'));
        $parser = new AmazonMailParser('0.0.1');
        self::assertTrue($parser->recognises($mail));
        $orders = array_map(static fn ($order): array => $order->toArray(), $parser->parseAll($mail));

        self::assertSame(['304-1111111-2222222', '304-3333333-4444444'], array_column($orders, 'order_number'));
        self::assertSame(['ordered', '2026-10-21', '9.98', 'Amazon.de'], [$orders[0]['kind'], $orders[0]['order_date'], $orders[0]['total'], $orders[0]['counterparty']]);
        self::assertSame(['4.49', '0'], [$orders[1]['total'], $orders[1]['shipping_cost']]);
        self::assertSame([['title' => 'ESP32 DevKit V1 Entwicklungsboard mit CP2102 | 2 Stück', 'quantity' => 2.0, 'unit_price' => '4.99', 'article_number' => null]], $orders[0]['lines']);
        self::assertSame([['title' => 'USB-C Kabel 1 m', 'quantity' => 1.0, 'unit_price' => '4.49', 'article_number' => null]], $orders[1]['lines']);
    }

    public function testAmazonDeliveryIsAStatusWithoutOrderDate(): void
    {
        $order = self::read('z2');

        self::assertSame(['304-1111111-2222222', 'delivered', null], [$order['order_number'], $order['kind'], $order['order_date']]);
    }

    public function testItNamesWhatItReads(): void
    {
        self::assertNotSame([], (new AmazonMailParser('1.2.3'))->kinds());
        self::assertSame('1.2.3', (new AmazonMailParser('1.2.3'))->version());
    }
}
