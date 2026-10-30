# Componenti runtime

`giuliomax-product-passport/vendor/qrcode.php` deriva da [Kazuhiko Arase, qrcode-generator/php](https://github.com/kazuhikoarase/qrcode-generator/tree/master/php), licenza MIT, commit upstream `83b7e8fe3fddd3b0368dbafd6ce56995bd25e3c8`. Il copyright e il riferimento alla licenza sono conservati nel sorgente. Modifiche locali: namespace `Giuliomax\Passport\Vendor`, prefisso costanti `GMPP_QR_` e guardia WordPress. Il wrapper seleziona le versioni tramite la capacità effettiva dei blocchi RS per evitare il limite della tabella di capacità rapida dell’upstream PHP.

Tutte le altre dipendenze nel package.json sono strumenti di sviluppo. Non entrano nel pacchetto installabile e non vengono richieste al sito dell’utilizzatore.
