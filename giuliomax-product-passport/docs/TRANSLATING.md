# Translating Giuliomax Product Passport

The original language is **English**. Version 1.1.1 replaces the old Italian-source/custom-English-dictionary approach with WordPress gettext throughout the admin, Design Studio, errors, supplier forms, Gutenberg block, public controls and PDF labels.

## Files

| File | Purpose |
|---|---|
| `languages/giuliomax-product-passport.pot` | English source messages and code references; the starting template for a new translation |
| `languages/giuliomax-product-passport-it_IT.po` | Editable Italian translation, suitable for Poedit or Loco Translate |
| `languages/giuliomax-product-passport-it_IT.mo` | Compiled PHP translations loaded by WordPress |
| `languages/giuliomax-product-passport-it_IT-<hash>.json` | WordPress JavaScript catalogues for admin.js, designer.js and block.js |

The extension is **`.pot`**, not `.pte`. The POT contains the English originals; translators work in a locale-specific PO file. The MO and JSON files are generated from that translation and shipped in the installable ZIP.

## Choosing the interface language

The interface follows WordPress rather than maintaining its own language preference. Set **Settings → General → Site Language**, or choose your personal **Users → Profile → Language**. Choose English there to use the English interface on an Italian site. Administrative REST requests use the user locale as well.

The obsolete `ui_language` plugin setting and `languages/en.json` runtime dictionary are no longer used. Existing product texts, translations, company details, document languages, saved custom template names and published revisions are preserved.

The public page uses its selected content language for interface labels. Italian labels can use the bundled catalogue even when an Italian WordPress core language pack is not installed. When no catalogue is available, labels fall back to English. Changing the interface language never automatically translates product content or document files.

## Updating Italian

1. Edit the Italian PO in Poedit, Loco Translate or a text editor.
2. From the repository root, run `npm run i18n` after installing the development dependencies. This extracts literal English source messages, updates POT/PO references and compiles MO and JavaScript JSON files.
3. Review new empty translations, run the checks and rebuild the installable ZIP with `npm run build`.

PHP extraction uses native PHP when available, otherwise the repository's PHP WebAssembly CLI. JavaScript extraction uses Acorn. Neither Node nor a build step is required on the customer's WordPress site.

New PHP strings use `__()`, `esc_html__()` or `esc_attr__()` with the `giuliomax-product-passport` text domain. New JavaScript strings use literal `wp.i18n.__()` calls. Use placeholders and formatting functions for new complete messages rather than translating user content, identifiers, routes or saved enum values.

For additional locales, standard WordPress tools such as `wp i18n make-pot`, `wp i18n make-mo` and `wp i18n make-json --no-purge` can also be used. Keep `assets/admin.js`, `assets/designer.js` and `assets/block.js` source references so WordPress can resolve the JSON filename hashes. Generated files are included with the plugin and directory language packs can override the bundled PHP catalogue.
