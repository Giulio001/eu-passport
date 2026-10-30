# Come usarlo e quando ha senso

Giuliomax Product Passport serve a mettere le informazioni utili di un prodotto in un posto stabile: il cliente scansiona un QR e trova manuale, manutenzione, materiali, ricambi e assistenza. Il vantaggio principale è organizzare informazioni che altrimenti finiscono in PDF sparsi, email e pagine commerciali destinate a cambiare.

La release 1.0.1 è un gestore di **schede digitali volontarie**. Non trasforma automaticamente un catalogo in passaporti regolamentati, non certifica la sostenibilità e non registra dati nel registro europeo.

## Esempio concreto: un’azienda che vende sedie

L’azienda produce la sedia Alba in due finiture. Il manuale di montaggio vale per entrambe; cambiano finitura e riferimento commerciale. Esistono inoltre piedini di ricambio.

1. **Configura l’azienda.** In Impostazioni inserisci “Atelier Alba”, email di assistenza, lingua italiana e settore arredo. Puoi aggiungere logo e colore. Il nome azienda basta per iniziare; il resto non deve bloccare l’attivazione.
2. **Carica i documenti.** In Documenti carica il manuale italiano indicando lingua `it`, tipo manuale, versione `1`. Carica separatamente il documento inglese con lingua `en`. Non chiamare “inglese” un file che contiene soltanto testo italiano.
3. **Crea il modello.** In Passaporti scegli Nuovo passaporto, nome “Sedia Alba” e riferimento “ALBA”. Compila materiali, cura, sicurezza pertinente, ricambi e assistenza. Seleziona i due manuali.
4. **Crea le varianti.** Crea “Sedia Alba naturale” e “Sedia Alba noce” come varianti, cercando il nome o riferimento della scheda padre e selezionandola. Ereditano i dati del modello. Compila solo i valori differenti. I campi ereditati sono segnalati; puoi rimuovere un override e tornare al valore del padre.
5. **Controlla e pubblica.** Salva la bozza, guarda l’anteprima e pubblica con motivazione “Prima pubblicazione”. Gli avvisi editoriali aiutano a completare la scheda; non sono una verifica legale del prodotto. Puoi rendere alcuni campi obbligatori per la tua azienda modificando il modello informativo in Impostazioni.
6. **Stampa il QR.** Nella scheda trovi SVG, PNG e stampa etichetta. Mettilo sul prodotto, sulla confezione o sul materiale di supporto in base alle esigenze concrete e agli eventuali requisiti applicabili. Prima stampa un campione e scansionalo.

Il cliente vede la pagina pubblicata, con le sole sezioni disponibili. Non deve installare app, creare account o accettare il marketing.

## Cosa succede dopo

**Arriva una nuova versione del manuale.** Nella libreria scegli Nuova versione sul documento esistente, carica il file e imposta la nuova versione. “Vedi impatto” elenca le schede che lo usano, comprese quelle che lo ereditano. Prepara le bozze, controllale e pubblica le nuove revisioni. Finché non pubblichi, la pagina continua a referenziare il vecchio manuale: un caricamento non cambia silenziosamente le informazioni già distribuite.

**Cambia il nome commerciale.** Il QR continua a riferirsi alla stessa identità. Se il prodotto arriva da WooCommerce, il confronto mostra i nuovi dati e consente di applicarli alla bozza. Non vengono sovrascritte automaticamente le modifiche manuali o la revisione pubblica.

**Il prodotto viene tolto dal catalogo.** La scheda rimane disponibile. L’azione di archiviazione pubblica un avviso di fuori produzione e conserva assistenza e documenti. Eliminare il prodotto WooCommerce non elimina il passaporto.

**Ti servono lotti o numeri di serie.** Parti dal modello o dalla variante, crea un lotto e genera gli esemplari con un seriale per riga. L’operazione viene elaborata in coda. Usa questa granularità quando porta un vantaggio: creare un passaporto per ogni pezzo senza un motivo aumenta il lavoro e non è il comportamento predefinito.

**Mancano dati del fornitore.** Seleziona i soli campi necessari e crea un collegamento con scadenza. Il fornitore vede il nome prodotto e quei campi. La risposta diventa una proposta; un revisore la accetta nella bozza e un responsabile decide la pubblicazione. L’email automatica richiede un’abilitazione esplicita; il collegamento può essere copiato e inviato con i normali canali aziendali.

**Sei un rivenditore.** Usa il campo Passaporto originale del produttore per rinviare all’identità esistente. Il plugin conserva il collegamento; non verifica la conformità del sito esterno né inventa un identificatore normativo. Puoi accompagnarlo con assistenza locale senza presentare la scheda del rivenditore come sostituzione del passaporto originale.

## Importazione ed esportazione

Per molti prodotti, apri Passaporti → Importa / esporta. Un CSV minimo è:

