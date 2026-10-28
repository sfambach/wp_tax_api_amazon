# Taxmod Amazon

Amazon connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin).

**Status 0.0.1 (needs modeller 0.1.81 and [Taxmod Mail](https://github.com/sfambach/wp_tax_mail) 0.6.0 or later):
reads order confirmations (several orders of one checkout), shipping, delivery and cancellation mails from the mailbox of Taxmod Mail and hands them to the modeller's inbox. The mails name no tracking number and no shipping cost. The order history from before the mails is uploaded in the Mail module for now.**

Every shop connection is a module of its own, whether it reads mails or an API; Taxmod Mail only fetches and files
the mails (D-1006, [concept](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md)).
The reader moved here from Taxmod Mail unchanged.

## Setup

1. Install and set up Taxmod Mail (mailbox access).
2. Activate this module. *Taxonomy Modeller → Configuration → Amazon* shows under «Mail» which mails it reads.

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
