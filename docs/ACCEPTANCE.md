# Stato di consegna

- **Implementato e provato:** catalogo autonomo, flusso pubblicazione/QR, revisioni, documenti condivisi e riservati, lingue contenuto, varianti/lotti/seriali, CSV e code, export/reimport, proposte fornitore e trasporto email di test, acquisizione WooCommerce, pagina pubblica, PDF, etichette, shortcode e blocco.
- **Predisposto, non operativo:** confine degli adapter esterni e validazione del risultato con ricevuta. Non sono inclusi invii al registro, provider di continuità o profili regolamentati verificati.
- **Da verificare nell’ambiente del sito:** transazioni/limiti sul database hosting, cache/CDN, stampa fisica, recapito email, combinazione tema/plugin e piano di continuità dominio/hosting.
- **Limiti di release:** un’azienda per sito, niente multisito, PDF testuale con traslitterazione, inglese amministrativo parziale per messaggi specialistici, dimensioni tecniche documentate nel rapporto.

La consegna viene pubblicata sul ramo `main` dopo le verifiche del workflow. Non è stata eseguita pubblicazione WordPress.org o distribuzione su siti reali. L’installatore ZIP è collaudato separatamente dal mount dei sorgenti usato nei test.
