# src/Core

The Amazon mail reader (`AmazonMailParser`): recognises Amazon's mails and reads order confirmations (several orders of one checkout), shipping, delivery and cancellation mails into an order proposal.
Plain PHP 8.2, **no WordPress call**; it implements the modeller's contract `Taxmod\Core\Import\Mail\OrderMailParser`.
Concept: [`docs/pakete/import/module-schnitt.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md).
