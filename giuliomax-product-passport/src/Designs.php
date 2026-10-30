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
            ['studio',__('Studio', 'giuliomax-product-passport'),__('Put your product first. Clean, spacious and contemporary.', 'giuliomax-product-passport'),'#155e50','#eef2ef','#ffffff','sans','split',20,['description','documents','materials','care','spares','support','safety','end_of_life']],
            ['editorial',__('Editorial', 'giuliomax-product-passport'),__('Elegant typography with a catalogue feel.', 'giuliomax-product-passport'),'#253c55','#f4f0e8','#fffdf8','serif','stacked',0,['description','materials','care','documents','end_of_life','spares','support','safety']],
            ['natural',__('Natural', 'giuliomax-product-passport'),__('Warm tones and soft shapes for materials and craftsmanship.', 'giuliomax-product-passport'),'#546345','#f0ede4','#faf9f4','serif','split',28,['description','materials','care','end_of_life','documents','spares','support','safety']],
            ['technical',__('Technical essentials', 'giuliomax-product-passport'),__('Compact and precise, with documents at hand.', 'giuliomax-product-passport'),'#254bc0','#f0f3f8','#ffffff','sans','compact',8,['documents','attributes','description','materials','care','spares','support','safety','end_of_life']],
            ['blank',__('From scratch', 'giuliomax-product-passport'),__('A neutral starting point: add sections and choose your style.', 'giuliomax-product-passport'),'#333333','#f5f5f5','#ffffff','sans','compact',12,['documents','safety']]
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
                throw new \InvalidArgumentException(__('Invalid design setting.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
                    throw new \InvalidArgumentException(__('Invalid section content.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
        return ['schema' => 1,'library_id' => sanitize_key(mb_substr((string)($raw['library_id'] ?? ''), 0, 60)),'name' => sanitize_text_field(mb_substr((string)($raw['name'] ?? __('My template', 'giuliomax-product-passport')), 0, 80)),'description' => sanitize_text_field(mb_substr((string)($raw['description'] ?? ''), 0, 200)),'accent' => sanitize_hex_color($raw['accent'] ?? '') ?: '#155e50','background' => sanitize_hex_color($raw['background'] ?? '') ?: '#eef2ef','surface' => sanitize_hex_color($raw['surface'] ?? '') ?: '#ffffff','font' => ($raw['font'] ?? '') === 'serif' ? 'serif' : 'sans','hero' => in_array($raw['hero'] ?? '', ['split','stacked','compact'], true) ? $raw['hero'] : 'split','radius' => in_array((int)($raw['radius'] ?? 20), [0,8,12,20,28], true) ? (int)($raw['radius'] ?? 20) : 20,'density' => ($raw['density'] ?? '') === 'compact' ? 'compact' : 'comfortable','sections' => $sections];
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
            throw new \DomainException(__('Only users with management permissions can edit the template library.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        add_option(self::OPTION, ['version' => 0,'templates' => []], '', false);
        $old = (array)get_option(self::OPTION);
        if (!isset($input['version']) || (int)$input['version'] !== (int)$old['version']) {
            throw new \RuntimeException(__('The library has changed. Reload before saving.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        $id = sanitize_key($input['id'] ?? '');
        if ($id && !isset($old['templates'][$id])) {
            throw new \InvalidArgumentException(__('Custom template not found.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        $next = $old;
        if ($delete) {
            if (!$id) {
                throw new \InvalidArgumentException(__('Select a custom template.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
            }
            unset($next['templates'][$id]);
        } else {
            if (!$id && count($old['templates']) >= 40) {
                throw new \InvalidArgumentException(__('The library already contains 40 templates. Remove unused templates first.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
            }
            $id = $id ?: 'custom-' . wp_generate_uuid4();
            $design = self::clean((array)($input['design'] ?? []));
            if (!$design['name']) {
                throw new \InvalidArgumentException(__('Give your template a name.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
            }
            $design['library_id'] = $id;
            $next['templates'][$id] = $design;
        }
        $next['version']++;
        global $wpdb;
        // Compare-and-swap prevents two browser tabs overwriting the library.
        $changed = $wpdb->update($wpdb->options, ['option_value' => maybe_serialize($next)], ['option_name' => self::OPTION,'option_value' => maybe_serialize($old)]);
        if ($changed !== 1) {
            throw new \RuntimeException(__('Save not confirmed: the library has changed. Reload before trying again.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
        return ['uuid' => '00000000-0000-4000-8000-000000000000','published_at' => gmdate('c'),'company' => ['company' => 'Atelier Alba','email' => 'assistenza@example.test'],'data' => ['name' => __('One chair, many stories.', 'giuliomax-product-passport'),'sku' => 'ALBA / 01','language' => 'en','description' => __('Clean lines, materials to understand and simple ways to make them last. All product information in one place.', 'giuliomax-product-passport'),'materials' => __('Beech wood frame with an upholstered seat. The company supplies the actual product information.', 'giuliomax-product-passport'),'care' => __('Dust with a soft cloth. Refer to the product manual for complete instructions.', 'giuliomax-product-passport'),'safety' => __('Follow the warnings and instructions in the manual.', 'giuliomax-product-passport'),'end_of_life' => __('Separate components according to local guidance. Contact us for available spare parts.', 'giuliomax-product-passport'),'spares' => __('Replaceable feet and components. Contact support with the product reference.', 'giuliomax-product-passport'),'support' => __('Need help with assembly or care? We are here to help.', 'giuliomax-product-passport'),'attributes' => [__('Collection', 'giuliomax-product-passport') => 'Alba',__('Finish', 'giuliomax-product-passport') => __('Natural', 'giuliomax-product-passport')],'documents' => [['version_id' => 0,'title' => __('Your product guide', 'giuliomax-product-passport'),'language' => 'en','version' => '1','filename' => 'manuale.pdf']]]];
    }
    public static function preview(array $input): array
    {
        $id = absint($input['passport_id'] ?? 0);
        if ($id) {
            $row = Passport::get($id);
            if (!Store::can($row)) {
                throw new \DomainException(__('You cannot preview this draft.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
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
        $body = self::render($snap, $snap['data']['language'] ?? 'en', !$id);
        return ['html' => '<!doctype html><html lang="' . esc_attr($snap['data']['language'] ?? 'en') . ('"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__('Design preview', 'giuliomax-product-passport') . '</title>') . $styles . '</head><body style="margin:0">' . $body . '</body></html>','sample' => !$id];
    }
    public static function render(array $snapshot, string $language, bool $sample = false): string
    {
        return I18n::with_language($language, fn()=>self::render_body($snapshot, $language, $sample));
    }
    private static function render_body(array $snapshot, string $language, bool $sample): string
    {
        $data = $snapshot['data'];
        $design = self::clean((array)($data['design'] ?? []));
        $base = $data['language'] ?? 'en';
        $data = array_replace($data, $data['translations'][$language] ?? []);
        $company = $snapshot['company'];

        $labels = ['description' => __('The product', 'giuliomax-product-passport'),'materials' => __('Materials and composition', 'giuliomax-product-passport'),'care' => __('Made to last', 'giuliomax-product-passport'),'safety' => __('Safety', 'giuliomax-product-passport'),'end_of_life' => __('End of life', 'giuliomax-product-passport'),'spares' => __('Spare parts and compatibility', 'giuliomax-product-passport'),'support' => __('Get in touch', 'giuliomax-product-passport'),'documents' => __('Your product documents', 'giuliomax-product-passport'),'attributes' => __('Details', 'giuliomax-product-passport'),'environmental' => __('Declared environmental data', 'giuliomax-product-passport'),'custom' => __('Good to know', 'giuliomax-product-passport')];
        $contrast = (max(self::luminance($design['accent']), self::luminance($design['surface'])) + 0.05) / (min(self::luminance($design['accent']), self::luminance($design['surface'])) + 0.05);
        $variables = '--gd-accent:' . $design['accent'] . ';--gd-bg:' . $design['background'] . ';--gd-surface:' . $design['surface'] . ';--gd-ink:' . self::ink($design['surface']) . ';--gd-page-ink:' . self::ink($design['background']) . ';--gd-on-accent:' . self::ink($design['accent']) . ';--gd-link:' . ($contrast >= 4.5 ? $design['accent'] : self::ink($design['surface'])) . ';--gd-radius:' . $design['radius'] . 'px';
        ob_start();
        ?>
        <main class="gmpp-design gd-font-<?php echo esc_attr($design['font']); ?> gd-hero-<?php echo esc_attr($design['hero']); ?> gd-density-<?php echo esc_attr($design['density']); ?>" style="<?php echo esc_attr($variables); ?>">
          <div class="gd-wrap">
            <header class="gd-header"><a class="gd-brand" href="#gd-product"><?php if (!empty($company['logo'])) :
                ?><img src="<?php echo esc_url($company['logo']); ?>" alt=""><?php
                                                                             endif; ?><span><?php echo esc_html($company['company'] ?? ''); ?></span></a><span class="gd-kicker"><?php echo esc_html(__('Product information', 'giuliomax-product-passport')); ?></span></header>
            <?php if ($sample) :
                ?><p class="gd-example"><?php echo esc_html(__('Preview with sample content', 'giuliomax-product-passport')); ?></p><?php
            endif; ?>
            <nav class="gd-languages" aria-label="<?php echo esc_attr(__('Languages', 'giuliomax-product-passport')); ?>"><?php foreach (array_unique(array_merge([$base], array_keys($data['translations'] ?? []))) as $lang) :
                ?><a href="<?php echo esc_url(add_query_arg('lang', $lang, Passport::url($snapshot['uuid']))); ?>" lang="<?php echo esc_attr($lang); ?>" <?php if ($lang === $language) :
    ?>aria-current="true"<?php
                endif; ?>><?php echo esc_html(strtoupper($lang)); ?></a><?php
                                                  endforeach; ?></nav>
            <?php if ($language !== $base) :
                ?><p class="gd-example"><?php echo esc_html(__('Untranslated information remains in its original language: ', 'giuliomax-product-passport') . $base); ?></p><?php
            endif; ?>
            <?php if (!empty($data['notice'])) :
                ?><aside class="gd-notice" role="note"><strong><?php echo esc_html(__('Product notice', 'giuliomax-product-passport')); ?></strong><p><?php echo nl2br(esc_html($data['notice'])); ?></p></aside><?php
            endif; ?>
            <section class="gd-hero" id="gd-product"><div class="gd-hero-copy"><p class="gd-eyebrow"><?php echo esc_html($data['family'] ?? __('Know your product', 'giuliomax-product-passport')); ?></p><h1><?php echo esc_html($data['name']); ?></h1><dl class="gd-identity"><?php foreach (['sku' => 'SKU','gtin' => 'GTIN','batch' => __('Batch', 'giuliomax-product-passport'),'serial' => __('Serial', 'giuliomax-product-passport')] as $key => $label) :
                if (!empty($data[$key])) :
                    ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($data[$key]); ?></dd></div><?php
                endif;
                                                                                                     endforeach; ?></dl><div class="gd-hero-actions"><?php if (!empty($data['documents'])) :
    ?><a class="gd-button" href="#gd-documents"><?php echo esc_html(__('Manuals and documents', 'giuliomax-product-passport')); ?> <span aria-hidden="true">↗</span></a><?php
                                                                                                     endif; ?><?php if (!empty($company['email']) || !empty($data['support_url']) || !empty($data['support'])) :
    ?><a class="gd-text-link" href="#gd-support"><?php echo esc_html(__('Need a hand?', 'giuliomax-product-passport')); ?></a><?php
                                                                                                     endif; ?></div></div><div class="gd-hero-visual"><?php if (!empty($data['image'])) :
    ?><img src="<?php echo esc_url($data['image']); ?>" alt="<?php echo esc_attr($data['name']); ?>"><?php
                                                                                                     else :
                                                                                                            ?><div class="gd-monogram" aria-hidden="true"><span><?php echo esc_html(mb_substr($data['name'], 0, 1)); ?></span><small><?php echo esc_html($data['sku'] ?? ''); ?></small></div><?php
                                                                                                     endif; ?></div></section>
            <div class="gd-sections">
            <?php $visible = 0; foreach ($design['sections'] as $index => $section) :
                $type = $section['type'];
                $text = $type === 'custom' ? $section['text'] : ($data[$type] ?? '');
                $available = $type === 'documents' ? !empty($data['documents']) : ($type === 'attributes' ? !empty($data['attributes']) : ($type === 'environmental' ? !empty($data['environmental_value']) : ($type === 'support' ? (!empty($text) || !empty($company['email']) || !empty($company['phone']) || !empty($data['support_url'])) : ($type === 'spares' ? (!empty($text) || !empty($data['compatible'])) : is_string($text) && $text !== ''))));
                if (!$available) {
                    continue;
                }
                ++$visible;
                ?><section class="gd-section gd-section-<?php echo esc_attr($type); ?>" id="gd-<?php echo esc_attr($type === 'custom' ? 'custom-' . $index : $type); ?>"><div class="gd-section-heading"><span class="gd-section-number" aria-hidden="true"><?php echo esc_html(str_pad((string)$visible, 2, '0', STR_PAD_LEFT)); ?></span><h2><?php echo esc_html($section['title'] ?: $labels[$type]); ?></h2></div><div class="gd-section-body">
                <?php if ($type === 'documents') :
                    ?><div class="gd-documents"><?php foreach ($data['documents'] as $doc) :
    ?><a class="gd-document" href="<?php echo esc_url(!empty($doc['version_id']) ? Documents::url((int)$doc['version_id']) : '#gd-documents'); ?>"><span class="gd-file-icon" aria-hidden="true">↗</span><span><strong><?php echo esc_html($doc['title']); ?></strong><small><?php echo esc_html(strtoupper($doc['language']) . ' · v' . $doc['version'] . ' · ' . strtoupper(pathinfo($doc['filename'], PATHINFO_EXTENSION))); ?></small><?php if (!empty($doc['expires']) && $doc['expires'] < gmdate('Y-m-d')) :
    ?><small><?php echo esc_html(__('Expiry date passed', 'giuliomax-product-passport')); ?></small><?php
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
                    endforeach; ?><p class="gd-disclaimer"><?php echo esc_html(__('Company-declared data. This is not a certification.', 'giuliomax-product-passport')); ?></p>
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
    ?><a class="gd-button" href="<?php echo esc_url($data['support_url']); ?>"><?php echo esc_html(__('Contact support', 'giuliomax-product-passport')); ?> ↗</a><?php
                        endif; ?></div><?php
                    endif;
                endif; ?>
                </div></section><?php
            endforeach; ?>
            </div>
            <?php if (!empty($data['original_url'])) :
                ?><p class="gd-external"><a href="<?php echo esc_url($data['original_url']); ?>"><?php echo esc_html(__('View the original manufacturer passport', 'giuliomax-product-passport')); ?> ↗</a></p><?php
            endif; ?>
            <?php if (!empty($data['successor_url'])) :
                ?><p class="gd-external"><a href="<?php echo esc_url($data['successor_url']); ?>"><?php echo esc_html(__('View the successor product', 'giuliomax-product-passport')); ?> ↗</a></p><?php
            endif; ?>
            <footer class="gd-footer"><p><?php echo esc_html(__('Voluntary digital sheet. Information supplied by the company.', 'giuliomax-product-passport')); ?></p><p><?php echo esc_html(__('Updated on ', 'giuliomax-product-passport') . substr($snapshot['published_at'], 0, 10)); ?><?php if (!$sample) :
                ?> · <a href="<?php echo esc_url(add_query_arg(['format' => 'pdf','lang' => $language], Passport::url($snapshot['uuid']))); ?>"><?php echo esc_html(__('PDF copy', 'giuliomax-product-passport')); ?></a><?php
                                         endif; ?></p></footer>
          </div>
        </main>
        <?php
        return (string)ob_get_clean();
    }
}
