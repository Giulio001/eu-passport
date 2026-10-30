<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Connections
{
    public static function status(): array
    {
        return [
            ['id' => 'registry','name' => 'Registro europeo DPP','state' => 'not_configured','environment' => Store::is_clone() ? 'test' : 'production','missing' => 'Organizzazione verificata con EU Login, credenziali, specifiche API applicabili e test di registrazione/ricevute. Nessun invio disponibile in questa versione.'],
            ['id' => 'preservation','name' => 'Conservazione esterna','state' => 'not_configured','missing' => 'Contratto con un prestatore, specifiche e credenziali; verifica della continuità e del ripristino.'],
            ['id' => 'resolver','name' => 'Identificatore e resolver esterno','state' => 'not_configured','missing' => 'Schema identificativo riconosciuto dal profilo, endpoint e prova di risoluzione. UUID locale privo di riconoscimento normativo automatico.']
        ];
    }
    public static function result(array $receipt): array
    {
        // Extension contract: a connector must supply a traceable, verified receipt.
        $state = in_array($receipt['state'] ?? '', ['not_configured','unverified','active','error'], true) ? $receipt['state'] : 'error';
        if ($state === 'active' && (empty($receipt['operation_id']) || empty($receipt['receipt_id']) || empty($receipt['verified_at']))) {
            $state = 'error';
        }
        return ['state' => $state,'operation_id' => sanitize_text_field($receipt['operation_id'] ?? ''),'receipt_id' => sanitize_text_field($receipt['receipt_id'] ?? ''),'verified_at' => sanitize_text_field($receipt['verified_at'] ?? '')];
    }
    public static function profiles(): array
    {
        return array_map(static fn($id, $name)=>['id' => $id,'name' => $name,'version' => '1.0','state' => 'voluntary','verified_at' => '2026-10-29','granularity' => ['model','variant','batch','item'],'required' => ['name'],'sources' => ['https://single-market-economy.ec.europa.eu/single-market/digital-product-passport/explore-our-faqs_en'],'limits' => 'Modello informativo volontario. Nessuna attestazione o profilo settoriale regolamentato.'], ['generic','furniture','textile'], ['Prodotto generico','Arredo','Tessile']);
    }
}
