<?php declare(strict_types=1);

namespace TaxApiAmazon\Core;

use Taxmod\Core\Import\Money;
use Taxmod\Core\Import\OrderProposal;
use Taxmod\Core\Import\ProposalKind;
use Taxmod\Core\Import\ProposalLine;

/**
 * The owner's own Amazon export, one pair of files per year: «amazon_<year>_bestellungen.csv» (one row per order) and
 * «amazon_<year>_positionen.csv» (one row per technical item; the export already left out what is not material), read
 * into one proposal per order. The orders are delivered — the export has no status and no date of shipping or delivery,
 * so none is written (D-1350); the product picture's address is not handed on, the pictures come from the export's own
 * archive (architecture thread, 2026-11-13).
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/konzept.md
 */
final class AmazonExportReader
{
    /** Same as the reader of Amazon's «Order History.csv», so an order read by both is one in the inbox. */
    public const SOURCE = 'amazon-export';

    private const ORDER_COLUMNS = ['Bestellnr', 'Bestelldatum', 'Versand €', 'Gesamt bezahlt €'];

    private const ITEM_COLUMNS = ['Bestellnr', 'Bestelldatum', 'Pos', 'Artikel', 'Einzelpreis €', 'Menge', 'Positionssumme €', 'ASIN'];

    /** @param ?string $since first order date kept, «Y-m-d»; null keeps all */
    public function __construct(private readonly ?string $since = null)
    {
    }

    /**
     * @param list<string> $files the texts of the uploaded files, orders and items in any order and of any years
     * @return list<OrderProposal> oldest first
     * @throws \InvalidArgumentException when a file is neither of the two or no file holds items
     */
    public function read(array $files): array
    {
        $orders = [];
        $items = [];

        foreach ($files as $text) {
            [$header, $rows] = self::rows($text);

            if (array_diff(self::ITEM_COLUMNS, $header) === []) {
                foreach ($rows as $row) {
                    $items[$row['Bestellnr']][] = $row;
                }
            } elseif (array_diff(self::ORDER_COLUMNS, $header) === []) {
                foreach ($rows as $row) {
                    $orders[$row['Bestellnr']] = $row;
                }
            } else {
                throw new \InvalidArgumentException('not a file of the Amazon export: columns missing');
            }
        }

        if ($items === []) {
            throw new \InvalidArgumentException('no file with the items («…_positionen.csv»)');
        }

        $proposals = [];

        foreach ($items as $number => $lines) {
            $date = substr($lines[0]['Bestelldatum'], 0, 10);

            if ($this->since === null || $date >= $this->since) {
                $proposals[] = self::proposal((string) $number, $date, $lines, $orders[$number] ?? null);
            }
        }

        usort($proposals, static fn (OrderProposal $a, OrderProposal $b): int => [$a->orderDate, $a->orderNumber] <=> [$b->orderDate, $b->orderNumber]);

        return $proposals;
    }

    /**
     * @param non-empty-list<array<string, string>> $items
     * @param ?array<string, string> $order
     */
    private static function proposal(string $number, string $date, array $items, ?array $order): OrderProposal
    {
        usort($items, static fn (array $a, array $b): int => (int) $a['Pos'] <=> (int) $b['Pos']);
        $lines = [];

        foreach ($items as $item) {
            $quantity = max(1, (int) $item['Menge']);
            $unit = self::amount($item['Einzelpreis €']);

            // A row without a unit price may still carry its sum.
            if ($unit === null && ($sum = Money::toUnits(self::amount($item['Positionssumme €']))) !== null) {
                $unit = Money::format(intdiv($sum, $quantity));
            }

            $lines[] = new ProposalLine(trim($item['Artikel']), (float) $quantity, $unit, self::given($item['ASIN']));
        }

        return new OrderProposal(
            'Amazon',
            $number,
            ProposalKind::Delivered,
            $date,
            'EUR',
            $lines,
            $order === null ? null : self::amount($order['Versand €']),
            $order === null ? null : self::amount($order['Gesamt bezahlt €']),
            source: self::SOURCE,
            externalId: self::SOURCE . ':' . $number,
        );
    }

    /**
     * @return array{0: list<string>, 1: list<array<string, string>>}
     */
    private static function rows(string $text): array
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text);
        rewind($handle);
        $header = fgetcsv($handle, null, ';', '"', '');
        $header = is_array($header) ? array_map(static fn ($name): string => trim((string) $name), $header) : [];
        $rows = [];

        while (($values = fgetcsv($handle, null, ';', '"', '')) !== false) {
            if (count($values) !== count($header)) {
                continue;
            }

            $row = array_combine($header, array_map('strval', $values));

            if (trim($row['Bestellnr'] ?? '') !== '') {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return [$header, $rows];
    }

    /** «49,93» → «49.93»; empty or not a number → null. */
    private static function amount(string $value): ?string
    {
        $value = str_replace(',', '.', trim($value));

        return preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1 ? Money::format((int) Money::toUnits($value)) : null;
    }

    private static function given(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
