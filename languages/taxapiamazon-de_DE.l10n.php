<?php declare(strict_types=1);

// German texts of the user-visible software strings (D-1488 began with the choosers; all screens since D-1500).
return [
    'project-id-version' => 'Taxmod Amazon',
    'language'           => 'de_DE',
    'plural-forms'       => 'nplurals=2; plural=(n != 1);',
    'messages'           => [
        '%1$d orders with %2$d items read; each fetch of the mail import hands the next 100 to the inbox.' => '%1$d Bestellungen mit %2$d Positionen eingelesen; jeder Abruf des Mail-Imports gibt die nächsten 100 in den Eingang.',
        '%s did not arrive.'                                                             => '%s ist nicht angekommen.',
        '%s is not a CSV file of the export, or larger than 10 MB.'                      => '%s ist keine CSV-Datei des Exports oder größer als 10 MB.',
        'Empty: every year.'                                                             => 'Leer: jedes Jahr.',
        'Files'                                                                          => 'Dateien',
        'Keep this module active: Taxmod Mail hands every Amazon mail to it, and the proposals appear in the Inbox.' => 'Dieses Modul aktiv lassen: Taxmod Mail gibt ihm jede Amazon-Mail, die Vorschläge erscheinen in der Inbox.',
        'Let Amazon send its order mails to the mailbox of Taxmod Mail (or forward them there).' => 'Amazon die Bestellmails an das Postfach von Taxmod Mail schicken lassen (oder dorthin weiterleiten).',
        'Needs Taxmod Mail 0.6.0 or later: its fetch hands the orders to the inbox.'     => 'Braucht Taxmod Mail ab 0.6.0: sein Abruf gibt die Bestellungen in den Eingang.',
        'No file arrived.'                                                               => 'Keine Datei angekommen.',
        'Order export'                                                                   => 'Bestellexport',
        'Ordered from'                                                                   => 'Bestellt ab',
        'Read the files'                                                                 => 'Dateien einlesen',
        'That is not the export: %s'                                                     => 'Das ist nicht der Export: %s',
        'The form has expired. Please try again.'                                        => 'Das Formular ist abgelaufen. Bitte erneut versuchen.',
        'The mails name no tracking number and no shipping cost. The orders from before the mails come from the export: upload its CSV files under «Order export».' => 'Die Mails nennen keine Sendungsnummer und keine Versandkosten. Die Bestellungen von vor den Mails kommen aus dem Export: seine CSV-Dateien unter «Bestellexport» hochladen.',
        'The orders could not be stored under wp-content.'                               => 'Die Bestellungen konnten nicht unter wp-content gespeichert werden.',
        'The orders from before the mails: upload the files «amazon_<year>_positionen.csv» and «amazon_<year>_bestellungen.csv» of your export, several at once. Each fetch of the mail import hands the next 100 orders to the inbox; an order the model has already is not proposed again. Taking an order in the inbox is the confirmation — stock and goods receipt follow only from there. The export has no shipping or delivery date, so none is written.' => 'Die Bestellungen von vor den Mails: die Dateien «amazon_<year>_positionen.csv» und «amazon_<year>_bestellungen.csv» Ihres Exports hochladen, mehrere auf einmal. Jeder Abruf des Mail-Imports gibt die nächsten 100 Bestellungen in den Eingang; eine Bestellung, die das Modell schon hat, wird nicht erneut vorgeschlagen. Das Übernehmen im Eingang ist die Bestätigung — Lager und Wareneingang folgen erst von dort. Der Export hat kein Versand- oder Lieferdatum, daher wird keins geschrieben.',
        'You may not import orders.'                                                     => 'Sie dürfen keine Bestellungen importieren.',
        'needs the Taxonomy Modeller 0.1.81 or later and Taxmod Mail 0.6.0 or later.'    => 'braucht den Taxonomy Modeller 0.1.81 und Taxmod Mail 0.6.0 oder neuer.',
        'needs the Taxonomy Modeller 0.2.174 or later and Taxmod Mail 0.6.0 or later.'   => 'braucht den Taxonomy Modeller ab 0.2.174 und Taxmod Mail ab 0.6.0.',
        'run "composer install" in the plugin folder — the autoloader is missing.'       => 'im Plugin-Ordner «composer install» ausführen — der Autoloader fehlt.',
    ],
];
