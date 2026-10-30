# Architettura, dati e continuità

## Confine del prodotto

Una installazione WordPress singola gestisce una organizzazione. Le capability WordPress separano compilatori, revisori e responsabili; non è un servizio multi-tenant. Nome azienda, contatti, logo e ruolo sono centralizzati nelle opzioni e congelati nella revisione pubblicata. Il catalogo WooCommerce è una sorgente facoltativa, mai l’identità del passaporto.

Il bootstrap carica classi con namespace `Giuliomax\Passport`. Persistenza (`Store`, `Schema`), dominio (`Passport`, `Profiles`, `Documents`, `Suppliers`), operazioni (`Jobs`, `Portable`), API (`Api`), UI (`Admin`, `PublicPage`) e integrazioni (`Woo`, `Connections`) sono separate. JavaScript e CSS sono distribuiti già pronti e caricati soltanto sulle pagine del plugin e sulle pagine pubbliche pertinenti.

## Entità e schema

Le tabelle hanno il prefisso effettivo di WordPress seguito da `gmpp_`.

| Tabella / opzione | Funzione e relazioni |
|---|---|
| `passports` | Identità immutabile UUID, chiave interna, riferimento, tipo modello/variante/lotto/esemplare, padre, proprietario, eventuale ID Woo, bozza JSON, contatore di concorrenza e puntatore alla revisione pubblica |
| `revisions` | Snapshot del passaporto e azienda, versioni esatte dei documenti pubblici, hash, autore, motivo e data; nessun aggiornamento della snapshot dopo l’inserimento |
| `documents` | Identità UUID di un documento condiviso e puntatore alla versione corrente |
| `document_versions` | Metadati, lingua, visibilità, MIME, SHA-256 e contenuto binario nel database; mai un normale URL media pubblico |
| `requests` | Richiesta fornitore: hash token, prodotto, campi ammessi, scadenza, proposta e stato; token non memorizzato in chiaro |
| `jobs` | Operazione, stato, proprietario, cursore, payload di anteprima e rapporto per riga |
| `events` | Attività significative con autore, data e motivazione |
| `gmpp_settings` | Azienda, branding, lingua, modalità, etichette, opt-in email |
| `gmpp_profiles` | Versioni delle personalizzazioni dei tre modelli informativi; regole dichiarative, mai codice eseguibile |
| `gmpp_origin` | Dominio sorgente per rilevare copie e bloccare gli invii di produzione |

`kind` distingue le entità e consente solo relazioni acicliche: modello senza padre; variante da modello; lotto da modello/variante; esemplare da modello/variante/lotto. I ricambi sono schede collegate da relazioni esplicite di compatibilità; non sono copie dei documenti.

Per migliaia di seriali o revisioni si usano tabelle indicizzate, non migliaia di meta query WordPress. I documenti nel database evitano di confidare in regole `.htaccess` che altri web server ignorano; il compromesso è il peso dei backup. Per grandi volumi serve dimensionare database, memoria e pacchetti massimi consentiti dal provider.

## Identità e unicità

UUID è un’identità locale persistente, non un identificatore normativo riconosciuto. Il resolver è `https://dominio/?gmpp=UUID`, indipendente da titolo, permalink commerciale e lingua. Funziona anche senza pretty permalinks e su WordPress in sottocartella. SKU, GTIN, lotto, seriale e identificatore esterno rimangono campi distinti.

La chiave univoca combina tipo/ambito e valore normalizzato: SKU del modello nel catalogo; SKU variante nel suo modello; lotto nel padre; seriale nel padre. SKU non è considerato globale. Un CSV che trova più record con lo stesso SKU deve utilizzare UUID. I riferimenti assenti usano l’UUID per non collidere. Non si riutilizza un UUID per una nuova scheda e non esiste una cancellazione commerciale che ne liberi l’identità.

## Bozza, pubblicazione, eredità e ripristino

La bozza conserva solo dati locali e override. La risoluzione dell’eredità è limitata dalla gerarchia. Documenti e compatibilità sono liste sostituibili, non liste fuse per indice. Ripristinare l’eredità rimuove il valore locale.

La pubblicazione controlla permessi, campi richiesti dal modello e concorrenza; crea la snapshot in transazione e sposta il puntatore pubblico. Un conflitto non sovrascrive silenziosamente il lavoro di un altro utente. Le snapshot incorporano i contatti aziendali, i testi e i riferimenti alle versioni documentali: cambiamenti successivi a dati condivisi e WooCommerce non cambiano la revisione pubblica.

Il ripristino di una revisione prepara una nuova bozza, con riferimenti alle versioni storiche dei documenti e ai contatti congelati; la nuova pubblicazione rimane esplicita. Cambiare la selezione documenti rimuove la selezione storica e torna alle versioni correnti dei documenti selezionati. Archiviare pubblica una nuova revisione con avviso di fuori produzione.

