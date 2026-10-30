# Giuliomax Product Passport

Plugin WordPress per creare **schede digitali volontarie** di prodotto con manuali, materiali, manutenzione, ricambi e assistenza raggiungibili da un QR. Funziona con un catalogo autonomo e può acquisire prodotti da WooCommerce. Non richiede servizi esterni, Composer o npm sul sito dell’utilizzatore.

**Questa release non certifica la conformità UE e non registra passaporti nel registro europeo.** I modelli generico, arredo e tessile sono modelli informativi modificabili. Per i confini normativi leggere [la matrice](docs/REGULATORY-MATRIX.md).

## Installa e crea il primo passaporto

1. Scarica [lo ZIP installabile](dist/giuliomax-product-passport-1.0.0.zip).
2. In WordPress: **Plugin → Aggiungi plugin → Carica plugin**, seleziona lo ZIP e attivalo.
3. Apri **Product Passport → Impostazioni** e inserisci almeno il nome azienda. Scegli modalità, settore, lingua e contatti. Logo, contatti aggiuntivi e dati di esempio sono facoltativi.
4. In **Documenti**, carica il manuale indicando la lingua effettiva, il tipo e la versione. Un documento può servire più prodotti. PDF, PNG, JPEG e testo semplice; massimo 10 MB per file.
5. Apri **Passaporti → Nuovo passaporto**. Inserisci nome e riferimento, completa le sezioni pertinenti e seleziona i documenti.
6. **Salva bozza → Anteprima → Pubblica revisione**. Inserisci una motivazione. Dopo la pubblicazione trovi il collegamento, il QR SVG/PNG, il PDF e la stampa etichetta nella colonna destra.
7. Scansiona una stampa di prova, alla dimensione effettiva, prima di produrre molte etichette.

Il cliente apre la scheda dal browser, senza app o account. Il QR identifica la scheda, non la pagina commerciale. Per un esempio completo e una valutazione del caso d’uso leggere [la guida pratica](docs/GUIDA-PRATICA.md).

## Funzioni

- Quattro aree: panoramica, passaporti, documenti, impostazioni.
- Elenco con ricerca, stato, ordinamento, paginazione, colonne e azioni massive con conferma d’impatto.
- Modello → variante → lotto → esemplare con eredità, override reversibili e seriali in coda.
- Bozza separata dalla revisione pubblica; storico, ripristino tracciato e avvisi sul prodotto.
- Libreria documentale condivisa, versioni e lingue; file riservati nel database con download autorizzato.
- Traduzioni di testi e allegati con lingua dichiarata; nessuna traduzione automatica inventata.
- Cura, sicurezza, fine vita, ricambi compatibili, evidenze ambientali con metodo/fonte e qualità del dato.
- QR locali, SVG/PNG, etichette singole o multiple e PDF testuale della revisione pubblicata.
- Shortcode e blocco Gutenberg per pulsante, QR o scheda.
- CSV UTF-8: associazione colonne, anteprima, modalità crea/aggiorna, regole sui vuoti, errori isolati e coda riprendibile.
- Archivio ZIP amministrativo con manifest SHA-256 e reimport; export pubblico JSON separato.
- Richieste ai fornitori: token hash, scadenza, revoca, proposta da accettare in bozza; email disattivate inizialmente.
- Ruoli compilatore, revisore e responsabile con controlli server, anche sulle API.
- WooCommerce facoltativo: prodotti e varianti, import esplicito e confronto aggiornamenti; nessuna modifica a ordini, prezzi, stock o checkout.

## Requisiti e compatibilità

WordPress 6.8 o successivo, PHP 8.2 o successivo, sito singolo. Estensioni PHP `mbstring`, `fileinfo`, `iconv`, `zlib`, `zip`; l’attivazione controlla i prerequisiti. Database transazionale InnoDB sul normale hosting WordPress. Il multisito è bloccato esplicitamente in questa release.

Le versioni realmente eseguite, l’ambiente di test, le metriche e i limiti sono riportati nel [rapporto di verifica](docs/TEST-REPORT.md). Non si dichiara una compatibilità HPOS: il plugin non legge ordini e non modifica la loro memorizzazione.

## WooCommerce

Dal catalogo WooCommerce usa **Crea o collega scheda digitale** oppure **Passaporti → Da WooCommerce**. La prima acquisizione copia i dati commerciali pertinenti nella bozza; gli aggiornamenti successivi mostrano un confronto e richiedono la selezione dei valori da applicare. Le modifiche manuali e la versione pubblica non vengono sovrascritte automaticamente. Per le varianti viene collegata la scheda del padre.

Nome, immagini, riferimento e descrizione vengono acquisiti attraverso le API prodotto. La scheda rimane disponibile se WooCommerce viene disattivato o il prodotto viene eliminato. Le informazioni obbligatorie nell’offerta online, per esempio ai sensi del GPSR, vanno mostrate e verificate anche nella pagina di vendita: il collegamento alla scheda non le sostituisce.

## Riutilizzo in pagine WordPress

Inserisci il blocco **Scheda prodotto Giuliomax** e copia l’UUID presente nei dettagli della scheda. In alternativa:

```text
[gmpp_passport uuid="UUID-DELLA-SCHEDA" view="button"]
[gmpp_passport uuid="UUID-DELLA-SCHEDA" view="qr"]
[gmpp_passport uuid="UUID-DELLA-SCHEDA" view="sheet"]
```

Le schede non pubblicate non vengono renderizzate. I file privati non compaiono nel blocco, nella pagina pubblica, nell’API pubblica o nel PDF.

## Code, trasferimenti e conservazione

**Passaporti → Importa / esporta** raccoglie CSV, ZIP, ripristino e avanzamento. Le operazioni lavorano in lotti di 50 record. Puoi usare **Continua ora**, Action Scheduler quando disponibile, WP-Cron o `wp gmpp jobs`. Per elaborazioni affidabili configura il cron di sistema: WP-Cron dipende dalle visite. L’annullamento ferma i lotti ancora in attesa; un lotto già acquisito da un worker viene terminato.

L’archivio amministrativo contiene dati riservati. Il ripristino verifica manifest e integrità prima di applicare i dati; conserva le identità già presenti senza sovrascriverle. È un’importazione in un catalogo esistente, non una funzione di rollback distruttivo. Per migrare i contenuti integralmente usa un catalogo vuoto e conserva dominio/redirect. Richieste fornitore e code pendenti non vengono migrate: vanno chiuse o ricreate. Vedi [architettura e continuità](docs/ARCHITECTURE.md).

Disattivazione e disinstallazione conservano i dati. Non esiste un comando pubblico di cancellazione totale. Una cancellazione amministrativa manuale del database richiede una decisione di conservazione e una copia verificata.

## Sviluppo e verifiche

Il pacchetto runtime è già completo. Le dipendenze Node servono solo per il collaudo del repository:

```sh
npm ci
npm run lint
npm test
npm run test:browser
npm run build
```

I test eseguono un WordPress temporaneo con PHP WebAssembly e integrazione SQLite. Le credenziali e gli utenti nei test sono fittizi. Il file `tests/harness.php` fornisce privilegi amministrativi nell’ambiente di prova: **non copiarlo mai in un sito reale**. Non è incluso nello ZIP.

Per WooCommerce e benchmark: istruzioni nel rapporto di verifica. Per integrare gestionali: [API](docs/API.md).

Licenza GPL-2.0-or-later. Encoder QR vendorizzato di Kazuhiko Arase sotto licenza MIT, con namespace e costanti isolati; riferimenti in [THIRD-PARTY.md](THIRD-PARTY.md).
