# Otter Blocks integration

## Plan

1. Verify Free and Pro 3.2.5 native registries on the installed sites.
2. Add an independent Gutenberg pack, its tab, default-off writes and discovery group.
3. Support native serialized block editing, local patterns, native CSS compilation and allowlisted feature switches.
4. Bundle runtime/downloadable skill with native authoring constraints and vendor attribution.
5. Exercise content and permissions through fresh MCP on elementor-mcp.test, build a landing page, validate the native editor and responsive frontend, and run both test suites.

## Native findings

The development site registers 63 blocks across `themeisle-blocks/` and `atomic-wind/`. Both installed versions are 3.2.5. Otter Pro augments schemas and renderers, so discovery uses the live WordPress registry rather than a hardcoded Free catalog. Static blocks require their saved HTML, including native wrappers. Otter CSS is compiled by `ThemeIsle\GutenbergBlocks\CSS\CSS_Handler::generate_css_file()` after saving; Atomic Wind has its own browser-generated CSS cache.

## Scope

19 tools: nine reads and ten default-off writes, grouped into Discovery, Pages, Blocks, Patterns, Styles and Settings. Otter runs independently beside Gutenberg and the selected standalone builder. Native attributes cover Pro blocks, responsive design, custom CSS and extensions. Whole-block updates preserve static save contracts. Pattern discovery uses locally registered patterns, including Pro patterns when the vendor registers them; no remote library download or image localization is performed.

Credentials, payment execution, form submission records, remote template sync and WooCommerce template assignment are outside this integration. WooCommerce block schemas can be discovered and authored when registered, but require the appropriate product context. The adapter does not claim server-side validation of Gutenberg JavaScript save output. Native editor validation remains required for authored static markup.

## Validation results

- Test landing page: [Fieldwork Coffee](https://elementor-mcp.test/fieldwork-otter-test/), page 2242. Six sections, native Otter headings/columns, Pro Business Hours, native Accordion and four existing media-library images. Created and published through MCP, then saved through Gutenberg without recovery warnings. MCP readback confirmed published status and preserved Pro/FAQ blocks after the native save.
- Desktop frontend and Gutenberg mobile preview checked. The native mobile content viewport measured 465 px, with no horizontal overflow or overflowing elements; all four images loaded. Frontend FAQ interaction and native Business Hours output verified. Frontend images are lazy-loaded, so scroll menu photos into view before assessing a full-page screenshot.
- MCP smoke fixture: draft 2247. Add, update, move, remove, pattern insertion, CSS rebuilding, stale-hash rejection, duplicate-ID rejection, self-move rejection and confirmation gates passed. Settings roundtrip left the selected feature switches unchanged. Local pattern discovery returned 207 patterns on the test site.
- Live permission smoke denied anonymous access, edit_posts, edit_post, unfiltered_html, edit_pages, publish_pages and manage_options without changing the landing page.
- Read-only Free-only probe used WP-CLI `--skip-plugins=otter-pro`, leaving persistent plugin activation unchanged: 45 blocks and no Pro Business Hours. Both plugins active: 63 blocks.
- Development-site catalog smoke found all 19 tools in the Otter tab and the runtime skill. Unit tests verify exact skill contents in both folder and desktop downloadable ZIP formats.
- Full Pro suite: 2,887 tests, 12,651 assertions, six existing skips. Public suite: 232 tests, 602 assertions. Both passed.

The test MCP process loads the source adapter with `scripts/e2e/otter-test-tools.php` and enables only its tools for that process. Three test-plugin routing files were updated to recognize the independent pack and its native adapter. This was not a full plugin deployment; the complete tab/catalog/defaults integration is in the source checkout. No commits or pushes were made.

Reproducible probes: `scripts/e2e/otter-inspect.php`, `otter-catalog-smoke.php`, `otter-permissions.php`. MCP clients/fixtures: `otter-mcp.py`, `otter-live-smoke.py`, `otter-landing.py`. The landing script reuses its recorded test ID; the smoke creates an isolated draft.

## Attribution and documentation

Original EMCP adapter and skill, informed by installed ThemeIsle Otter source and [official documentation](https://docs.themeisle.com/otter-page-builder-blocks-extensions/), including [Pro features](https://docs.themeisle.com/otter-page-builder-blocks-extensions/otter-pro-documentation). The repository does not bundle vendor plugin source or modify vendor licensing.
