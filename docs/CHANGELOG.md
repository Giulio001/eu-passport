# Changelog

## 1.0.1 — 30 ottobre 2026

- Code con lease recuperabile, lettura del cursore dopo l’acquisizione, commit per riga e continuazione separata dal controllo orario. Continuazione Action Scheduler verificata anche con azione corrente in esecuzione.
- CSV: token di anteprima con maiuscole conservate, riferimenti ambigui e righe malformate isolati, intestazioni duplicate rifiutate e intestazioni quotate riconosciute anche dal pannello.
- Documenti: autore della bozza tracciato, anteprima del proprio file pubblico, evidenze private riservate ai revisori, aggiornamenti condivisi con controllo concorrenza e rollback atomico.
- Scelta della scheda padre e dei prodotti compatibili per nome/riferimento; traduzioni acquisite al salvataggio e al cambio lingua; valori verificati riportati da verificare quando cambiano.
- Varianti senza copie involontarie dei valori iniziali del modello. Archiviazione conserva gli avvisi di sicurezza e verifica i diritti prima di modificare la bozza. Ripristino storico transazionale.
- Query con placeholder identificatori, tabelle consentite esplicite, readme inglese per la directory, nota privacy nell’editor WordPress e CSS pubblico accodato tramite API WordPress.
- Workflow GitHub Actions con PHP/MariaDB, Plugin Check, installazione ZIP, WooCommerce/Action Scheduler e browser/accessibilità.


## 1.0.0 — 29 ottobre 2026

Prima implementazione del plugin autonomo: identità separate dal catalogo commerciale, bozze e revisioni pubbliche, modelli informativi volontari personalizzabili, varianti/lotti/seriali, documenti condivisi con versioni e permessi, contenuti multilingua, QR SVG/PNG locali, etichette, PDF, Gutenberg e shortcode.

Aggiunti CSV con anteprima/mapping e coda riprendibile, export ZIP con manifest e ripristino conservativo, proposte fornitore con token hash/scadenza/revoca, ruoli editoriali e controllo di concorrenza. Integrata acquisizione facoltativa da WooCommerce con confronto esplicito degli aggiornamenti. Interfaccia italiana iniziale e catalogo inglese per il percorso amministrativo.

Registro UE, identificatori riconosciuti, backup presso prestatore e profili regolamentati restano connessioni non operative con prerequisiti espliciti. Nessuna dichiarazione automatica di conformità, telemetria, abbonamento o pubblicazione su WordPress.org.