Il modello informativo porta ID/versione/stato volontario. Modificarlo cambia i controlli delle nuove pubblicazioni e mostra le schede da rivalutare, senza modificare le revisioni esistenti. Le regole ammesse sono campi richiesti e testi predefiniti selezionati; nessun `eval` o esecuzione di codice caricato.

## Permessi e sicurezza

| Capability | Azione |
|---|---|
| `gmpp_edit` | Crea schede e modifica le proprie bozze |
| `gmpp_edit_others` | Accesso editoriale alle schede degli altri utenti dell’organizzazione |
| `gmpp_review` | Revisiona proposte, documenti condivisi e dati verificati internamente; scarica documenti riservati |
| `gmpp_publish` | Pubblica e archivia le risorse accessibili |
| `gmpp_manage` | Impostazioni, modelli e archivi amministrativi |

Ogni endpoint richiede autenticazione/capability; le mutazioni REST usano il nonce WordPress per le sessioni browser. Le credenziali per API di gestione alternative vanno protette con HTTPS e i normali controlli WordPress. Il download di un documento pubblico richiede che quella versione sia referenziata da una revisione attualmente pubblicata; per un documento riservato serve un revisore autenticato. Un vecchio download che non è più referenziato dal pubblico resta recuperabile dal revisore, non anonimamente.

Le pagine pubbliche e l’API leggono soltanto la snapshot attiva. Nominativi dei responsabili interni e qualità editoriale non vengono pubblicati. Non si usano URL segreti come autorizzazione per le bozze. Le anteprime richiedono proprietà/permessi e nonce, con `no-store`/noindex; gli export amministrativi richiedono `gmpp_manage` e nonce. PDF e QR non scaricano risorse remote. La versione non ha telemetria né statistiche scansioni.

Upload: MIME effettivo, estensione, dimensione 10 MB e nomi normalizzati. Download come allegato con nosniff e sandbox. L’archivio viene letto senza estrazione; i nomi ammessi sono precisi e si controllano dimensione totale, numero elementi e hash prima delle scritture. Nessun URL di CSV viene scaricato; così non esiste un fetch remoto arbitrario da sfruttare per SSRF. Il helper CSV per celle pericolose esegue l’escaping delle formule; gli export attuali sono ZIP/JSON.

## Operazioni e ripresa

Ogni operazione appartiene all’utente che l’ha creata. Il worker ripristina il suo contesto e ricontrolla i permessi; una loro revoca non permette di proseguire con privilegi precedenti. L’acquisizione del job è un compare-and-swap. I record sono elaborati in lotti di 50 e il cursore viene confermato insieme alla scrittura; i salvataggi annidati usano savepoint. I lease interrotti sono recuperati dopo cinque minuti. Errori per riga non annullano le righe valide.

Action Scheduler viene usato quando disponibile; altrimenti WP-Cron e l’azione Continua ora. `wp gmpp jobs` permette un cron esterno. La frequenza massima dipende da scheduling e hosting: una pagina non deve essere tenuta aperta per rendere affidabile un’elaborazione. Le code concluse conservano un rapporto; l’annullamento non cancella il lavoro già confermato.

## Migrazione e conservazione

Schema versione 1 con migrazioni `dbDelta` ripetibili. Non vengono cancellati dati durante update, disattivazione o disinstallazione. Per un futuro cambio schema, aggiungere una migrazione idempotente e un test di conservazione delle identità.

L’export amministrativo ha formato `gmpp-portable` versione 1, `data.json`, binari documentali e `manifest.json` SHA-256. L’export pubblico contiene soltanto snapshot attive. Il ripristino ricostruisce identità e relazioni, rimappa le chiavi interne e conserva snapshot e allegati; i record già esistenti non vengono sovrascritti. Su un sito esistente è quindi un’integrazione, non un rollback. Impostazioni aziendali locali non vengono sovrascritte automaticamente: vanno verificate sul sito destinazione. I token fornitore e le code pendenti non sono trasferiti.

Per mantenere QR stampati: conservare il dominio e il percorso del resolver, usare redirect permanenti dal vecchio indirizzo se necessario e provarli con scansioni reali. Se WordPress si trova in una sottocartella, conservare o reindirizzare anche quella. Identità uguale non significa dominio uguale. L’opzione `gmpp_origin` e `wp_get_environment_type()` rilevano staging/copie: per una migrazione di produzione verificata aggiornare l’origine in modo amministrativo dopo le prove. Non basta rinominare un sito di staging.

L’hosting e il dominio devono avere titolare, rinnovo, backup esterno e prova di ripristino. Per un regime che richiede un prestatore, servono contratto e integrazione collaudati. Un archivio sullo stesso server non è prova di continuità.

## Confini delle connessioni

`Connections::result()` accetta stati non configurata/da verificare/attiva/errore; rifiuta un successo senza ID operazione, ricevuta e data di verifica. Nessun adapter di produzione viene incluso o attivato. Prima di implementarne uno servono specifiche ufficiali, credenziali e contratti pertinenti, mapping di modello, ritentativi limitati e riconciliazione. Gli emulatori non appartengono al runtime di produzione.
