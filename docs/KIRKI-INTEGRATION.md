# Kirki integration

## Target and plan

Kirki 6.3.1 (free and Pro) on the development and test sites. Kirki 6 includes the former Droip visual builder as well as the Customizer framework.

1. Map the installed native page graph, styles, staging, library and permissions.
2. Add a standalone Kirki selector, tab, tool categories and AI tool group. Gutenberg and its block packs remain independent.
3. Implement native draft creation, graph editing, responsive styles, staged saves, publication and version restoration. Require fresh content hashes, native full access and WordPress capabilities.
4. Expose local templates, header/footer components, popups, design settings and CMS discovery without changing site-wide assignments.
5. Bundle an injected/downloadable skill. Test malformed graphs, concurrency, permissions, registration and both skill archive formats.
6. Deploy to elementor-mcp.test and create a landing page through MCP. Verify rendering and a native-editor save round trip. Keep test content off the development site.

## Boundaries

Page content uses native Kirki blocks and style blocks, not an HTML wrapper. Kirki components use a different symbol envelope and must not be treated as ordinary page graphs. Shared library editing, global template assignments, CMS schema mutations, app installation, collaboration messages and external form submissions are not part of this first adapter.

## Sources

- https://kirki.com/releases/
- Installed Kirki 6.3.1 `app/Managers/PageManager.php`, `app/Services/PageService.php`, `app/Models/PostMeta.php`, and `includes/Frontend/Preview/Preview.php`.

## Validation

Implemented 19 tools in eight sections: Discovery, Pages, Elements, Styles, Versions, Library, Settings and CMS. Kirki has its own selector, tab and AI tool group. Eight writes start disabled; existing user choices are preserved during migration 47. The bundled `emcp-kirki` skill is available through runtime discovery and both folder and Claude Desktop downloads.

Test page: [STILLFORM](https://elementor-mcp.test/stillform-kirki-builder-integration-test/) (2187), created and built through the configured test-site MCP stdio server. It contains 56 native elements and 29 style blocks. No test pages were created on the development site.

Verified:

- Native draft creation, staged saves and explicit publication through MCP.
- Stale hashes and missing graph references rejected without applying the proposed content.
- Incremental element insertion/property editing and local stylesheet replacement.
- Published-version restoration returns the exact original graph; test mutations were removed.
- Native Kirki editor Save, MCP readback and publication retain text, links and layout.
- Desktop frontend screenshot, heading completeness, link labels/destinations and no horizontal overflow. Responsive variants exist at the installed tablet/mobile breakpoints; the native editor loads its responsive frames. A separate mobile frontend visual check remains outstanding.
- Runtime checks reject anonymous access, inactive integration, unavailable pages and writes without `unfiltered_html`.
- Both downloadable archives include the same skill as runtime injection.
- Full Pro suite: 2839 tests, 11593 assertions, six existing skips. Public suite: 232 tests, 602 assertions. Subsequent focused Kirki/default-migration checks: 19 tests, 96 assertions.
- Eleven deployed runtime/skill files match source; changed runtime PHP files pass syntax checks.

The default Kirki full-canvas template still renders theme chrome when no custom header/footer is assigned. The demo uses a page-local style to hide that theme's header/footer. Native text leaves omit `children`; buttons contain child text elements. These contracts are recorded in the skill and regression fixture.

Reproduction: `scripts/e2e/kirki-mcp.py` builds the fixture, and its `regression` argument tests incremental editing and version restoration. `kirki-test-tools.php` temporarily enables and restores test-site tool settings. No third-party Kirki code was modified.
