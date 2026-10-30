# Revisione per WordPress.org — 30 ottobre 2026

Candidato: Giuliomax Product Passport 1.0.1. Questo documento registra la verifica tecnica del pacchetto e i limiti della consegna. Non è un’approvazione del Plugin Review Team né un certificato di conformità del prodotto dell’azienda.

## Fonti e ambito

Consultati la [pagina italiana di invio](https://it.wordpress.org/plugins/developers/add/), le [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), le FAQ sviluppatori e la [regola del readme in inglese](https://make.wordpress.org/plugins/2025/07/28/requiring-the-readme-to-be-written-in-english/). La pagina di invio prevede una revisione manuale. GitHub e WordPress.org sono canali distinti; questo lavoro non include un invio alla directory.

## Riscontro delle linee guida

| Punto | Riscontro sul candidato |
|---|---|
| 1 — Licenze | Runtime GPL-2.0-or-later; encoder QR MIT con testo integrale della licenza e attribuzione. Nessun asset runtime richiede una licenza a pagamento. |
| 2 — Responsabilità e sicurezza | Capability e proprietà verificate sul server; REST cookie authentication con nonce; nonce sui form e download amministrativi; output contestuale; validazione upload e ZIP. Suite di regressione inclusa. |
| 3 — Distribuzione stabile | ZIP completo e autonomo, con versione coerente. La disponibilità nella directory dipenderà dall’ammissione e dal caricamento SVN successivo. |
| 4 — Sorgenti leggibili | PHP, JavaScript e CSS leggibili inclusi. Repository pubblico con strumenti di test e packaging. Nessuna offuscazione o dipendenza di build sul sito. |
| 5 — Funzioni disponibili | Nessuna scadenza, quota commerciale, blocco trial o sblocco remoto. Limiti file/righe sono protezioni tecniche documentate. |
| 6 — Servizi | Nessun servizio esterno necessario. Le connessioni future sono dichiarate non configurate e non mostrano esiti di registrazione inventati. |
| 7 — Dati e consenso | Nessuna telemetria o AI. Email fornitore disabilitate inizialmente; opt-in amministrativo e blocco su copie/staging. Privacy e immagini esterne spiegate nel readme e nel testo suggerito per l’informativa. |
| 8 — Codice eseguibile | Script, CSS ed encoder locali; nessuna esecuzione da CDN o aggiornamento proprietario. |
| 9 — Dichiarazioni | Nessuna promessa di messa a norma automatica, certificazione o sostenibilità verificata. Schede esplicitamente volontarie, modelli settoriali regolamentati assenti. |
| 10 — Crediti pubblici | Nessun collegamento promozionale obbligatorio all’autore. La pagina mostra l’azienda configurata dall’operatore. |
| 11 — Amministrazione | Asset sulle pagine del plugin; nessun redirect di attivazione, pubblicità, richiesta recensioni o avviso invasivo nel resto della dashboard. |
| 12 — Readme | Inglese standard, cinque tag pertinenti, requisiti e limiti espliciti. Niente keyword stuffing o promesse non implementate. |
| 13 — Librerie WordPress | API REST, capability, nonce, cron, Gutenberg e i18n di WordPress; nessuna seconda copia di jQuery o React. |
| 14 — Aggiornamenti directory | I commit di sviluppo restano in GitHub. Un futuro repository SVN deve contenere release, non il traffico della CI. |
| 15 — Versioni | Header PHP, costante runtime, package e stable tag 1.0.1. “Tested up to” nel formato 7.1 richiesto dalla directory. |
| 16 — Pacchetto completo | Catalogo, pubblicazione, documenti e QR funzionano senza WooCommerce né chiavi esterne. Test e dipendenze di sviluppo esclusi dallo ZIP installabile. |
| 17 — Nome e attribuzioni | Nome proprio Giuliomax; WooCommerce descritto come integrazione facoltativa, senza pretesa di affiliazione. Encoder attribuito. Lo slug definitivo viene assegnato/confermato dalla directory. |
| 18 — Revisione | La decisione del team resta separata dal collaudo tecnico. Eventuali richieste del revisore vanno risolte prima di dichiarare il plugin approvato. |

## Plugin Check e annotazioni

Il workflow esegue il Plugin Check ufficiale senza escludere categorie e senza usare analisi AI. Arresta la verifica in presenza di errori; conserva il rapporto completo fra gli artifact.

Le annotazioni nel codice riguardano casi circoscritti: CSV su stream in memoria, streaming di ZIP/PDF/QR/JSON generati localmente, corpo HTML già costruito con escaping contestuale, query con frammenti fissi e valori associati a placeholder, lettura di parametri pubblici o di navigazione senza mutazioni. Non sono disabilitazioni generali dei controlli.

Gli avvisi generici sulle query dirette e sull’assenza di cache vengono conservati nel rapporto: il plugin usa tabelle proprie per snapshot immutabili, file binari, journal e code transazionali; non esiste un’API post/meta equivalente. I controlli editoriali e dei permessi leggono lo stato corrente. Una cache incoerente su cursori, lock o autorizzazioni sarebbe scorretta. Questo riscontro motivato non equivale a nascondere gli avvisi o a garantire l’esito della revisione manuale.

## Cosa resta esterno al collaudo

L’account WordPress.org, il nome contributore dichiarato e lo slug definitivo devono essere confermati dal titolare nel flusso di invio. Non sono stati usati account o credenziali WordPress.org. La manutenzione del plugin, la risposta alle segnalazioni e le release successive restano responsabilità del maintainer.

Il plugin conserva dati editoriali e documenti alla disinstallazione per proteggere i riferimenti già stampati. L’operatore deve definire conservazione, accesso ai backup e informativa. Un hosting compatibile, dati corretti e diritti sui documenti caricati sono necessari nell’uso concreto. Per i limiti ESPR e delle discipline settoriali leggere REGULATORY-MATRIX.md.
