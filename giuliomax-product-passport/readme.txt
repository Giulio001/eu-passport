=== Giuliomax Product Passport ===
Contributors: giulio001
Tags: product passport, qr code, manuals, product information, woocommerce
Requires at least: 6.8
Tested up to: 7.1.4
Requires PHP: 8.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schede digitali volontarie con documenti versionati, QR locali e assistenza prodotto, anche senza WooCommerce.

== Description ==
Crea un catalogo di informazioni prodotto consultabile da browser tramite QR. Riutilizza documenti, crea varianti e seriali, conserva revisioni pubblicate e raccogli proposte dai fornitori. Nessun servizio esterno obbligatorio o abbonamento proprietario.

La compilazione non certifica la conformità normativa UE. I modelli generico, arredo e tessile sono volontari. Registro europeo, resolver riconosciuti e conservazione esterna non sono operativi in questa release.

== Installation ==
1. Carica lo ZIP da Plugin > Aggiungi plugin > Carica plugin e attiva.
2. Apri Product Passport > Impostazioni. Inserisci almeno il nome azienda.
3. Carica un manuale in Documenti, poi crea una scheda in Passaporti.
4. Salva la bozza, controlla l'anteprima e pubblica la revisione.
5. Scarica il QR SVG/PNG oppure stampa un'etichetta e verifica una scansione.

Richiede PHP 8.2+, mbstring, fileinfo, iconv, zlib, zip e WordPress singolo. Dati conservati alla disinstallazione.

== Frequently Asked Questions ==
= Serve WooCommerce? =
No. Il catalogo autonomo funziona senza WooCommerce.
= È una certificazione DPP europea? =
No. Questa versione gestisce schede volontarie, non profili regolamentati verificati.
= Una modifica cambia subito la scheda pubblica? =
No. La versione pubblica cambia solo pubblicando una nuova revisione.
= I QR utilizzano servizi terzi? =
No. La codifica SVG e PNG avviene localmente.
= I documenti riservati sono nella libreria media pubblica? =
No. Sono memorizzati nel database e scaricati dopo il controllo dei permessi.
= Lo ZIP di export garantisce la continuità normativa? =
No. Servono un piano di dominio/hosting, backup esterni e gli accordi richiesti dal regime applicabile.

== Changelog ==
= 1.0.1 =
Revisione funzionale e sicurezza: code automatiche con lease, import CSV robusto, anteprima documenti per autore, scelta prodotti per nome, traduzioni salvate con la bozza e conservazione degli avvisi.

= 1.0.0 =
Prima implementazione del catalogo autonomo, documenti versionati, QR, import/export, ruoli, richieste fornitore e collegamento facoltativo a WooCommerce.

== Privacy and external services ==

Il plugin non invia telemetria o dati a servizi AI. QR, dati prodotto e documenti vengono gestiti sul sito. Nessun collegamento al registro UE o prestatore esterno è attivo. Le email ai fornitori partono solo con abilitazione aziendale esplicita e usano il trasporto email di WordPress. I log editoriali conservano ID utente, date e motivazioni; i token dei fornitori sono memorizzati soltanto come hash. I documenti riservati restano nel database e richiedono autorizzazione per il download. Immagini e collegamenti inseriti dall’azienda possono riferirsi a siti esterni scelti dall’amministratore; usare media locali per evitare richieste a terzi. I dati sono conservati alla disinstallazione.

== Source code ==

Sorgenti e strumenti di verifica: https://github.com/Giulio001/eu-passport
Il pacchetto contiene tutti gli asset runtime e l’encoder QR locale. Non usa CDN per JavaScript/CSS e non richiede build sul sito. Le dipendenze Node del repository servono soltanto ai test.
