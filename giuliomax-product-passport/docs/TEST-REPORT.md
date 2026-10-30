# Rapporto di verifica — 30 ottobre 2026

Versione 1.0.1. La revisione corrente aggiunge 41 controlli funzionali eseguiti su PHP nativo e MariaDB 11.4.13 in GitHub Actions, oltre ai test browser su Playground. Il controllo ufficiale Plugin Check 2.1.0 non rileva errori. Il workflow conserva i rapporti grezzi, prova l’installazione dello ZIP e verifica WooCommerce/Action Scheduler. Vedere anche WORDPRESS-ORG-REVIEW.md.

I test locali originari della 1.0.0 sono stati eseguiti su WordPress temporanei reali tramite WordPress Playground CLI 3.1.61, PHP WebAssembly e l’integrazione SQLite ufficiale distribuita con Playground. Non sono mock delle API WordPress. Questi test originari non rappresentano un collaudo di ogni hosting; la revisione 1.0.1 è stata successivamente eseguita anche su MariaDB reale.

## Compatibilità eseguita

| WordPress | PHP | WooCommerce | Esito |
|---|---|---|---|
| 6.8.3 | 8.2.33 | assente | Suite dominio/API, browser e accessibilità sui percorsi indicati |
| 6.8.3 | 8.3.33 | assente | Suite dominio/API e benchmark 10.000 schede |
| 7.1.4 | 8.3.33 | assente | Suite dominio/API e installazione ZIP con Plugin_Upgrader |
| 6.8.3 | 8.2.33 | 10.3.6 | Cinque scenari WooCommerce superati |
| 7.1.4 | 8.3.33 | 11.0.0 | Cinque scenari WooCommerce superati |
| 6.8.3 | PHP 8.2.35 nativo / MariaDB 11.4.13 | 10.3.6 | 1.0.1: 41 controlli dominio/API, sei WooCommerce/Action Scheduler, installazione ZIP e Plugin Check |
| 7.1.4 | PHP 8.3.36 nativo / MariaDB 11.4.13 | 11.0.0 | 1.0.1: stessa suite nativa e controllo directory |
| 6.8.3 / 7.1.4 | PHP 8.2 / 8.3 Playground SQLite | assente | 1.0.1: 41 controlli dominio/API e browser con traduzioni e selezione del padre per nome |
| Sorgenti | PHP nativo e PHP CLI WebAssembly | n/a | Parsing di tutti i PHP, lint JavaScript, PHP_CodeSniffer |

Non dichiarati: multisito (bloccato all’attivazione), HPOS, compatibilità universale con temi, page builder, cache/CDN o servizi di hosting. Il plugin non tratta ordini, checkout o magazzino. Creazione e ripetizione delle migrazioni sono eseguite su MariaDB in CI. La combinazione di hosting, tema e altri plugin del sito destinazione va comunque verificata sullo staging prima dell’adozione aziendale.

## Scenari verificati

I 29 controlli originari, mantenuti nella suite ampliata, coprono installazione/migrazione ripetibile senza WooCommerce; creazione e pubblicazione; isolamento bozza/pubblico; esclusione file riservati e responsabili interni; download documenti pubblici autorizzati dalla snapshot; contatti aziendali congelati; eredità modello/variante/lotto/esemplare e reset; unicità; UUID non modificabile; conflitti; impatto documento condiviso e versioni congelate; proposte fornitore limitate ai campi; scadenza e riuso negato dei token; proprietà e ruoli; input malevoli/MIME; CSV ripetuto, errori isolati e vuoti; coda ripresa; mancato successo delle connessioni senza ricevuta; PNG/SVG locali; PDF lungo; ZIP e ripristino conservativo; diniego anonimo; stati non applicabili; ripristino storico con documenti e azienda; reimportazione in catalogo vuoto; email con catcher e opt-in; versionamento modelli; payload QR lungo entro il limite; integrità binaria dei documenti.

I dodici controlli aggiunti coprono: continuazione della coda indipendente dal watchdog orario; recupero lease scaduto; isolamenti di SKU ambigui, record malformati e valori non validi; intestazioni CSV quotate; token di conferma REST con maiuscole; query elenco lavori; download autore e diniego anonimo/privato; aggiornamento documento concorrente senza versioni orfane; eredità senza copia dei default; conservazione richiami durante archiviazione e diritti prima della modifica; invalidazione della verifica interna quando cambia un valore; errore ZIP invalido leggibile.

