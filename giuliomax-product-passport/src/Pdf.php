<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
/** Small deterministic PDF writer. Text/QR only, no remote fetching or executable content. */
final class Pdf
{
    public static function render(array $snapshot, ?string $language = null): string
    {
        return I18n::with_language($language ?? $snapshot['data']['language'] ?? 'en', fn()=>self::render_text($snapshot));
    }
    private static function render_text(array $snapshot): string
    {
        $d = $snapshot['data'];
        $c = $snapshot['company'];
        $lines = [$d['name'] ?? '',$c['company'] ?? '', __('Voluntary digital sheet · Revision ', 'giuliomax-product-passport') . ($snapshot['revision'] ?? '') . ' · ' . ($snapshot['published_at'] ?? ''),Passport::url($snapshot['uuid']),''];
        $labels = ['description' => __('Description', 'giuliomax-product-passport'),'sku' => __('Reference', 'giuliomax-product-passport'),'gtin' => 'GTIN','batch' => __('Batch', 'giuliomax-product-passport'),'serial' => __('Serial', 'giuliomax-product-passport'),'materials' => __('Materials', 'giuliomax-product-passport'),'care' => __('Product care', 'giuliomax-product-passport'),'safety' => __('Safety', 'giuliomax-product-passport'),'end_of_life' => __('End of life', 'giuliomax-product-passport'),'spares' => __('Spare parts', 'giuliomax-product-passport'),'support' => __('Support', 'giuliomax-product-passport'),'notice' => __('Notice', 'giuliomax-product-passport'),'method' => __('Method', 'giuliomax-product-passport'),'environmental_value' => __('Environmental value', 'giuliomax-product-passport'),'unit' => __('Unit', 'giuliomax-product-passport'),'source' => __('Source', 'giuliomax-product-passport'),'data_date' => __('Data date', 'giuliomax-product-passport')];
        foreach ($labels as $key => $label) {
            if (isset($d[$key]) && $d[$key] !== '') {
                $lines[] = $label . ': ' . $d[$key];
                $lines[] = '';
            }
        }
        foreach ($d['design']['sections'] ?? [] as $section) {
            if ($section['type'] === 'custom' && !empty($section['text'])) {
                $lines[] = ($section['title'] ?: __('Good to know', 'giuliomax-product-passport')) . ': ' . $section['text'];
                $lines[] = '';
            }
        }
        foreach ($d['documents'] ?? [] as $doc) {
            $lines[] = $doc['title'] . ' · ' . $doc['language'] . ' · v' . $doc['version'];
            $lines[] = Documents::url((int)$doc['version_id']);
        }
        $lines[] = __('This copy captures the indicated revision. Open the link for updates.', 'giuliomax-product-passport');
        $wrapped = [];
        foreach ($lines as $line) {
            $line = str_replace(["\r","\t"], ['',' '], $line);
            foreach (explode("\n", wordwrap($line, 88, "\n", true)) as $part) {
                $wrapped[] = $part;
            }
        }
        $pages = array_chunk($wrapped, 48);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $kids = [];
        foreach ($pages as $index => $page) {
            $id = 4 + $index * 2;
            $streamId = $id + 1;
            $kids[] = "$id 0 R";
            $stream = "BT /F1 10 Tf 14 TL 45 790 Td\n";
            foreach ($page as $line) {
                $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $line) ?: '';
                $text = str_replace(['\\','(',')',"\x00"], ['\\\\','\\(','\\)',''], $text);
                $stream .= '(' . $text . ") Tj T*\n";
            }
            $stream .= "ET\n";
            $stream .= "BT /F1 9 Tf 45 50 Td (" . ($index + 1) . '/' . count($pages) . ") Tj ET\n";
            $objects[$id] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamId 0 R >>";
            $objects[$streamId] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . 'endstream';
        }
        $objects[2] = '<< /Type /Pages /Count ' . count($pages) . ' /Kids [' . implode(' ', $kids) . '] >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $start = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($objects as $id => $object) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        return $pdf . 'trailer << /Size ' . (count($objects) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n$start\n%%EOF\n";
    }
}