```csv
sku,name,materials,care,language,template
ALBA-01,Sedia Alba,Legno di faggio,Pulire con un panno umido,it,furniture
ALBA-02,Sedia Alba noce,Legno con finitura noce,Pulire con un panno umido,it,furniture
```

Associa le colonne, scegli se creare o aggiornare, indica la chiave di riconciliazione e decidi se i campi vuoti devono mantenere o cancellare i valori. Controlla l’anteprima, poi conferma. Usa SKU soltanto se univoco nel catalogo; per riconciliazioni ambigue usa UUID. La colonna documenti accetta numeri documento separati da `|`: non scarica allegati da URL importati.

Le operazioni lavorano in lotti. Puoi seguirne l’avanzamento, proseguire manualmente o consultare gli errori per riga. Una riga errata non blocca quelle valide. L’annullamento ferma il lavoro ancora in attesa, non annulla i record già confermati.

Per migrare dati e allegati usa l’archivio portabile ZIP, non il solo export pubblico JSON. Il ripristino mostra l’anteprima e conserva le identità esistenti senza sovrascriverle. Trasferire i dati non mantiene da solo un dominio scaduto: devi conservare gli indirizzi o i redirect e avere una strategia di backup separata dall’hosting principale.

## Ha realmente senso?

**Sì, quando il problema è la gestione delle informazioni dopo la vendita.** Un produttore che riusa manuali su molte varianti, riceve richieste ricorrenti di montaggio/manutenzione o deve mantenere documenti anche per prodotti fuori catalogo ha un caso d’uso concreto. Il valore cresce con riutilizzo, versioni, varianti e ricambi. Un artigiano senza e-commerce può usarlo ugualmente.

**Per una pagina con un solo prodotto e un solo PDF, il vantaggio può essere piccolo.** Anche una normale pagina WordPress può mostrare un manuale e un contatto. Il plugin si giustifica quando riduce la manutenzione ripetuta e controlla identità e revisioni. Il QR è solo il punto di accesso; non basta da solo a differenziare il prodotto.

**Non partire dalla promessa “ti metto a norma con un click”.** Il quadro europeo distingue regimi e prodotti. Le regole settoriali, i dati verificati e le integrazioni effettive non possono essere sostituiti dalla completezza di una scheda. Per questa release la promessa corretta è: informazioni di prodotto ordinate, condivise e consultabili anche dopo l’acquisto.

**La domanda commerciale non è ancora dimostrata.** Esiste già almeno un plugin DPP nella directory WordPress. Non abbiamo misurato tutto il mercato, i costi di acquisizione o la disponibilità a pagare. La differenziazione proposta è l’uso autonomo da WooCommerce, i documenti condivisi, le revisioni controllate e la semplicità del lavoro quotidiano.

Una prova utile è far gestire alcuni prodotti reali a un piccolo produttore: osservare il tempo impiegato per il primo prodotto e le varianti, quante informazioni sono davvero disponibili, quanto lavoro evita un aggiornamento condiviso e se i clienti trovano manuali e assistenza. Sono criteri di utilità, non risultati già ottenuti. Se compilare le schede richiede più tempo di quanto ne faccia risparmiare e i clienti non le consultano, bisogna semplificare o rivedere il target.

## Confini da conoscere prima dell’uso reale

La conformità specifica del prodotto resta da verificare. Non sono operativi registro UE, prestatore di continuità o resolver esterno riconosciuto. Il PDF è una copia datata, non un sostituto automaticamente equivalente al passaporto dinamico. Le lingue dei contenuti devono essere inserite dall’azienda. Nell’editor apri Traduzioni, scegli la lingua, compila i testi e usa Salva bozza: i testi vengono conservati anche quando cambi lingua prima di salvare. I documenti riservati sono protetti dall’applicazione ma un amministratore del database può accedervi: backup e hosting devono essere protetti.

Il rapporto di test elenca versioni realmente eseguite e verifiche residue. Prima di un’adozione aziendale verifica il plugin sullo staging del sito e prova export, ripristino e scansione dell’etichetta sul tuo hosting.

La lingua del pannello si sceglie nelle impostazioni ed è italiana inizialmente. È disponibile un catalogo inglese per il percorso amministrativo; i dati di prodotto non vengono tradotti quando cambi lingua del pannello. Testi di errore del server e alcune indicazioni specialistiche possono restare in italiano. Le stringhe sono raccolte nel catalogo e nel template di traduzione per le integrazioni successive.

## Installazione e WordPress.org

Scarica lo ZIP runtime `giuliomax-product-passport-1.0.1.zip`, non lo ZIP generale del repository GitHub. Lo ZIP runtime contiene la cartella del solo plugin ed è caricabile da WordPress. Il readme della directory è inglese, come richiesto da WordPress.org; la guida d’uso resta italiana. L’approvazione della directory richiede una revisione manuale separata e non è stata ottenuta con il push su GitHub.
