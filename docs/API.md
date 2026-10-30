# API versione 1

Namespace `gmpp/v1`. Il prefisso WordPress può essere `/wp-json/` o `?rest_route=`. Il consumer non deve assumere un prefisso fisso: il pannello riceve `rest_url()`.

Sessione browser autenticata con header `X-WP-Nonce` ottenuto da `wp_create_nonce('wp_rest')`. Le API di gestione restituiscono 401/403 per utenti non autorizzati, 404 per risorse assenti, 422 per input rifiutati e 409 per conflitti. Ogni salvataggio/pubblicazione richiede `lock_version` letto dalla scheda. Le Application Passwords WordPress possono essere usate via HTTPS con lo stesso modello di capability; questo trasporto non è stato collaudato nella release.

| Metodo e risorsa | Comportamento |
|---|---|
| `GET /passports` | Ricerca `search`, stato `state`, `page`, `per_page` max 100, `sort` e `direction`; limita le righe al proprietario se manca accesso alle altrui schede |
| `POST /passports` | Crea identità locale; `data`, `kind`, `parent_id`. Non accetta la scelta di un UUID o un collegamento Woo arbitrario |
| `GET/PUT /passports/{id}` | Dettaglio con `data`, `effective`, `checks`; aggiorna bozza con `lock_version`, `data`, eventuale lista `reset` |
| `POST /passports/{id}/publish` | Pubblica snapshot con `reason`, `lock_version`; richiede pubblicazione |
| `POST /passports/{id}/archive` | Avviso fuori produzione, nuova revisione, conservazione dei contenuti |
| `POST /passports/{id}/duplicate` | Nuova identità; rimuove SKU, seriale, lotto e identificatore esterno |
| `GET /passports/{id}/history` | Revisioni ed eventi autorizzati |
| `POST /passports/{id}/restore` | `revision`, `lock_version`; nuova bozza tracciata, nessuna pubblicazione automatica |
| `POST /passports/{id}/serials` | Lista `serials`, massimo 10.000; crea job di esemplari ereditati |
| `POST /bulk` | `rows: [{id,version}]`, `action: publish/archive/review`, `reason`; job confermato dal chiamante |
| `GET/PUT /settings` | Azienda e impostazioni; gestione amministrativa |
| `PUT /profiles/{generic|furniture|textile}` | `version`, `required`, `defaults`; nuova versione e impatto, solo regole dichiarative ammesse |
| `GET/POST /documents` | Metadati autorizzati; upload multipart con `file`, `document_id` per nuova versione, tipo, lingua, versione, visibilità, date, fonte |
| `GET /documents/{id}/impact` | Schede interessate dal documento anche per eredità, solo revisori |
| `POST /import/preview` | `csv`, `map`, `mode:create/update/upsert`, `key:sku/uuid`, `clear`; anteprima e token temporaneo legato all’utente |
| `POST /import/start` | Token dell’anteprima; crea job e consuma il token |
| `GET /jobs`, `GET /jobs/{id}` | Avanzamento e rapporto del proprietario, senza payload privato nell’elenco |
| `POST /jobs/{id}/run` | Esegue un giro della coda; i permessi dei proprietari vengono ricontrollati |
| `POST /jobs/{id}/cancel` | Ferma job in attesa, senza rollback dei record conclusi |
| `POST /requests` | Richiesta fornitore: `passport_id`, `fields`, `days` (1–30); restituisce una sola volta URL/token |
| `GET /passports/{id}/requests` | Stati e proposte, senza token |
| `POST /requests/{id}/review` | `accept`, `lock_version`; accetta nella bozza o rifiuta |
| `POST /requests/{id}/revoke` | Revoca il collegamento |
| `POST /requests/{id}/email` | `email`, `url`; richiede opt-in aziendale e origine produzione verificata |
| `GET /woocommerce`, `POST /woocommerce/{id}` | Selezione e acquisizione/confronto attraverso le API Woo; richiede anche permessi prodotto |
| `POST /portable` | `archive` ZIP base64, `confirm:false` per anteprima, `true` per applicazione; solo amministratore plugin |
| `GET /public/{uuid}` | Unica API anonima: solo snapshot pubblicata con documenti pubblici, mai bozza/responsabili interni |

## Esempio

```json
{
  "data": {
    "name": "Sedia Alba",
    "sku": "ALBA-01",
    "template": "furniture",
    "language": "it",
    "materials": "Legno di faggio",
    "care": "Panno morbido leggermente umido",
    "documents": [12, 18]
  },
  "kind": "model",
  "parent_id": 0
}
```

Il dettaglio restituisce un nuovo `lock_version` dopo ogni modifica. Per pubblicare inviare quel valore, non un numero fisso. Non scrivere direttamente nelle tabelle: si salterebbero identità, capability, validazioni e tracciamento.

## Accesso pubblico e download

La pagina è `/?gmpp=UUID`. Parametri `lang`, `format=svg`, `format=png`, `format=pdf` restano associati alla stessa identità. Un documento passa da `/?gmpp_document=VERSION_ID`: lo stato pubblico dipende da una snapshot attiva che lo referenzia e dalla visibilità della versione, non dalla conoscenza dell’URL.

Gli export amministrativi e le etichette usano `admin-post.php` con action `gmpp_export` o `gmpp_labels`, capability e nonce `gmpp_download`. Non memorizzare questi URL con nonce come collegamenti permanenti.

Il modello è JSON proprio versione 1. Non è dichiarato un formato normativo DPP o un’implementazione interoperabile EN/GS1 verificata.