La suite WooCommerce verifica acquisizione tramite API prodotto, aggiornamento commerciale soltanto proposto, conservazione del prezzo, sopravvivenza del passaporto alla cancellazione del prodotto, variante con padre e conservazione dopo disattivazione WooCommerce. Ai cinque scenari si aggiunge la prova Action Scheduler con azione in stato in-progress: viene programmata una nuova azione pending e il cursore continua dal record 50 al 61 senza errori. Le combinazioni sono quelle della matrice.

**Email:** `pre_wp_mail` intercetta il trasporto di test. Nessun destinatario reale contattato. Non collaudati recapito SMTP, SPF/DKIM, antispam o provider aziendale.

**Interfaccia Chromium 153 / Playwright 1.64.1:** login, panoramica, apertura editor, modifica, salvataggio bozza, pubblicazione da dialogo, collegamento pubblico e QR, pagina mobile anonima, diniego al file riservato, pannello inglese principale, selezione della scheda padre per nome, creazione della variante e traduzioni conservate al cambio lingua e al salvataggio ordinario. Nessun errore JavaScript nei percorsi provati. Nessun overflow orizzontale sulla pagina pubblica o nell’editor alla viewport 390×844; controllo desktop a 1440×1000. Verificati nomi/etichette, focus visibile e sequenza principale; non svolto un audit completo con lettori di schermo, utenti con disabilità o tastiere/dispositivi fisici diversi.

**Accessibilità:** axe-core 4.14.0, regole WCAG 2 A/AA, 2.1 AA e 2.2 AA; zero violazioni rilevate nelle aree del plugin di panoramica/editor e nella pagina pubblica esaminata. Questo esito circoscritto non equivale a certificazione WCAG dell’intero sito o di ogni contenuto caricato dall’azienda.

**QR:** decodifica indipendente tramite ZXing della PNG generata. Campione SVG rasterizzato a 28 mm / 300 dpi e decodificato. Il payload artificiale di 500 byte è stato decodificato in modalità PURE_BARCODE: il rilevatore geometrico ZXing non individuava quel pattern ripetitivo; non si assume che ogni scanner lo legga su un’etichetta piccola. Contrasto nero/bianco e quiet zone di quattro moduli. Non è stata eseguita una stampa fisica su ogni materiale/stampante: fare una scansione reale prima della produzione. Limite applicativo 500 byte nell’URL; oltre serve un indirizzo/resolver più breve.

**PDF:** parsing e testo estratto con PyMuPDF. Il caso lungo genera cinque pagine, con oltre 15.000 caratteri estraibili. Esclusi documenti riservati. Il PDF nativo è testuale, datato e paginato; non include foto o replica grafica della pagina. Usa Windows-1252 con traslitterazione: per alfabeti non coperti è preferibile stampare la pagina pubblica dal browser. Le etichette stampano un titolo abbreviato se superiore a 90 colonne di testo; la scheda conserva il titolo completo.

**Codice:** parsing di tutti i PHP, `node --check` degli script, PHP_CodeSniffer con PSR-12 adattato e formattazione automatica. La configurazione esplicita consente nomi snake_case coerenti con WordPress, guardie ABSPATH e righe lunghe SQL/template. Eseguito Plugin Check ufficiale 2.1.0 senza categorie escluse: zero errori. Gli avvisi su query di tabelle proprie e letture di routing sono valutati nella revisione WordPress.org, non sono certificazioni di sicurezza. Non si dichiara un audit completo WordPress Coding Standards, PHPStan o un penetration test indipendente.

## Benchmark storico 1.0.0 — 10.000 schede

Queste misure appartengono alla 1.0.0. La 1.0.1 aggiunge validazione e commit per riga: il benchmark non è stato ripetuto e i tempi sotto non sono attribuiti alla nuova versione.

Ambiente: Linux x86_64, CPU visibile AMD EPYC 9V74; quota cgroup otto CPU, memoria cgroup 8 GiB. Node 24.22.1, PHP WebAssembly 8.3.33, WordPress 6.8.3, SQLite Playground, un worker HTTP. Altri processi del workspace possono influenzare la misura.

Import CSV reale di 10.000 schede con SKU, nome, materiali e cura, su catalogo iniziale di 129 schede. Elaborazione in lotti di 50. Nessuna foto o grande allegato nel dataset di prestazione.

| Operazione | Misura osservata |
|---|---|
| Anteprima CSV e riconciliazione, 10.000 righe | 11,409 s; picco memoria PHP 58,56 MiB |
| Import completato, 10.000 righe | 258,989 s; 10.000 confermate; zero errori |
| Elenco, catalogo 10.129 schede | Sette chiamate applicative: 6, 4, 4, 4, 4, 3, 4 ms; mediana 4 ms |
| Ricerca riferimento `PERF-09999` | Sette chiamate: 17, 14, 14, 16, 16, 16, 16 ms; mediana 16 ms; un risultato |

