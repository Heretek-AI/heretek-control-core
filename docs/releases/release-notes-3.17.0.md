# EMCP Tools 3.17.0

> Adds independent page-builder integrations and Gutenberg block packs, expands Widget Builder data controls, bundles native authoring skills, and strengthens History recording and rollback.

- New: **Dedicated page-builder integrations.** Bricks, Breakdance, Avada, Divi 5, Thrive Architect, Oxygen 6, Kirki, WPBakery, Beaver Builder and Visual Composer join the existing Elementor and BeBuilder workflows. Each has its own tools and sections. Availability follows the installed vendor version and tested scope; optional vendor features may remain read-only or unsupported.
- New: **Page Builders management screen.** Choose one standalone builder integration while keeping Gutenberg available. Inactive builder tools stay out of discovery, and new write tools start disabled.
- Improved: **Independent Gutenberg block packs.** Spectra, Kadence Blocks, GenerateBlocks, Blocksy Blocks and Otter Blocks have independent toggles and dedicated tools. Theme settings remain in Themes; Blocksy Companion extensions and Pro Content Blocks have separate plugin tools.
- New: **Otter Free and Pro support.** Adds 19 tools for native block discovery and editing, local patterns, generated page styles and allowlisted feature settings. Verified with 45 registered blocks in Free and 63 with Pro. Static blocks retain native Gutenberg markup.
- Improved: **Oxygen integration coverage.** Adds native library, template, component and design-system workflows, version-aware schemas, conditional data handling and recovery checks. The bundled skill and integration guide document tested write boundaries.
- New: **Widget Builder data controls.** Queries can supply posts, products, terms, menus, site data, breadcrumbs, carts and remote JSON. Allowlisted shortcode providers support saved Elementor templates and form plugins. Live data keys have a dedicated Connection section and remain outside cloud settings sync.
- Fixed: **Widget control collisions.** Reserved Elementor setting names are rejected so a custom control cannot accidentally hide a widget through entrance-animation settings. Structured control defaults and live registry synchronization improve generated widget behavior.
- Improved: **Bundled authoring skills.** Builder-specific guidance is available in runtime discovery and both downloadable skill formats, with native editor contracts, test findings and upstream attribution.
- Fixed: **Templates module visibility.** The Templates page follows its module toggle even when another builder is selected. The current library remains Elementor-specific. The general Elementor installation notice no longer appears merely because Elementor is absent.
- Maintenance: **Shared agent guidance.** AGENTS.md is the single source of project guidance; CLAUDE.md imports it. Native integration probes, MCP smoke fixtures and regression tests accompany the implementation.

- Fixed: **Page creation undo removes the created page.** `create-page` now records one creation event, including initial Elementor content, and returns its `change_id`. Initialization no longer produces an edit-only undo that leaves an empty page behind.
- New: **Uploaded media is recorded in History.** `upload-media` returns a creation change ID; undo removes the attachment and verifies deletion of its recorded WordPress-managed files. Creation guards protect later page edits, attachment metadata, and changed image bytes. Windows attachment paths are normalized during undo so WordPress also deletes generated image sizes.
- Fixed: **Page settings and custom CSS changes have undo records.** Rollback preserves exact prior metadata rows, including absent values and escaped CSS, and invalidates Elementor caches. Identical CSS saves do not add redundant entries.
- Improved: **Rollback protects the state an operation actually changed.** Post-field guards include metadata and taxonomy assignments; user and ACF updates also verify their recorded after-state. Unrelated post-field edits remain intact, while an undated draft's automatically advancing clock does not falsely block creation undo.
- Fixed: **Failed restoration is no longer marked successful.** Rollback checks write results and restored values, refuses occupied post or attachment IDs and missing file backups, preserves outer recording suppression, and surfaces History persistence failures. Ledger cleanup retains snapshot blobs when the ledger write fails.
- Changed: **Incomplete or unverified snapshots refuse automatic undo, even with force.** This includes database snapshots without verified row identities and after-state, older post snapshots without complete conflict guards, and generic attachment-deletion snapshots without file backups. History and MCP listings show why undo is unavailable; retained snapshots remain available for inspection. This release does not reconstruct previously omitted actions or provide universal rollback coverage.

## Downloads and updating

- Free: download the attached `emcp-tools-3.17.0.zip`, or update from your WordPress dashboard.
- Pro: update through Freemius with an active license. Premium code is distributed separately.
- After updating, reconnect your MCP client so it reloads the new tools and skills.
- Choose your integration on the Page Builders screen, immediately after Modules, then enable the tools you need. Gutenberg and its supported packs remain independently available.

Requires WordPress 6.9+ and PHP 8.1+. Vendor versions, licenses and authoring scope vary by integration.
