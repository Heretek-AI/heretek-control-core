# Blocksy separation and missing integrations

## Plan and ownership

1. Inspect theme and Companion Pro 2.1.57 on both local sites; probe their native APIs before implementing adapters.
2. Move block tools to an independently enabled Gutenberg extension, preserving compatibility names and saved tool choices.
3. Keep native theme configuration in Themes; move Companion extension tools and new Pro Content Block tools to Plugins.
4. Add missing native embed discovery, template workflow, permission checks, stale-write protection and default-off writes.
5. Verify unit suites, actual WordPress registration, native cache invalidation and an MCP-only test-site template lifecycle. Update the bundled skill and both download formats.

## Implemented surface

| Location | Tools | Coverage |
| --- | --- | --- |
| Blocksy Blocks | Eight individual tools plus two compatibility dispatchers | Context, registered block discovery/schema, mixed Gutenberg page read, add/update/move/remove |
| Themes | blocksy-theme-read/write | Context, curated layout sliders, saved palette/typography/responsive spacing/header/footer discovery |
| Plugins: Companion Extensions | blocksy-extensions-read/write | Existing discovery and native activation/deactivation, with native manager permission enforcement |
| Plugins: Companion Content Blocks | blocksy-content-read/write | Context/types, paginated list, document read, native hooks, create/edit draft, publish/unpublish |

Sixteen tools total, twelve added to the previous four-tool surface. New writes default off with defaults migration 50. Existing compatibility tool choices are retained. The block pack is independently enabled alongside Gutenberg and any standalone builder. Native theme/Companion requirements still apply.

Companion Pro templates cover hook, header, footer, single, archive, 404, nothing_found, popup and maintenance. Creation produces a disabled draft with no conditions. Updates preserve unknown native settings, require current hashes and refuse published documents. Publication is explicit and does not silently enable a disabled block. Use native editor controls for advanced conditions, popup configuration and code-mode documents. Ordinary Gutenberg content tools retain their normal immediate-save behavior; unpublish live templates before using those tools if draft isolation is needed.

Theme writes currently cover maxSiteWidth, narrowContainerWidth and wideOffset using verified native ranges. Complex design settings and header/footer placement graphs are discoverable but read-only. No font/account configuration, white-label changes, code execution, remote starter-site import or arbitrary Companion extension configuration is introduced.

## Validation

- Both sites have Blocksy theme and Companion Pro 2.1.57. The native registry exposes fourteen blocks, including two loop children. The curated placeable catalog now includes twelve blocks with the missing Pro content-block embed.
- Complete source registration smoke confirms sixteen unique tools in their intended platforms and independent Blocksy Blocks activation.
- Native test-site smoke confirms layout write/readback, cache invalidation, stale rejection, exact theme-setting restoration, native condition structure and anonymous rejection.
- MCP lifecycle smoke created fixture 2231 on elementor-mcp.test, updated native content/settings, preserved apostrophes, rejected stale writes/code settings/unconfirmed publication, published, rejected an edit to the published fixture and returned it to draft. Fixture is disabled with no conditions.
- Pro and public suites passed. Dedicated tests cover independent toggles, catalog ownership, upgrade choice preservation, typed theme writes, permissions and condition validation. Skill packaging tests check the same updated body in folder and Claude Desktop downloads.
- No plugin files deployed, no commits or pushes. A test-only WP-CLI bridge loads the two new source adapters with the test site's existing plugin. It exposes their abilities and enables their tools only in that process; it does not persist tool or plugin-list options. The full changed catalog/registration is verified against the dev checkout separately.

## Native API findings

- Blocksy database theme-mod reads cache within a request. Call its public db->wipe_cache() before blocksy:dynamic-css:refresh-caches after a theme update.
- Content Blocks use ct_content_block posts, template_type metadata and blocksy_post_meta_options. Respect native CPT capabilities in addition to theme/post permissions.
- Pro content-block embeds expect content_block (post ID) in the render callback, although it is absent from the server registry schema in 2.1.57.
- Blocksy supports registered core style attributes on several blocks. The prior blanket instruction that blocks have no styles was incorrect.
- The native extension manager has its own can() capability gate. Calling activate_extension() alone does not enforce that gate.

## Reproduction

- scripts/e2e/blocksy-inspect.php: read-only native inventory.
- scripts/e2e/blocksy-catalog-smoke.php: source checkout catalog and registration.
- scripts/e2e/blocksy-source-test.php: test-only WP-CLI --require bridge.
- scripts/e2e/blocksy-live-smoke.php: native theme and permissions smoke, restores the changed setting in finally.
- scripts/e2e/blocksy-mcp.py: creates a fresh disabled test fixture via MCP and exercises its lifecycle.

Original integration guidance is in pro/skills/emcp-blocksy/SKILL.md, with credit to CreativeThemes and links to official documentation.

### Landing page verification (2026-09-20)

Created through fresh MCP stdio calls on the test site: page 2235, `https://elementor-mcp.test/form-field-blocksy-integration-test/`, with reusable Companion Pro Content Block 2233. Native Gutenberg sections include hero, introduction, selected spaces, approach, questions, and an embedded project invitation. The Content Block is enabled and published with no global display conditions.

Native editor validation caught a missing saved placeholder for `blocksy/content-block`. Its JavaScript save output is `<div>Blocksy: Content Block Filter</div>` even though PHP renders the document dynamically. The insertion adapter now preserves this placeholder; the regression test covers the saved block structure. All page blocks opened without invalid-content warnings after correction. Desktop images and embed render, and the approach link resolves correctly. The CTA heading explicitly sets white because Blocksy's heading rule overrides the parent text color.

Focused validation: BlocksySeparationTest, 6 tests / 481 assertions passing. Browser viewport overrides did not change the actual desktop viewport, so mobile visual verification remains outstanding.
