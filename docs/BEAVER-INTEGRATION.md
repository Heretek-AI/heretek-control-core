# Beaver Builder integration

## Plan

1. Inspect installed Pro 2.11.1 APIs, permissions, node/settings formats, draft/publication flow, saved library and optional Themer.
2. Add standalone builder selection, tab, individual tool toggles, AI Chat group and default-off writes.
3. Implement native node-map validation and edits, draft-only staging, explicit publication, page CSS, local library discovery/application and recovery snapshots.
4. Bundle an original runtime skill in both downloadable formats, crediting official documentation.
5. Run unit/catalog/archive tests and runtime permissions; deploy to the test site and build a native page exclusively through MCP. Validate native editor publication and desktop/mobile rendering. Restore write defaults.

## Boundaries

Verified legacy row/column hierarchy and listed core modules support authoring. Other installed schemas remain discoverable. Shared/global/dynamic references and Themer layouts are read-only. No remote template downloads, global design writes, service account changes or template assignment writes. Themer requires its separate installed plugin; report availability explicitly.

## Results

Implemented 22 tools across seven sections: discovery, pages, elements, styles, library, recovery and publishing. Eleven read tools default on; eleven write tools default off. Standalone selection, admin toggles, AI Chat grouping and bundled runtime/downloadable skills are wired.

Verified against Beaver Builder Pro 2.11.1 on the local sites. All 42 registered module schemas are discoverable; 15 core module types support authoring. The optional Themer plugin was not installed for this validation, so Themer runtime behavior remains unverified.

- Pro suite: 2,865 tests, 11,867 assertions, six existing skips, no failures.
- Public suite: 232 tests, 602 assertions, no failures.
- Live MCP smoke: discovery, 63-node layout staging, CSS, node add/update/move/remove, stale-hash and invalid-parent rejection, recovery, publication and draft isolation passed.
- Native saved-template creation and detached application to a draft fixture passed.
- Live permission smoke: native builder access, unrestricted editing, disabled modules, WordPress capabilities, page access, inactive integration and anonymous requests were rejected without changing the page.
- Native editor: opened and saved a heading, then used Done/Publish; MCP readback preserved the published graph, images, CSS and apostrophes.
- Desktop and mobile visual checks passed. Mobile viewport inspection found no overflowing content elements; all three images loaded. The full-page browser capture clipped the right edge, while the normal viewport capture and DOM bounds confirmed correct layout.
- Test-site write tools restored to disabled defaults after verification.

Landing page: [Tide & Timber](https://elementor-mcp.test/tide-timber-beaver-builder-integration-test/) (2202). Reusable local template: 2205. Draft template-application fixture: 2207. Document writes used MCP.

## Native API lessons

- Beaver access settings can cache before WP-CLI authenticates the MCP user. Re-register the existing builder-access definition to refresh request-local role defaults, preserving saved restrictions.
- Native module updates need current module versions and photo source URLs. Resolve missing photo sources from the attachment; preserve existing explicit sources.
- Let native save generate assets once. A second asset render in the same request can omit module and page CSS because native de-duplication state is already populated.
- Native save can retain slashed layout data in its request cache. Publication must read the persisted unslashed graph and render fallback content from the staged draft.
- Readback comparison normalizes associative key ordering while preserving list order and actual values.

Reproducible live checks: `scripts/e2e/beaver-mcp.py`, `scripts/e2e/beaver-permissions.php`, and `scripts/e2e/beaver-test-tools.php`. Test state is ignored by Git. Implementation remains local and uncommitted.
