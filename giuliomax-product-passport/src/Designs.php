<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;

/** Safe visual configurations. Product data and published design snapshots stay separate. */
final class Designs
{
    public const SECTIONS = ['description','materials','care','safety','end_of_life','spares','support','documents','attributes','environmental','custom'];
    private const OPTION = 'gmpp_design_library';

    public static function presets(): array
    {
        $rows = [
            ['studio','Studio','Un prodotto al centro. Pulito, ampio, contemporaneo.','#155e50','#eef2ef','#ffffff','sans','split',20,['description','documents','materials','care','spares','support','safety','end_of_life']],
            ['editorial','Editoriale','Tipografia elegante e ritmo da catalogo.','#253c55','#f4f0e8','#fffdf8','serif','stacked',0,['description','materials','care','documents','end_of_life','spares','support','safety']],
            ['natural','Naturale','Toni caldi e forme morbide per materiali e artigianato.','#546345','#f0ede4','#faf9f4','serif','split',28,['description','materials','care','end_of_life','documents','spares','support','safety']],
            ['technical','Essenziale tecnico','Compatto e preciso, con i documenti subito disponibili.','#254bc0','#f0f3f8','#ffffff','sans','compact',8,['documents','attributes','description','materials','care','spares','support','safety','end_of_life']],
            ['blank','Da zero','Una base neutra: aggiungi le sezioni e scegli il tuo stile.','#333333','#f5f5f5','#ffffff','sans','compact',12,['documents','safety']]
        ];
        $out = [];
        foreach ($rows as [$id,$name,$description,$accent,$background,$surface,$font,$hero,$radius,$sections]) {
            $out[] = self::clean(['library_id' => $id,'name' => $name,'description' => $description,'accent' => $accent,'background' => $background,'surface' => $surface,'font' => $font,'hero' => $hero,'radius' => $radius,'sections' => array_map(static fn($type)=>['type' => $type], $sections)]);
        }
        return $out;
    }
    public static function clean(array $raw): array
    {
        foreach (['library_id','name','description','accent','background','surface','font','hero','radius','density'] as $field) {
            if (isset($raw[$field]) && !is_scalar($raw[$field])) {
                throw new \InvalidArgumentException('Impostazione grafica non valida.');
            }
        }
        $sections = [];
        $seen = [];
        foreach (array_slice((array)($raw['sections'] ?? []), 0, 20) as $section) {
            if (!is_array($section) || !in_array($section['type'] ?? '', self::SECTIONS, true)) {
                continue;
            }
            foreach (['title','text'] as $field) {
                if (isset($section[$field]) && !is_scalar($section[$field])) {
                    throw new \InvalidArgumentException('Contenuto della sezione non valido.');
                }
            }
            $type = $section['type'];
            if ($type !== 'custom' && isset($seen[$type])) {
                continue;
            }
            $seen[$type] = true;
            $sections[] = ['type' => $type,'title' => sanitize_text_field(mb_substr((string)($section['title'] ?? ''), 0, 100)),'text' => $type === 'custom' ? sanitize_textarea_field(mb_substr((string)($section['text'] ?? ''), 0, 4000)) : ''];
        }
        // Safety and manuals must remain reachable even in a minimal design.
        foreach (['documents','safety','support'] as $required) {
            if (!isset($seen[$required])) {
                $sections[] = ['type' => $required,'title' => '','text' => ''];
            }
        }
        for ($i = count($sections) - 1; $i >= 0 && count($sections) > 20; --$i) {
            if (!in_array($sections[$i]['type'], ['documents','safety','support'], true)) {
                unset($sections[$i]);
            }
        }
        $sections = array_values($sections);
        return ['schema' => 1,'library_id' => sanitize_key(mb_substr((string)($raw['library_id'] ?? ''), 0, 60)),'name' => sanitize_text_field(mb_substr((string)($raw['name'] ?? 'Il mio template'), 0, 80)),'description' => sanitize_text_field(mb_substr((string)($raw['description'] ?? ''), 0, 200)),'accent' => sanitize_hex_color($raw['accent'] ?? '') ?: '#155e50','background' => sanitize_hex_color($raw['background'] ?? '') ?: '#eef2ef','surface' => sanitize_hex_color($raw['surface'] ?? '') ?: '#ffffff','font' => ($raw['font'] ?? '') === 'serif' ? 'serif' : 'sans','hero' => in_array($raw['hero'] ?? '', ['split','stacked','compact'], true) ? $raw['hero'] : 'split','radius' => in_array((int)($raw['radius'] ?? 20), [0,8,12,20,28], true) ? (int)($raw['radius'] ?? 20) : 20,'density' => ($raw['density'] ?? '') === 'compact' ? 'compact' : 'comfortable','sections' => $sections];
    }
    public static function library(): array
    {
        $saved = (array)get_option(self::OPTION, ['version' => 0,'templates' => []]);
        $custom = [];
        foreach ((array)($saved['templates'] ?? []) as $design) {
            if (is_array($design)) {
                $custom[] = self::clean($design);
            }
        }
        return ['version' => (int)($saved['version'] ?? 0),'presets' => self::presets(),'templates' => $custom];
    }
    public static function save(array $input, bool $delete = false): array
    {
        if (!current_user_can('gmpp_manage')) {
            throw new \DomainException('Solo il responsabile può modificare la libreria dei template.');
        }
        add_option(self::OPTION, ['version' => 0,'templates' => []], '', false);
        $old = (array)get_option(self::OPTION);
        if (!isset($input['version']) || (int)$input['version'] !== (int)$old['version']) {
            throw new \RuntimeException('La libreria è cambiata. Ricarica prima di salvare.');
        }
        $id = sanitize_key($input['id'] ?? '');
        if ($id && !isset($old['templates'][$id])) {
            throw new \InvalidArgumentException('Template personalizzato non trovato.');
        }
        $next = $old;
        if ($delete) {
            if (!$id) {
                throw new \InvalidArgumentException('Seleziona un template personalizzato.');
            }
            unset($next['templates'][$id]);
        } else {
            if (!$id && count($old['templates']) >= 40) {
                throw new \InvalidArgumentException('La libreria contiene già 40 template. Rimuovi quelli inutilizzati.');
            }
            $id = $id ?: 'custom-' . wp_generate_uuid4();
            $design = self::clean((array)($input['design'] ?? []));
            if (!$design['name']) {
                throw new \InvalidArgumentException('Dai un nome al template.');
            }
            $design['library_id'] = $id;
            $next['templates'][$id] = $design;
        }
        $next['version']++;
        global $wpdb;
        // Compare-and-swap prevents two browser tabs overwriting the library.
        $changed = $wpdb->update($wpdb->options, ['option_value' => maybe_serialize($next)], ['option_name' => self::OPTION,'option_value' => maybe_serialize($old)]);
        if ($changed !== 1) {
            throw new \RuntimeException('Salvataggio non confermato: la libreria è cambiata. Ricarica prima di riprovare.');
        }
        wp_cache_delete(self::OPTION, 'options');
        return ['id' => $id,'library' => self::library()];
    }
    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $rgb = array_map(static function ($value) {
            $c = hexdec($value) / 255;
            return $c <= 0.04045 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        }, str_split($hex, 2));
        return $rgb[0] * 0.2126 + $rgb[1] * 0.7152 + $rgb[2] * 0.0722;
    }
    private static function ink(string $color): string
    {
                $luminance = self::luminance($color);
        if (($luminance + 0.05) / (self::luminance('#111111') + 0.05) >= 4.5) {
            return '#111111';
        }
        return 1.05 / ($luminance + 0.05) >= 4.5 ? '#ffffff' : '#000000';
    }
    public static function assets(): void
    {
        wp_enqueue_style('gmpp-design', plugins_url('assets/design.css', GMPP_FILE), [], GMPP_VERSION);
    }
    public static function sample(): array
    {
        return ['uuid' => '00000000-0000-4000-8000-000000000000','published_at' => gmdate('c'),'company' => ['company' => 'Atelier Alba','email' => 'assistenza@example.test'],'data' => ['name' => 'Una sedia, molte storie.','sku' => 'ALBA / 01','language' => 'it','description' => 'Linee essenziali, materiali da conoscere e piccoli gesti per farli durare. Tutte le informazioni del prodotto, in un unico posto.','materials' => 'Struttura in legno di faggio. Seduta rivestita in tessuto. Le informazioni effettive vengono compilate dall’azienda.','care' => 'Rimuovere la polvere con un panno morbido. Per le istruzioni complete, consultare il manuale del prodotto.','safety' => 'Seguire le avvertenze e le istruzioni riportate nel manuale.','end_of_life' => 'Separare i componenti secondo le indicazioni locali. Contattaci per conoscere i ricambi disponibili.','spares' => 'Piedini e componenti sostituibili. Contatta l’assistenza indicando il riferimento del prodotto.','support' => 'Un dubbio sul montaggio o sulla cura? Siamo qui per aiutarti.','attributes' => ['Collezione' => 'Alba','Finitura' => 'Naturale'],'documents' => [['version_id' => 0,'title' => 'Guida al tuo prodotto','language' => 'it','version' => '1','filename' => 'manuale.pdf']]]];
    }
    public static function preview(array $input): array
    {
        $id = absint($input['passport_id'] ?? 0);
        if ($id) {
            $row = Passport::get($id);
            if (!Store::can($row)) {
                throw new \DomainException('Non puoi visualizzare questa bozza.');
            }
            $snap = Passport::snapshot($row);
        } else {
            $snap = self::sample();
        }
        $snap['data']['design'] = self::clean((array)($input['design'] ?? []));
        self::assets();
        ob_start();
        wp_print_styles('gmpp-design');
        $styles = (string)ob_get_clean();
        $body = self::render($snap, $snap['data']['language'] ?? 'it', !$id);
        return ['html' => '<!doctype html><html lang="' . esc_attr($snap['data']['language'] ?? 'it') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Anteprima design</title>' . $styles . '</head><body style="margin:0">' . $body . '</body></html>','sample' => !$id];
    }
    public static function render(array $snapshot, string $language, bool $sample = false): string
    {
        $data = $snapshot['data'];
        $design = self::clean((array)($data['design'] ?? []));
        $base = $data['language'] ?? 'it';
        $data = array_replace($data, $data['translations'][$language] ?? []);
        $company = $snapshot['company'];
        $english = str_starts_with($language, 'en');
        $t = static fn($it, $en)=>$english ? $en : $it;
        $labels = ['description' => $t('Il prodotto', 'The product'),'materials' => $t('Materiali e composizione', 'Materials and composition'),'care' => $t('Fatto per durare', 'Made to last'),'safety' => $t('Sicurezza', 'Safety'),'end_of_life' => $t('La prossima vita', 'End of life'),'spares' => $t('Ricambi e compatibilità', 'Spare parts and compatibility'),'support' => $t('Parliamone', 'Get in touch'),'documents' => $t('Tutto quello che serve', 'Your product documents'),'attributes' => $t('In dettaglio', 'Details'),'environmental' => $t('Dati ambientali dichiarati', 'Declared environmental data'),'custom' => $t('Da sapere', 'Good to know')];
        $contrast = (max(self::luminance($design['accent']), self::luminance($design['surface'])) + 0.05) / (min(self::luminance($design['accent']), self::luminance($design['surface'])) + 0.05);
        $variables = '--gd-accent:' . $design['accent'] . ';--gd-bg:' . $design['background'] . ';--gd-surface:' . $design['surface'] . ';--gd-ink:' . self::ink($design['surface']) . ';--gd-page-ink:' . self::ink($design['background']) . ';--gd-on-accent:' . self::ink($design['accent']) . ';--gd-link:' . ($contrast >= 4.5 ? $design['accent'] : self::ink($design['surface'])) . ';--gd-radius:' . $design['radius'] . 'px';
        ob_start();
        ?>
        <main class="gmpp-design gd-font-<?php echo esc_attr($design['font']); ?> gd-hero-<?php echo esc_attr($design['hero']); ?> gd-density-<?php echo esc_attr($design['density']); ?>" style="<?php echo esc_attr($variables); ?>">
          <div class="gd-wrap">
            <header class="gd-header"><a class="gd-brand" href="#gd-product"><?php if (!empty($company['logo'])) :
                ?><img src="<?php echo esc_url($company['logo']); ?>" alt=""><?php
                                                                             endif; ?><span><?php echo esc_html($company['company'] ?? ''); ?></span></a><span class="gd-kicker"><?php echo esc_html($t('Informazioni di prodotto', 'Product information')); ?></span></header>
            <?php if ($sample) :
                ?><p class="gd-example"><?php echo esc_html($t('Anteprima con contenuti di esempio', 'Preview with sample content')); ?></p><?php
            endif; ?>
            <nav class="gd-languages" aria-label="<?php echo esc_attr($t('Lingue', 'Languages')); ?>"><?php foreach (array_unique(array_merge([$base], array_keys($data['translations'] ?? []))) as $lang) :
                ?><a href="<?php echo esc_url(add_query_arg('lang', $lang, Passport::url($snapshot['uuid']))); ?>" lang="<?php echo esc_attr($lang); ?>" <?php if ($lang === $language) :
    ?>aria-current="true"<?php
                endif; ?>><?php echo esc_html(strtoupper($lang)); ?></a><?php
                                                  endforeach; ?></nav>
            <?php if ($language !== $base) :
                ?><p class="gd-example"><?php echo esc_html($t('Le informazioni non tradotte restano nella lingua originale: ', 'Untranslated information remains in its original language: ') . $base); ?></p><?php
            endif; ?>
            <?php if (!empty($data['notice'])) :
                ?><aside class="gd-notice" role="note"><strong><?php echo esc_html($t('Avviso sul prodotto', 'Product notice')); ?></strong><p><?php echo nl2br(esc_html($data['notice'])); ?></p></aside><?php
            endif; ?>
            <section class="gd-hero" id="gd-product"><div class="gd-hero-copy"><p class="gd-eyebrow"><?php echo esc_html($data['family'] ?? $t('Conosci il tuo prodotto', 'Know your product')); ?></p><h1><?php echo esc_html($data['name']); ?></h1><dl class="gd-identity"><?php foreach (['sku' => 'SKU','gtin' => 'GTIN','batch' => $t('Lotto', 'Batch'),'serial' => $t('Seriale', 'Serial')] as $key => $label) :
                if (!empty($data[$key])) :
                    ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($data[$key]); ?></dd></div><?php
                endif;
                                                                                                     endforeach; ?></dl><div class="gd-hero-actions"><?php if (!empty($data['documents'])) :
    ?><a class="gd-button" href="#gd-documents"><?php echo esc_html($t('Manuali e documenti', 'Manuals and documents')); ?> <span aria-hidden="true">↗</span></a><?php
                                                                                                     endif; ?><?php if (!empty($company['email']) || !empty($data['support_url']) || !empty($data['support'])) :
    ?><a class="gd-text-link" href="#gd-support"><?php echo esc_html($t('Serve una mano?', 'Need a hand?')); ?></a><?php
                                                                                                     endif; ?></div></div><div class="gd-hero-visual"><?php if (!empty($data['image'])) :
    ?><img src="<?php echo esc_url($data['image']); ?>" alt="<?php echo esc_attr($data['name']); ?>"><?php
                                                                                                     else :
                                                                                                            ?><div class="gd-monogram" aria-hidden="true"><span><?php echo esc_html(mb_substr($data['name'], 0, 1)); ?></span><small><?php echo esc_html($data['sku'] ?? ''); ?></small></div><?php
                                                                                                     endif; ?></div></section>
            <div class="gd-sections">
            <?php foreach ($design['sections'] as $index => $section) :
                $type = $section['type'];
                $text = $type === 'custom' ? $section['text'] : ($data[$type] ?? '');
                $available = $type === 'documents' ? !empty($data['documents']) : ($type === 'attributes' ? !empty($data['attributes']) : ($type === 'environmental' ? !empty($data['environmental_value']) : ($type === 'support' ? (!empty($text) || !empty($company['email']) || !empty($company['phone']) || !empty($data['support_url'])) : ($type === 'spares' ? (!empty($text) || !empty($data['compatible'])) : is_string($text) && $text !== ''))));
                if (!$available) {
                    continue;
                }
                ?><section class="gd-section gd-section-<?php echo esc_attr($type); ?>" id="gd-<?php echo esc_attr($type === 'custom' ? 'custom-' . $index : $type); ?>"><div class="gd-section-heading"><span class="gd-section-number" aria-hidden="true"><?php echo esc_html(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)); ?></span><h2><?php echo esc_html($section['title'] ?: $labels[$type]); ?></h2></div><div class="gd-section-body">
                <?php if ($type === 'documents') :
                    ?><div class="gd-documents"><?php foreach ($data['documents'] as $doc) :
    ?><a class="gd-document" href="<?php echo esc_url(!empty($doc['version_id']) ? Documents::url((int)$doc['version_id']) : '#gd-documents'); ?>"><span class="gd-file-icon" aria-hidden="true">↗</span><span><strong><?php echo esc_html($doc['title']); ?></strong><small><?php echo esc_html(strtoupper($doc['language']) . ' · v' . $doc['version'] . ' · ' . strtoupper(pathinfo($doc['filename'], PATHINFO_EXTENSION))); ?></small><?php if (!empty($doc['expires']) && $doc['expires'] < gmdate('Y-m-d')) :
    ?><small><?php echo esc_html($t('Data di scadenza superata', 'Expiry date passed')); ?></small><?php
    endif; ?></span></a><?php
                    endforeach; ?></div>
                <?php elseif ($type === 'attributes') :
                    ?><dl class="gd-specs"><?php foreach ($data['attributes'] as $key => $value) :
    ?><div><dt><?php echo esc_html($key); ?></dt><dd><?php echo esc_html($value); ?></dd></div><?php
                    endforeach; ?></dl>
                <?php elseif ($type === 'environmental') :
                    ?><p><?php echo esc_html($data['environmental_value'] . ' ' . ($data['unit'] ?? '')); ?></p><?php foreach (['method','source','data_date'] as $field) :
    if (!empty($data[$field])) :
        ?><p><?php echo esc_html((Admin::labels()[$field] ?? $field) . ': ' . $data[$field]); ?></p><?php
    endif;
                    endforeach; ?><p class="gd-disclaimer"><?php echo esc_html($t('Dato dichiarato dall’azienda. Non è una certificazione.', 'Company-declared data. This is not a certification.')); ?></p>
                <?php else :
                    if (is_string($text) && $text !== '') :
                        ?><p><?php echo nl2br(esc_html($text)); ?></p><?php
                    endif; if ($type === 'spares') :
                        foreach ($data['compatible'] ?? [] as $part) :
                            ?><p><a href="<?php echo esc_url($part['url']); ?>"><?php echo esc_html($part['name']); ?></a></p><?php
                        endforeach;
                    endif; if ($type === 'support') :
                        ?><div class="gd-contact"><?php if (!empty($company['email'])) :
    ?><a href="mailto:<?php echo esc_attr($company['email']); ?>"><?php echo esc_html($company['email']); ?></a><?php
                        endif; ?><?php if (!empty($company['phone'])) :
    ?><p><?php echo esc_html($company['phone']); ?></p><?php
                        endif; ?><?php if (!empty($data['support_url'])) :
    ?><a class="gd-button" href="<?php echo esc_url($data['support_url']); ?>"><?php echo esc_html($t('Contatta l’assistenza', 'Contact support')); ?> ↗</a><?php
                        endif; ?></div><?php
                    endif;
                endif; ?>
                </div></section><?php
            endforeach; ?>
            </div>
            <?php if (!empty($data['original_url'])) :
                ?><p class="gd-external"><a href="<?php echo esc_url($data['original_url']); ?>"><?php echo esc_html($t('Consulta il passaporto originale del produttore', 'View the original manufacturer passport')); ?> ↗</a></p><?php
            endif; ?>
            <?php if (!empty($data['successor_url'])) :
                ?><p class="gd-external"><a href="<?php echo esc_url($data['successor_url']); ?>"><?php echo esc_html($t('Consulta il prodotto successore', 'View the successor product')); ?> ↗</a></p><?php
            endif; ?>
            <footer class="gd-footer"><p><?php echo esc_html($t('Scheda digitale volontaria. Informazioni fornite dall’azienda.', 'Voluntary digital sheet. Information supplied by the company.')); ?></p><p><?php echo esc_html($t('Aggiornata il ', 'Updated on ') . substr($snapshot['published_at'], 0, 10)); ?><?php if (!$sample) :
                ?> · <a href="<?php echo esc_url(add_query_arg(['format' => 'pdf','lang' => $language], Passport::url($snapshot['uuid']))); ?>"><?php echo esc_html($t('Copia PDF', 'PDF copy')); ?></a><?php
                                         endif; ?></p></footer>
          </div>
        </main>
        <?php
        return (string)ob_get_clean();
    }
}
