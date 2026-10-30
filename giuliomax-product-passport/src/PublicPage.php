<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class PublicPage
{
    public function hooks(): void
    {
        add_action('template_redirect', [$this,'route'], 0);
        add_shortcode('gmpp_passport', [$this,'shortcode']);
        add_action('wp_enqueue_scripts', static function () {
            global $post;
            if ($post && (has_shortcode($post->post_content, 'gmpp_passport') || has_block('giuliomax/product-passport', $post))) {
                wp_enqueue_style('gmpp-public', plugins_url('assets/style.css', GMPP_FILE), [], GMPP_VERSION);
                Designs::assets();
            }
        });
        add_action('init', function () {
            wp_register_script('gmpp-block', plugins_url('assets/block.js', GMPP_FILE), ['wp-blocks','wp-element','wp-block-editor','wp-components','wp-i18n'], GMPP_VERSION, true);
            wp_set_script_translations('gmpp-block', 'giuliomax-product-passport', GMPP_DIR . 'languages');
            register_block_type('giuliomax/product-passport', ['api_version' => 3,'editor_script' => 'gmpp-block','attributes' => ['uuid' => ['type' => 'string','default' => ''],'view' => ['type' => 'string','default' => 'button']],'render_callback' => fn($a)=>$this->shortcode($a)]);
        });
    }
    private function document(string $title, string $body, string $language = 'en', string $accent = '#135f54'): void
    {
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        wp_enqueue_style('gmpp-public', plugins_url('assets/style.css', GMPP_FILE), [], GMPP_VERSION);
        echo '<!doctype html><html lang="' . esc_attr($language) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($title) . '</title>';
        wp_print_styles(['gmpp-public','gmpp-design']);
        echo '</head><body class="gmpp-public" style="--gmpp-accent:' . esc_attr(sanitize_hex_color($accent) ?: '#135f54') . '">' . $body . '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body is rendered with context escaping by body() or the supplier form below.
    }
    public function route(): void
    {
        if (isset($_GET['gmpp_document'])) {
            Documents::download(absint($_GET['gmpp_document'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only download; Documents::can_read checks publication or authenticated ownership.
        }
        if (isset($_GET['gmpp_supplier'])) {
            $this->supplier(sanitize_text_field(wp_unslash($_GET['gmpp_supplier'])));
            exit;
        }
        if (!isset($_GET['gmpp'])) {
            return;
        }
        $uuid = sanitize_text_field(wp_unslash($_GET['gmpp']));
        $snapshot = Passport::published($uuid);
        if (isset($_GET['preview'])) {
            $row = Store::uuid($uuid);
            if (!$row || !Store::can($row) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'gmpp_preview')) {
                status_header(403);
                exit;
            }
            $snapshot = Passport::snapshot(Passport::get((int)$row['id']));
            nocache_headers();
            header('X-Robots-Tag: noindex, nofollow');
        } else {
            header('Cache-Control: no-cache, must-revalidate');
        }
        if (!$snapshot) {
            status_header(404);
            echo esc_html__('Sheet not available yet.', 'giuliomax-product-passport');
            exit;
        }
        $format = sanitize_key(wp_unslash($_GET['format'] ?? ''));
        if (in_array($format, ['svg','png'], true)) {
            header('Content-Type: ' . ($format === 'svg' ? 'image/svg+xml' : 'image/png'));
            header('Content-Disposition: attachment; filename="product-' . $uuid . '.' . $format . '"');
            header('X-Content-Type-Options: nosniff');
            echo $format === 'svg' ? Qr::svg(Passport::url($uuid)) : Qr::png(Passport::url($uuid)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated binary/SVG, no user markup.
            exit;
        }
        $lang = Passport::language(sanitize_text_field(wp_unslash($_GET['lang'] ?? $snapshot['data']['language'] ?? 'en')));
        if ($format === 'pdf') {
            if (!empty($snapshot['data']['translations'][$lang])) {
                $snapshot['data'] = array_replace($snapshot['data'], $snapshot['data']['translations'][$lang]);
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="product-' . $uuid . '.pdf"');
            echo Pdf::render($snapshot, $lang); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated PDF bytes, sent with application/pdf; not HTML.
            exit;
        } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $body = $this->body($snapshot, $lang, true);
        if (isset($_GET['preview'])) {
            $body = '<div class="gmpp-preview-note">' . esc_html__('Draft preview · visible only to authorized users', 'giuliomax-product-passport') . '</div>' . $body;
        }
        $this->document($snapshot['data']['name'], $body, $lang, $snapshot['company']['accent'] ?? '#135f54');
        exit;
    }
    public function body(array $snap, string $lang, bool $full = false): string
    {
        return I18n::with_language($lang, fn()=>$this->render_body($snap, $lang, $full));
    }
    private function render_body(array $snap, string $lang, bool $full): string
    {
        if (is_array($snap['data']['design'] ?? null)) {
            Designs::assets();
            return Designs::render($snap, $lang);
        }
        $d = $snap['data'];
        $company = $snap['company'];
        $base = $d['language'] ?? 'en';
        $fallback = $lang !== $base;
        if (!empty($d['translations'][$lang])) {
            $d = array_replace($d, $d['translations'][$lang]);
        }

        ob_start();
        ?>
        <main class="gmpp-sheet" id="product-info">
          <header class="gmpp-sheet-header"><?php if (!empty($company['logo'])) :
                ?><img class="gmpp-company-logo" src="<?php echo esc_url($company['logo']); ?>" alt=""><?php
                                            else :
                                                ?><span class="gmpp-mark" aria-hidden="true">P</span><?php
                                            endif; ?><span><?php echo esc_html($company['company']); ?></span><span class="gmpp-subtle"><?php echo esc_html(__('Product information', 'giuliomax-product-passport')); ?></span></header>
          <?php if ($fallback) :
                ?><p class="gmpp-message"><?php echo esc_html(__('Untranslated information remains in the original language: ', 'giuliomax-product-passport') . $base); ?></p><?php
          endif; ?>
          <nav class="gmpp-languages" aria-label="<?php echo esc_attr__('Languages', 'giuliomax-product-passport'); ?>"><?php foreach (array_unique(array_merge([$base], array_keys($d['translations'] ?? []))) as $language) :
                ?><a href="<?php echo esc_url(add_query_arg('lang', $language, Passport::url($snap['uuid']))); ?>" lang="<?php echo esc_attr($language); ?>"><?php echo esc_html(strtoupper($language)); ?></a><?php
                                                  endforeach; ?></nav>
          <?php if (!empty($d['notice'])) :
                ?><aside class="gmpp-alert" role="note"><strong><?php echo esc_html(__('Product notice', 'giuliomax-product-passport')); ?></strong><p><?php echo nl2br(esc_html($d['notice'])); ?></p></aside><?php
          endif; ?>
          <section class="gmpp-hero">
            <?php if (!empty($d['image'])) :
                ?><img class="gmpp-product-image" src="<?php echo esc_url($d['image']); ?>" alt="<?php echo esc_attr($d['name']); ?>"><?php
            endif; ?>
            <div><p class="gmpp-eyebrow"><?php echo esc_html(__('Your product, documented', 'giuliomax-product-passport')); ?></p><h1><?php echo esc_html($d['name']); ?></h1><?php if (!empty($d['description'])) :
                ?><p class="gmpp-description"><?php echo nl2br(esc_html($d['description'])); ?></p><?php
                                         endif; ?>
            <dl class="gmpp-identifiers"><?php foreach (['sku' => __('Reference / SKU', 'giuliomax-product-passport'),'gtin' => 'GTIN','batch' => __('Batch', 'giuliomax-product-passport'),'serial' => __('Serial', 'giuliomax-product-passport')] as $key => $label) :
                if (!empty($d[$key])) :
                    ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($d[$key]); ?></dd></div><?php
                endif;
                                         endforeach; ?></dl></div>
          </section>
          <?php if (!empty($d['attributes'])) :
                ?><section class="gmpp-section"><h2><?php echo esc_html(__('Specifications', 'giuliomax-product-passport')); ?></h2><dl class="gmpp-identifiers"><?php foreach ($d['attributes'] as $name => $value) :
    ?><div><dt><?php echo esc_html($name); ?></dt><dd><?php echo esc_html($value); ?></dd></div><?php
                endforeach; ?></dl></section><?php
          endif; ?>
          <nav class="gmpp-actions" aria-label="<?php echo esc_attr(__('Useful information', 'giuliomax-product-passport')); ?>">
            <?php $manuals = array_values(array_filter($d['documents'] ?? [], static fn($doc)=>$doc['type'] === 'manual')); if ($manuals) :
                ?><a href="#documents"><span aria-hidden="true">↗</span><?php echo esc_html(__('Manual', 'giuliomax-product-passport')); ?></a><?php
            endif; ?>
            <?php if (!empty($d['care'])) :
                ?><a href="#care"><span aria-hidden="true">↗</span><?php echo esc_html(__('Product care', 'giuliomax-product-passport')); ?></a><?php
            endif; ?>
            <?php if (!empty($d['spares']) || !empty($d['compatible'])) :
                ?><a href="#spares"><span aria-hidden="true">↗</span><?php echo esc_html(__('Spare parts', 'giuliomax-product-passport')); ?></a><?php
            endif; ?>
            <?php if (!empty($d['support']) || !empty($company['email']) || !empty($d['support_url'])) :
                ?><a href="#support"><span aria-hidden="true">↗</span><?php echo esc_html(__('Support', 'giuliomax-product-passport')); ?></a><?php
            endif; ?>
          </nav>
          <?php $labels = ['materials' => __('Materials and composition', 'giuliomax-product-passport'),'care' => __('Product care', 'giuliomax-product-passport'),'safety' => __('Safety', 'giuliomax-product-passport'),'end_of_life' => __('End of life', 'giuliomax-product-passport'),'spares' => __('Spare parts and compatibility', 'giuliomax-product-passport'),'support' => __('Support', 'giuliomax-product-passport')]; foreach ($labels as $key => $label) :
                if (!empty($d[$key]) || ($key === 'support' && (!empty($company['email']) || !empty($d['support_url']))) || ($key === 'spares' && !empty($d['compatible']))) : ?>
          <section class="gmpp-section" id="<?php echo esc_attr($key); ?>"><h2><?php echo esc_html($label); ?></h2><?php if (!empty($d[$key])) :
                ?><p><?php echo nl2br(esc_html($d[$key])); ?></p><?php
                                            endif; ?>
                    <?php if ($key === 'support') :
                        ?><?php if (!empty($company['email'])) :
    ?><a href="mailto:<?php echo esc_attr($company['email']); ?>"><?php echo esc_html($company['email']); ?></a><?php
                        endif; ?><?php if (!empty($d['support_url'])) :
    ?> <a href="<?php echo esc_url($d['support_url']); ?>"><?php echo esc_html(__('Contact support', 'giuliomax-product-passport')); ?></a><?php
                        endif; ?><?php
                    endif; ?>
                    <?php if ($key === 'spares') :
                        foreach ($d['compatible'] ?? [] as $part) :
                            ?><p><a href="<?php echo esc_url($part['url']); ?>"><?php echo esc_html($part['name']); ?></a></p><?php
                        endforeach;
                    endif; ?></section>
                <?php endif;
          endforeach; ?>
          <?php if (isset($d['environmental_value']) && $d['environmental_value'] !== '') :
                ?><section class="gmpp-section"><h2><?php echo esc_html(__('Declared environmental information', 'giuliomax-product-passport')); ?></h2><p><?php echo esc_html($d['environmental_value'] . ' ' . ($d['unit'] ?? '')); ?></p><?php foreach (['method' => __('Method', 'giuliomax-product-passport'),'source' => __('Source', 'giuliomax-product-passport'),'data_date' => __('Date', 'giuliomax-product-passport')] as $key => $label) :
    if (!empty($d[$key])) :
        ?><p><?php echo esc_html($label . ': ' . $d[$key]); ?></p><?php
    endif;
                endforeach; ?></section><?php
          endif; ?>
          <?php if (!empty($d['documents'])) :
                ?><section class="gmpp-section" id="documents"><h2><?php echo esc_html(__('Product documents', 'giuliomax-product-passport')); ?></h2><div class="gmpp-doc-grid"><?php foreach ($d['documents'] as $doc) :
    ?><a class="gmpp-doc-card" href="<?php echo esc_url(Documents::url((int)$doc['version_id'])); ?>"><strong><?php echo esc_html($doc['title']); ?></strong><span><?php echo esc_html(strtoupper($doc['language']) . ' · v' . $doc['version'] . ' · ' . strtoupper(pathinfo($doc['filename'], PATHINFO_EXTENSION))); ?></span><?php if (!empty($doc['expires']) && $doc['expires'] < gmdate('Y-m-d')) :
    ?><span><?php echo esc_html(__('Expiry date passed', 'giuliomax-product-passport')); ?></span><?php
    endif; ?></a><?php
                endforeach; ?></div></section><?php
          endif; ?>
          <?php if (!empty($d['original_url'])) :
                ?><section class="gmpp-section"><h2><?php echo esc_html(__('Manufacturer passport', 'giuliomax-product-passport')); ?></h2><a href="<?php echo esc_url($d['original_url']); ?>" rel="noopener"><?php echo esc_html(__('View the original passport', 'giuliomax-product-passport')); ?></a></section><?php
          endif; ?>
          <?php if (!empty($d['successor_url'])) :
                ?><p><a href="<?php echo esc_url($d['successor_url']); ?>"><?php echo esc_html(__('View successor product', 'giuliomax-product-passport')); ?></a></p><?php
          endif; ?>
          <footer class="gmpp-sheet-footer"><p><?php echo esc_html(__('Voluntary digital product sheet. Information supplied by the company.', 'giuliomax-product-passport')); ?></p><p><?php echo esc_html(__('Updated on ', 'giuliomax-product-passport') . substr($snap['published_at'], 0, 10)); ?><?php if ($full) :
                ?> · <a href="<?php echo esc_url(add_query_arg(['format' => 'pdf','lang' => $lang], Passport::url($snap['uuid']))); ?>"><?php echo esc_html(__('Download PDF', 'giuliomax-product-passport')); ?></a><?php
                                               endif; ?></p></footer>
        </main>
        <?php return (string)ob_get_clean();
    }
    public function shortcode($attributes): string
    {
        $a = shortcode_atts(['uuid' => '','view' => 'button'], $attributes);
        $snap = Passport::published(sanitize_text_field($a['uuid']));
        if (!$snap) {
            return '';
        }
        wp_enqueue_style('gmpp-public', plugins_url('assets/style.css', GMPP_FILE), [], GMPP_VERSION);
        if ($a['view'] === 'sheet') {
            return $this->body($snap, $snap['data']['language'] ?? 'en');
        }
        $url = Passport::url($snap['uuid']);
        if ($a['view'] === 'qr') {
            return '<a class="gmpp-inline-qr" href="' . esc_url($url) . '" aria-label="' . esc_attr__('Open product sheet', 'giuliomax-product-passport') . '">' . Qr::svg($url) . '</a>';
        }
        return '<a class="gmpp-public-button" href="' . esc_url($url) . '">' . esc_html__('Information and manuals', 'giuliomax-product-passport') . '</a>';
    }
    private function supplier(string $token): void
    {
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        $request = Suppliers::by_token($token);
        $message = '';
        if (!$request) {
            status_header(410);
            echo esc_html__('This link has expired, been revoked or already been used.', 'giuliomax-product-passport');
            return;
        }
        if (sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'] ?? '')) === 'post') {
            try {
                if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'gmpp_supplier_' . $request['id']) || !empty($_POST['website'])) {
                    throw new \InvalidArgumentException(__('Reload the page before submitting.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
                }
                Suppliers::respond($token, array_map('sanitize_textarea_field', (array)wp_unslash($_POST['values'] ?? [])));
                $this->document(__('Response received', 'giuliomax-product-passport'), ('<main class="gmpp-sheet"><h1>' . esc_html__('Thank you. Your response was received.', 'giuliomax-product-passport') . '</h1><p>' . esc_html__('The company will review the information before publication.', 'giuliomax-product-passport') . '</p></main>'));
                return;
            } catch (\Throwable $e) {
                $message = $e->getMessage();
            }
        }
        $row = Store::one('passports', (int)$request['passport_id']);
        ob_start(); ?><main class="gmpp-sheet"><p class="gmpp-eyebrow"><?php echo esc_html__('Information request', 'giuliomax-product-passport'); ?></p><h1><?php echo esc_html($row['title']); ?></h1><p><?php echo esc_html__('Complete only the requested fields. Your response will be reviewed.', 'giuliomax-product-passport'); ?></p><?php if ($message) :
    ?><p role="alert"><?php echo esc_html($message); ?></p><?php
        endif; ?><form method="post"><?php wp_nonce_field('gmpp_supplier_' . $request['id']); ?><input type="text" name="website" tabindex="-1" autocomplete="off" class="gmpp-honeypot" aria-hidden="true"><?php foreach (Store::decode($request['fields']) as $field) :
    ?><label class="gmpp-field"><?php echo esc_html(Admin::labels()[$field] ?? $field); ?><textarea name="values[<?php echo esc_attr($field); ?>]" rows="4" maxlength="20000"></textarea></label><?php
        endforeach; ?><button class="gmpp-primary" type="submit"><?php echo esc_html__('Submit for review', 'giuliomax-product-passport'); ?></button></form></main><?php $this->document(__('Information request', 'giuliomax-product-passport'), (string)ob_get_clean());
    }
}
