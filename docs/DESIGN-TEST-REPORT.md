# Collaudo Design Studio 1.1.0 — 30 ottobre 2026

La suite finale aggiunge alla precedente un controllo specifico sul design delle varianti: un template sostituisce la configurazione come unità, può tornare a ereditare dal padre oppure scegliere il layout classico. I 46 controlli della suite completa di riferimento sono documentati nell’esecuzione [41043578061](https://github.com/Giulio001/eu-passport/actions/runs/41043578061), superata su entrambe le combinazioni. Il nuovo controllo porta il totale funzionale a 47 prima della consegna. I rapporti sintetici sono in `docs/qa-1.1.0/`; i risultati grezzi e le schermate sono negli artifact della CI. Il pacchetto finale viene nuovamente rigenerato, confrontato e installato dal workflow prima della consegna.

## Verifiche

| Area | Esito osservato |
|---|---|
| PHP nativo + MariaDB 11.4.13 | 47 controlli nella suite finale su WordPress 6.8.3 / PHP 8.2.35 e WordPress 7.1.4 / PHP 8.3.36 |
| WooCommerce / Action Scheduler | Sei scenari per ciascuna combinazione, WooCommerce 10.3.6 e 11.0.0 |
| Plugin Check 2.1.0 | Zero errori. 104 avvisi: 53 sulle query dirette delle tabelle proprie/opzione con controllo concorrenza, 51 sulla cache, motivati nella revisione WordPress.org |
| Interfaccia | Galleria, scelta preset, font, nome, blocco di testo personalizzato, riordino, salvataggio, scelta prodotto e applicazione alla bozza |
| Responsive | Tutti e cinque i punti di partenza provati a 390, 768 e 1440 px. Nessun overflow orizzontale rilevato |
| Contrasto automatico | Ulteriore configurazione con accento giallo e superfici grigie, provata alle stesse tre larghezze |
| Accessibilità | Zero violazioni axe WCAG 2 A/AA, 2.1 AA e 2.2 AA nella galleria, nei controlli dell’editor e nelle 18 combinazioni del renderer pubblico controllate |
| Editor sul telefono | Cambio area Personalizza/Anteprima, cornice con viewport reale a 390 px, assenza di overflow |
| Compatibilità pregressa | Le schede senza design continuano a usare il renderer classico. Ripristinare una revisione classica rimuove il nuovo design dalla bozza |
| Sicurezza | Configurazione con campi consentiti, colori esadecimali, testo sanificato, niente codice utente. Anteprima con controllo proprietà; libreria condivisa riservata al gestore |
| Pubblicazione | Modifica grafica isolata nella bozza; snapshot pubblica immutata fino alla pubblicazione; copia del template indipendente dalla libreria |
| Documenti e PDF | Evidenze private escluse anche dal renderer grafico. I blocchi di testo personalizzato sono presenti nella copia PDF testuale |

L’anteprima è renderizzata dal server con la stessa funzione della scheda pubblica, in iframe con script disabilitati. I pulsanti dispositivo impostano una larghezza effettiva di 1080/768/390 px; il risultato viene ridotto visivamente per entrare nel pannello. Non si creano tre varianti separate dei contenuti.

## Limiti della verifica

I test browser usano Chromium tramite Playwright, non ogni dispositivo fisico o versione di Safari/Firefox. Il CSS usa layout fluidi, media query e font di sistema; una prova sul sito e sui dispositivi destinazione resta opportuna. La verifica automatica non certifica l’accessibilità dell’intero sito o dei contenuti caricati dall’azienda.

La selezione foto usa il media picker nativo di WordPress e i permessi del sito. La libreria dei template non è un editor di HTML/CSS libero. Il designer è italiano; titoli personalizzati e testi dei blocchi non vengono tradotti automaticamente. Il PDF rimane testuale e non riproduce il layout grafico.

I test storici di prestazione della 1.0.0 non sono stati ripetuti né attribuiti a questa versione. L’introduzione dei template non attiva servizi esterni o profili DPP regolamentati.
