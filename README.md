# Taxmod Amazon

Amazon connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin).

**Status 0.0.2 (needs modeller 0.2.89 and [Taxmod Mail](https://github.com/sfambach/wp_tax_mail) 0.6.8 or later):
reads order confirmations (several orders of one checkout), shipping, delivery and cancellation mails from the mailbox of Taxmod Mail and hands them to the modeller's inbox. The mails name no tracking number and no shipping cost.
The orders from before the mails come from the owner's own export (one pair of CSV files per year), uploaded in this module's tab.**

Every shop connection is a module of its own, whether it reads mails or an API; Taxmod Mail only fetches and files
the mails (D-1006, [concept](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md)).
The reader moved here from Taxmod Mail unchanged.

## Setup

1. Install and set up Taxmod Mail (mailbox access).
2. Activate this module. *Taxonomy Modeller → Configuration → Amazon* shows under «Mail» which mails it reads.
3. For older orders: in the same tab under «Order export», upload `amazon_<year>_positionen.csv` and `amazon_<year>_bestellungen.csv` (several years at once, optionally from a date).
   Each fetch of Taxmod Mail hands the next 100 orders to the inbox; an order the model already has is not proposed again.

## Order export

- One proposal per order, platform «Amazon», the ASIN as article number, shipping and total from the order file.
- The export has no status and no shipping or delivery date: the orders count as delivered, and no date is written for it (modeller D-1350).
- Taking an order in the inbox is the confirmation; stock and goods receipt follow only from there.
- The export's picture addresses are not handed on; product pictures come from the export's own archive, for internal use only.

## Development

`composer install`, then `vendor/bin/phpunit` (core suite, no WordPress). The sample mails in `tests/Core/fixtures`
are synthetic.

## License

Copyright (C) 2026 Stefan Fambach (SteFam), https://www.fambach.net — [PolyForm Noncommercial 1.0.0](LICENSE.md).
Commercial use needs the owner's prior consent.

## Authors

- **Stefan Fambach** (SteFam) – idea and concept, review and testing – https://www.fambach.net
- **Claude** (Anthropic AI) – implementation, via Claude Code

## AI disclosure

This project is built with AI assistance. Stefan Fambach had the idea, wrote the concept and reviews and tests every change; the implementation (code and documentation) is written largely by Claude (Anthropic) via Claude Code.