I tempi elenco/ricerca misurano la chiamata REST interna con WordPress già avviato. Non includono avvio PHP, rete, browser, CDN o tempi HTTP completi. Non sono una promessa per un hosting condiviso, né una misura con 10.000 PDF o seriali per ciascun prodotto. Il JSON di esecuzione è riproducibile con lo script di test; i limiti degli archivi e degli upload richiedono dimensionamento per cataloghi documentali grandi.

## Dipendenze esterne non collaudate

| Connessione | Stato reale | Cosa manca |
|---|---|---|
| Registro UE | Non configurata, azione produzione assente | Operatore verificato, credenziali e specifiche API applicabili, invio/ricevute/errori/reconciliation reali |
| Prestatore backup/conservazione | Non configurata | Contratto, modello, credenziali e prove di invio/ripristino/continuità |
| Resolver/identificatori riconosciuti | Non configurata | Schema applicabile, provider e test di risoluzione |
| Profili settoriali regolamentati | Non operativi | Matrice specifica, standard integrali, vocabolari/dati/accessi e prove richieste |
| Email aziendale reale | Disabilitata inizialmente | Configurazione aziendale esplicita e recapito sul trasporto del sito |

## Limiti locali espliciti

Una organizzazione per installazione; niente workflow multi-azienda. Pannello italiano e catalogo inglese per il percorso principale; alcuni messaggi server e testi specialistici restano italiani. La libreria elenca fino a 1.000 documenti per selezione; upload singolo massimo 10 MB; import CSV massimo 20.000 righe / 12 MB; ripristino ZIP massimo 50 MB compresso, 100 MB espanso, 5.000 elementi e 20 MB per elemento. Sono limiti tecnici/protezioni, non quote commerciali. Le impostazioni di hosting possono imporre limiti inferiori.

Le impostazioni aziendali locali non vengono sovrascritte dal ripristino. Le identità esistenti sono conservate, non riportate a una vecchia versione. Richieste fornitore e code pendenti non vengono trasferite. Il backup database resta necessario per ripristini amministrativi completi.

## Riproduzione

```sh
npm ci
npm run lint
npm test
QA_WP=7.1.4 QA_PHP=8.3 npm test
QA_PERF=1 QA_PHP=8.3 npm test
QA_BROWSER=1 npm test
QA_WOO_DIR=/percorso/plugin/woocommerce node tests/run-woo.mjs
QA_WOO_DIR=/percorso/woocommerce11 QA_WP=7.1.4 QA_PHP=8.3 node tests/run-woo.mjs
npm run build
node tests/install.mjs
```

Per Chromium, `QA_CHROME` può indicare un eseguibile già disponibile; il fallback di test usa `@sparticuz/chromium`. Nell’ambiente di questa sessione i pacchetti Brotli sono stati estratti senza cambiare proprietari, a causa della mappatura UID del sandbox. Questo adattamento interessa solo il browser di test, non il plugin. Per PHPCS usare la versione corrente con `phpcs --standard=phpcs.xml` e PHP CLI normale; PHPCS non è un requisito del sito.

## Riproduzione della CI e pacchetto

`.github/workflows/quality.yml` installa WordPress su MariaDB e prova sorgenti, migrazione, regressioni, Plugin Check, ZIP e integrazione WooCommerce. Il browser viene avviato con Playwright standard in GitHub Actions; `QA_SERVERLESS_BROWSER=1` abilita gli argomenti specifici del browser serverless locale. Il pacchetto ZIP ha contenuti e timestamp deterministici: la CI rigenera l’archivio e confronta lo ZIP e SHA256SUMS con quelli committati.

I risultati di riferimento sono nei workflow pubblici del repository e negli artifact associati. `QA-EVIDENCE.json` conserva l’evidenza originaria datata della 1.0.0; i rapporti della revisione corrente sono separati in `docs/qa-1.0.1/`. Il superamento dei test non costituisce approvazione WordPress.org né conformità normativa del prodotto aziendale.

Esecuzione di riferimento completa: https://github.com/Giulio001/eu-passport/actions/runs/41030472725 (commit 2537f0dcd6769401aa9fae031a25c57c3a91ebc6), entrambe le combinazioni superate. Il rapporto conservato rileva 105 avvisi: 52 per query dirette, 51 per assenza di cache e due per letture di navigazione senza mutazioni. La revisione finale aggiunge l’annotazione specifica per queste due letture. Il workflow della consegna rigenera e verifica nuovamente il pacchetto finale.
