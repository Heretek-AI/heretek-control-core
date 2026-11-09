# Visual Composer integration

## Plan

1. Inspect Visual Composer Website Builder 45.16.2 on both local sites and verify its native helpers through WP-CLI.
2. Add a standalone `visual-composer` integration, individual tool sections, default-off writes, selection gating, and AI Chat routing.
3. Support installed-element/schema discovery, page reads and draft creation, staged native document plus rendered HTML/CSS saves, explicit publication, local library discovery and recovery. Keep global design settings and optional Theme Builder assignments read-only.
4. Bundle an original skill for runtime injection and downloadable archives, crediting vendor documentation.
5. Test permissions, stale hashes, document validation, staging isolation, publication and native editor compatibility. Build the demonstration exclusively through MCP on elementor-mcp.test.

## Native findings

- Installed version: 45.16.2. Both local sites have the plugin. The dev runtime exposes 35 elements and `vcv_templates`; optional header/footer/sidebar/layout/popup types are absent.
- `vchelper('HubElements')->getElements()` returns installed element paths and discovery metadata. Attribute schemas reside in local JavaScript bundles, not a PHP schema registry.
- `vcv-pageContent` stores URL-encoded JSON with `elements`. Front-end HTML is stored separately in `post_content`. Visual Composer compiles HTML/CSS in the browser, so a native document save must keep both artifacts together.
- The native AJAX save also writes global CSS options. The integration must preserve globals and stage page-local artifacts independently.
- Native access: `vchelper('AccessUserCapabilities')->canEdit($id)` checks post capability, unfiltered HTML, role-manager post-type access and the posts-page restriction.

Sources: [vendor API](https://dev.visualcomposer.com/), [services](https://dev.visualcomposer.com/public-api/services), and installed vendor source. No existing applicable agent skill was found in the initial search.

## Implemented scope

Sixteen individual tools in six sections, with ten read tools and six default-off write tools. Standalone selection, Tools tab, AI Chat group, Pro loader/manifest, defaults migration 51, test class map and runtime/downloadable skill are wired. The integration respects the existing one-builder selection policy.

Authoring uses a complete native graph plus matching HTML and CSS. It does not compile arbitrary React elements on the server or provide automatic per-element rendering. This limitation is explicit in tool descriptions and the bundled skill. All installed elements are discoverable; authoring was verified with row, column, textBlock, singleImage and basicButton. Global design writes, remote Hub downloads, linked/global template editing, and optional Theme Builder assignments are outside this adapter's write scope. Local custom template application is implemented but no local custom template was present for end-to-end validation.

## Live results

- Landing page 2238: [Still House](https://elementor-mcp.test/still-house-visual-composer-integration-test/), 32 native elements. Created, styled and published through fresh MCP calls on the test site. Native editor save preserved the element graph and images.
- Draft isolation, stale hashes, confirmation checks, recovery restoration, discard, page/style/library discovery, anonymous rejection and native capability denial passed. Permission and recovery tests left published content unchanged.
- Desktop rendering and native 320 px mobile preview checked. The mobile content viewport measured 307 px after its scrollbar, with no overflowing content elements and all three images loaded.
- Source-site catalog smoke found all sixteen tools in their dedicated platform and the runtime skill. Download tests verified both folder and desktop ZIP bundles.
- Pro suite: 2,879 tests, 12,507 assertions, six existing skips. Public suite: 232 tests, 602 assertions. Both passed. Focused tests were rerun after final publication readback/restore safeguards.

The test process loads the current source adapter through `visual-composer-test-tools.php`, enables its tools only for that MCP process, and uses the updated builder registry on the test site. Persistent tool settings are not broadened. The source checkout contains the complete admin integration; this test bridge is not a full plugin deployment.

## Native-save lessons

- Page CSS must also reach `vcvSourceCss`, followed by `vchelper('Assets')->generateSourceCssFile()`. Merely saving the custom CSS editor field does not produce a public stylesheet.
- Native generated column CSS includes its own flex bases and wrapping. Page CSS with additional gaps must account for these on both initial output and the native-save round trip.
- Editor schemas are embedded as inert JSON in development-format bundles. Read one encoded source line at a time, never execute JavaScript; a regex across the complete bundle can exceed PCRE limits.
- Native IDs may begin with digits. Preserve IDs and require unique sibling order, existing parents, no cycles, and matching rendered element wrappers.

Reproducible probes and tests: `scripts/e2e/visual-composer-{inspect,schema-smoke,catalog-smoke,permissions}.php`, `visual-composer-live.py`, `visual-composer-landing.py`, `visual-composer-polish.py`, and `visual-composer-recovery.py`. The landing and polish scripts target their recorded test fixture explicitly. No commits or pushes were made.
