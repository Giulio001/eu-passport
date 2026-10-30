<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class PublicPage
{
    public function hooks(): void
    {
        add_action('template_redirect', [$this,'route'], 0);
        add_shortcode('gmpp_passport', [$this,'shortcode']);
        add_action('init', function () {
            wp_register_script('gmpp-block', plugins_url('assets/block.js', GMPP_FILE), ['wp-blocks','wp-element','wp-block-editor','wp-components'], GMPP_VERSION, true);
            register_block_type('giuliomax/product-passport', ['api_version' => 3,'editor_script' => 'gmpp-block','attributes' => ['uuid' => ['type' => 'string','default' => ''],'view' => ['type' => 'string','default' => 'button']],'render_callback' => fn($a)=>$this->shortcode($a)]);
        });
    }
    private function document(string $title, string $body, string $language = 'it', string $accent = '#135f54'): void
    {
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        echo '<!doctype html><html lang="' . esc_attr($language) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($title) . '</title><link rel="stylesheet" href="' . esc_url(plugins_url('assets/style.css', GMPP_FILE)) . '"></head><body class="gmpp-public" style="--gmpp-accent:' . esc_attr(sanitize_hex_color($accent) ?: '#135f54') . '">' . $body . '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body rendered with escaping below.
    }
    public function route(): void
    {
        if (isset($_GET['gmpp_document'])) {
            Documents::download(absint($_GET['gmpp_document']));
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
            echo esc_html__('Scheda non ancora disponibile.', 'giuliomax-product-passport');
            exit;
        }
        $format = sanitize_key($_GET['format'] ?? '');
        if (in_array($format, ['svg','png'], true)) {
            header('Content-Type: ' . ($format === 'svg' ? 'image/svg+xml' : 'image/png'));
            header('Content-Disposition: attachment; filename="product-' . $uuid . '.' . $format . '"');
            header('X-Content-Type-Options: nosniff');
            echo $format === 'svg' ? Qr::svg(Passport::url($uuid)) : Qr::png(Passport::url($uuid)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated binary/SVG, no user markup.
            exit;
        }
        $lang = Passport::language(sanitize_text_field(wp_unslash($_GET['lang'] ?? $snapshot['data']['language'] ?? 'it')));
        if ($format === 'pdf') {
            if (!empty($snapshot['data']['translations'][$lang])) {
                $snapshot['data'] = array_replace($snapshot['data'], $snapshot['data']['translations'][$lang]);
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="product-' . $uuid . '.pdf"');
            echo Pdf::render($snapshot);
            exit;
        } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $body = $this->body($snapshot, $lang, true);
        if (isset($_GET['preview'])) {
            $body = '<div class="gmpp-preview-note">' . esc_html__('Anteprima della bozza · visibile solo agli utenti autorizzati', 'giuliomax-product-passport') . '</div>' . $body;
        }
        $this->document($snapshot['data']['name'], $body, $lang, $snapshot['company']['accent'] ?? '#135f54');
        exit;
    }
    public function body(array $snap, string $lang, bool $full = false): string
    {
        $d = $snap['data'];
        $company = $snap['company'];
        $base = $d['language'] ?? 'it';
        $fallback = $lang !== $base;
        if (!empty($d['translations'][$lang])) {
            $d = array_replace($d, $d['translations'][$lang]);
        }
        $english = str_starts_with($lang, 'en');
        $t = static fn($it, $en)=>$english ? $en : $it;
        ob_start();
        ?>
        <main class="gmpp-sheet" id="product-info">
          <header class="gmpp-sheet-header"><?php if (!empty($company['logo'])) :
                ?><img class="gmpp-company-logo" src="<?php echo esc_url($company['logo']); ?>" alt=""><?php
                                            else :
                                                ?><span class="gmpp-mark" aria-hidden="true">P</span><?php
                                            endif; ?><span><?php echo esc_html($company['company']); ?></span><span class="gmpp-subtle"><?php echo esc_html($t('Informazioni prodotto', 'Product information')); ?></span></header>
          <?php if ($fallback) :
                ?><p class="gmpp-message"><?php echo esc_html($t('Le informazioni non tradotte restano nella lingua originale: ', 'Untranslated information remains in the original language: ') . $base); ?></p><?php
          endif; ?>
          <nav class="gmpp-languages" aria-label="Lingue / Languages"><?php foreach (array_unique(array_merge([$base], array_keys($d['translations'] ?? []))) as $language) :
                ?><a href="<?php echo esc_url(add_query_arg('lang', $language, Passport::url($snap['uuid']))); ?>" lang="<?php echo esc_attr($language); ?>"><?php echo esc_html(strtoupper($language)); ?></a><?php
                                                                      endforeach; ?></nav>
          <?php if (!empty($d['notice'])) :
                ?><aside class="gmpp-alert" role="note"><strong><?php echo esc_html($t('Avviso sul prodotto', 'Product notice')); ?></strong><p><?php echo nl2br(esc_html($d['notice'])); ?></p></aside><?php
          endif; ?>
          <section class="gmpp-hero">
            <?php if (!empty($d['image'])) :
                ?><img class="gmpp-product-image" src="<?php echo esc_url($d['image']); ?>" alt="<?php echo esc_attr($d['name']); ?>"><?php
            endif; ?>
            <div><p class="gmpp-eyebrow"><?php echo esc_html($t('La storia del tuo prodotto', 'Your product, documented')); ?></p><h1><?php echo esc_html($d['name']); ?></h1><?php if (!empty($d['description'])) :
                ?><p class="gmpp-description"><?php echo nl2br(esc_html($d['description'])); ?></p><?php
                                         endif; ?>
            <dl class="gmpp-identifiers"><?php foreach (['sku' => 'Riferimento / SKU','gtin' => 'GTIN','batch' => 'Lotto / Batch','serial' => 'Seriale / Serial'] as $key => $label) :
                if (!empty($d[$key])) :
                    ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($d[$key]); ?></dd></div><?php
                endif;
                                         endforeach; ?></dl></div>
          </section>
          <?php if (!empty($d['attributes'])) :
                ?><section class="gmpp-section"><h2><?php echo esc_html($t('Caratteristiche', 'Specifications')); ?></h2><dl class="gmpp-identifiers"><?php foreach ($d['attributes'] as $name => $value) :
    ?><div><dt><?php echo esc_html($name); ?></dt><dd><?php echo esc_html($value); ?></dd></div><?php
                endforeach; ?></dl></section><?php
          endif; ?>
          <nav class="gmpp-actions" aria-label="<?php echo esc_attr($t('Informazioni utili', 'Useful information')); ?>">
            <?php $manuals = array_values(array_filter($d['documents'] ?? [], static fn($doc)=>$doc['type'] === 'manual')); if ($manuals) :
                ?><a href="#documents"><span aria-hidden="true">↗</span><?php echo esc_html($t('Manuale', 'Manual')); ?></a><?php
            endif; ?>
            <?php if (!empty($d['care'])) :
                ?><a href="#care"><span aria-hidden="true">↗</span><?php echo esc_html($t('Cura del prodotto', 'Product care')); ?></a><?php
            endif; ?>
            <?php if (!empty($d['spares']) || !empty($d['compatible'])) :
                ?><a href="#spares"><span aria-hidden="true">↗</span><?php echo esc_html($t('Ricambi', 'Spare parts')); ?></a><?php
            endif; ?>
            <?php if (!empty($d['support']) || !empty($company['email']) || !empty($d['support_url'])) :
                ?><a href="#support"><span aria-hidden="true">↗</span><?php echo esc_html($t('Assistenza', 'Support')); ?></a><?php
            endif; ?>
          </nav>
          <?php $labels = ['materials' => $t('Materiali e composizione', 'Materials and composition'),'care' => $t('Cura del prodotto', 'Product care'),'safety' => $t('Sicurezza', 'Safety'),'end_of_life' => $t('Una seconda vita', 'End of life'),'spares' => $t('Ricambi e compatibilità', 'Spare parts and compatibility'),'support' => $t('Assistenza', 'Support')]; foreach ($labels as $key => $label) :
                if (!empty($d[$key]) || ($key === 'support' && (!empty($company['email']) || !empty($d['support_url']))) || ($key === 'spares' && !empty($d['compatible']))) : ?>
          <section class="gmpp-section" id="<?php echo esc_attr($key); ?>"><h2><?php echo esc_html($label); ?></h2><?php if (!empty($d[$key])) :
                ?><p><?php echo nl2br(esc_html($d[$key])); ?></p><?php
                                            endif; ?>
                    <?php if ($key === 'support') :
                        ?><?php if (!empty($company['email'])) :
    ?><a href="mailto:<?php echo esc_attr($company['email']); ?>"><?php echo esc_html($company['email']); ?></a><?php
                        endif; ?><?php if (!empty($d['support_url'])) :
    ?> <a href="<?php echo esc_url($d['support_url']); ?>"><?php echo esc_html($t('Contatta l’assistenza', 'Contact support')); ?></a><?php
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
                ?><section class="gmpp-section"><h2><?php echo esc_html($t('Informazioni ambientali dichiarate', 'Declared environmental information')); ?></h2><p><?php echo esc_html($d['environmental_value'] . ' ' . ($d['unit'] ?? '')); ?></p><?php foreach (['method' => 'Metodo / Method','source' => 'Fonte / Source','data_date' => 'Data / Date'] as $key => $label) :
    if (!empty($d[$key])) :
        ?><p><?php echo esc_html($label . ': ' . $d[$key]); ?></p><?php
    endif;
                endforeach; ?></section><?php
          endif; ?>
          <?php if (!empty($d['documents'])) :
                ?><section class="gmpp-section" id="documents"><h2><?php echo esc_html($t('Documenti del prodotto', 'Product documents')); ?></h2><div class="gmpp-doc-grid"><?php foreach ($d['documents'] as $doc) :
    ?><a class="gmpp-doc-card" href="<?php echo esc_url(Documents::url((int)$doc['version_id'])); ?>"><strong><?php echo esc_html($doc['title']); ?></strong><span><?php echo esc_html(strtoupper($doc['language']) . ' · v' . $doc['version'] . ' · ' . strtoupper(pathinfo($doc['filename'], PATHINFO_EXTENSION))); ?></span><?php if (!empty($doc['expires']) && $doc['expires'] < gmdate('Y-m-d')) :
    ?><span><?php echo esc_html($t('Data di scadenza superata', 'Expiry date passed')); ?></span><?php
    endif; ?></a><?php
                endforeach; ?></div></section><?php
          endif; ?>
          <?php if (!empty($d['original_url'])) :
                ?><section class="gmpp-section"><h2><?php echo esc_html($t('Passaporto del produttore', 'Manufacturer passport')); ?></h2><a href="<?php echo esc_url($d['original_url']); ?>" rel="noopener"><?php echo esc_html($t('Consulta il passaporto originale', 'View the original passport')); ?></a></section><?php
          endif; ?>
          <?php if (!empty($d['successor_url'])) :
                ?><p><a href="<?php echo esc_url($d['successor_url']); ?>"><?php echo esc_html($t('Consulta il prodotto successore', 'View successor product')); ?></a></p><?php
          endif; ?>
          <footer class="gmpp-sheet-footer"><p><?php echo esc_html($t('Scheda digitale volontaria. Le informazioni sono fornite dall’azienda.', 'Voluntary digital product sheet. Information supplied by the company.')); ?></p><p><?php echo esc_html($t('Aggiornata il ', 'Updated on ') . substr($snap['published_at'], 0, 10)); ?><?php if ($full) :
                ?> · <a href="<?php echo esc_url(add_query_arg(['format' => 'pdf','lang' => $lang], Passport::url($snap['uuid']))); ?>"><?php echo esc_html($t('Scarica la scheda PDF', 'Download PDF')); ?></a><?php
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
            return $this->body($snap, $snap['data']['language'] ?? 'it');
        }
        $url = Passport::url($snap['uuid']);
        if ($a['view'] === 'qr') {
            return '<a class="gmpp-inline-qr" href="' . esc_url($url) . '" aria-label="' . esc_attr__('Apri la scheda prodotto', 'giuliomax-product-passport') . '">' . Qr::svg($url) . '</a>';
        }
        return '<a class="gmpp-public-button" href="' . esc_url($url) . '">' . esc_html__('Informazioni e manuali', 'giuliomax-product-passport') . '</a>';
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
            echo esc_html__('Collegamento scaduto, revocato o già utilizzato.', 'giuliomax-product-passport');
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'gmpp_supplier_' . $request['id']) || !empty($_POST['website'])) {
                    throw new \InvalidArgumentException('Ricarica la pagina prima di inviare.');
                }
                Suppliers::respond($token, wp_unslash((array)($_POST['values'] ?? [])));
                $this->document('Risposta ricevuta', '<main class="gmpp-sheet"><h1>Grazie, risposta ricevuta.</h1><p>I dati saranno revisionati dall’azienda prima della pubblicazione.</p></main>');
                return;
            } catch (\Throwable $e) {
                $message = $e->getMessage();
            }
        }
        $row = Store::one('passports', (int)$request['passport_id']);
        ob_start(); ?><main class="gmpp-sheet"><p class="gmpp-eyebrow">Richiesta informazioni</p><h1><?php echo esc_html($row['title']); ?></h1><p>Compila solo i dati richiesti. La risposta verrà sottoposta a revisione.</p><?php if ($message) :
    ?><p role="alert"><?php echo esc_html($message); ?></p><?php
        endif; ?><form method="post"><?php wp_nonce_field('gmpp_supplier_' . $request['id']); ?><input type="text" name="website" tabindex="-1" autocomplete="off" class="gmpp-honeypot" aria-hidden="true"><?php foreach (Store::decode($request['fields']) as $field) :
    ?><label class="gmpp-field"><?php echo esc_html(Admin::labels()[$field] ?? $field); ?><textarea name="values[<?php echo esc_attr($field); ?>]" rows="4" maxlength="20000"></textarea></label><?php
        endforeach; ?><button class="gmpp-primary" type="submit">Invia per revisione</button></form></main><?php $this->document('Richiesta informazioni', (string)ob_get_clean());
    }
}
