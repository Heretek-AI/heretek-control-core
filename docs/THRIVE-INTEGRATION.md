# Thrive Architect integration

Target: installed Thrive Architect 11.0.0.1. Gutenberg remains available beside
one selected standalone builder. Thrive Theme Builder is a separate product.

## Plan

1. Inspect native element registry, HTML templates, meta storage, REST save
   pipeline, permissions, revisions and landing-page suffix handling.
2. Add standalone discovery, page creation/read/save, targeted native HTML
   element operations, local library discovery and page design settings.
3. Preserve untouched CSS, globals, feature flags, template selection and header/
   footer references; use hashes, edit locks and recovery snapshots before saves.
4. Wire selector, dedicated tool tab, defaults, AI Chat and skill downloads.
5. Run contract/permission/round-trip tests. Copy plugin to the test site and
   build a native landing page through MCP when Thrive is available there.

## Sources

- Installed `thrive-visual-editor/inc/classes/rest/class-tcb-content-rest.php`
  and `class-tcb-editor-ajax.php`: canonical save contract.
- Installed element registry and `inc/views/elements`: native markup/controls.
- https://thrivethemes.com/docs/accessing-and-updating-thrive-architect-content-through-the-wordpress-rest-api/

Public skill search found general WordPress/migration skills, but no dedicated
Architect authoring skill selected for reuse. Author an EMCP-specific skill
from the native source and official documentation.

## Implemented and validated

- Twelve tools in Discovery, Pages, Elements, and Library; standalone selector,
  dedicated tab/toggles, AI Chat group, and six write tools disabled by default.
- Discover 186 installed native definitions. Authoring supports basic native
  sections, columns, text, images, content boxes, buttons and dividers. Discovery
  does not imply write support for every installed element.
- Canonical native save, per-post permissions, current-state hashes, edit locks,
  bounded recovery snapshots, omitted CSS/settings preservation and draft status.
- Library reads cover local content templates and symbols with pagination.
  Cloud landing templates, shared-symbol edits, forms/scripts, Thrive Theme
  Builder and Thrive Optimize are outside this first Architect integration.
- Skill available through runtime injection, folder ZIP and Claude Desktop ZIP;
  independently authored with credit/link to Thrive's official documentation.
- Test page 1979, SOLSTICE, created and built only through MCP on
  https://elementor-mcp.test/?page_id=1979&preview=true (draft). Seven sections,
  native text/columns/images/buttons and responsive page CSS. Theme header kept.
- Live add/replace/move/remove round trip preserved CSS and settings; stale hash
  rejected. Native editor recognizes Background Section > Columns > Column >
  Text/Image, with native formatting and image controls. Desktop/tablet/phone
  previews checked; frontend images and anchor targets verified.
- Full Pro tests: 2,741 tests, 10,537 assertions, six skips; public tests: 232
  tests, 602 assertions. Download tests verify runtime/folder/Desktop parity.
- Regressions found during live testing: route ID must use `set_url_params`
  before the body payload; empty drafts require native migration; editor URLs
  must use `tcb_get_editor_url`, and the REST controller loads lazily.
- Installed-runtime permission probes pass for anonymous denial, missing page
  capability, per-post denial and disabled integration; Gutenberg stays enabled.
  Final PHP lint and focused skill-download/parser checks pass after sync.
