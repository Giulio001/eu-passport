<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Connections
{
    public static function status(): array
    {
        return [
            ['id' => 'registry','name' => __('EU DPP registry', 'giuliomax-product-passport'),'state' => 'not_configured','environment' => Store::is_clone() ? 'test' : 'production','missing' => __('Verified organization with EU Login, credentials, applicable API specifications and registration/receipt testing. Submissions are unavailable in this release.', 'giuliomax-product-passport')],
            ['id' => 'preservation','name' => __('External preservation', 'giuliomax-product-passport'),'state' => 'not_configured','missing' => __('Provider contract, specifications and credentials; continuity and restore verification.', 'giuliomax-product-passport')],
            ['id' => 'resolver','name' => __('External identifier and resolver', 'giuliomax-product-passport'),'state' => 'not_configured','missing' => __('An identifier scheme recognized by the profile, an endpoint and resolution testing. A local UUID has no automatic regulatory recognition.', 'giuliomax-product-passport')]
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
        return array_map(static fn($id, $name)=>['id' => $id,'name' => $name,'version' => '1.0','state' => 'voluntary','verified_at' => '2026-10-29','granularity' => ['model','variant','batch','item'],'required' => ['name'],'sources' => ['https://single-market-economy.ec.europa.eu/single-market/digital-product-passport/explore-our-faqs_en'],'limits' => __('Voluntary information template. No certification or validated regulated sector profile.', 'giuliomax-product-passport')], ['generic','furniture','textile'], [__('Generic product', 'giuliomax-product-passport'),__('Furniture', 'giuliomax-product-passport'),__('Textile', 'giuliomax-product-passport')]);
    }
}
