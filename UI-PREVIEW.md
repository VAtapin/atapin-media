# Clickable UI prototypes

This is a screenshot navigation prototype, not the Laravel product. All artwork comes from the existing `UI/` directory. Images are not edited, cropped, renamed or regenerated in the repository.

## Open

Open `index.html` with `UI/` alongside it. No npm install, build, network API or credentials are needed. Alternatively run `python3 -m http.server 8080` in the repository and open `http://localhost:8080`.

The index groups coherent public and admin series separately from single designs and UI kits. The two-pane comparison can synchronize equivalent sections. There is no silent cross-series fallback for missing screens.

## Navigation

- Click the navigation text drawn on the screenshot, or a linked card.
- `D`: show/hide transparent hotspots. Blue means a linked screen; gold means a prototype-only action/missing screen.
- `G`: screen thumbnails. Left/right arrows: previous/next screen.
- `H`: hide/restore the prototype toolbar. `Z`: natural image size. `F`: fullscreen where permitted.
- Browser back/forward and direct `ui-preview.html?series=frontend-gold-b#book` links work.

The screenshot scales as a single image. Hotspots use percentages of that image and scale with it. This is **not** the responsive HTML implementation of the illustrated product.

## Scope and honest limits

Mapped series: Gold Editorial A (8), Gold Editorial B (10), Blue Library (7), Manna Workspace (6), Classic Media Desktop (3): 34 screens.

The additional 19:55 sequence (9 images) is available for browsing but has **no visually verified menu mapping yet**. It is explicitly marked as a reference sequence, not falsely presented as an implemented navigation flow. Other single designs and UI-kit posters are also browsable references.

Missing destination screens show a non-blocking notice. Authentication, search inside content, video playback, streaming, payments, AI, uploads and real form submissions are not implemented. Do not treat a screenshot containing a button as a working integration.

## Files

- `ui-preview-data.js`: series, original file paths and per-screen rectangles.
- `ui-preview.html` / `ui-preview.js`: shared renderer, accessible transparent links, image loading/errors, navigation and keyboard controls.
- `index.html` / `ui-preview-index.js`: gallery and synchronized comparison.
- `preview-*.html`: compatibility entry points for earlier links.

Each rectangle is `{x, y, w, h, target, label}` in percentages from the top-left of the full original image. Inspect individual screens before reusing a common header: some change logo size, menu positions or add an announcement bar. Actions without a screen use an explicit `message` instead of pretending to navigate.

## Verification

Run `node --test tests/ui-preview.test.cjs`. Tests check data shape, bounds, file paths, counts and entry points. Verify image alignment visually in a browser with `D`; real screenshot text is not DOM text. Browser checks should include public menu links, detail-page breadcrumbs, switching series, back/forward, comparison synchronization, narrow viewport, original-size mode and a missing-image error.

No external fonts, tracking, API calls or third-party script dependencies are introduced.

### Checks performed for this change

- JavaScript syntax checks passed for all three scripts.
- Seven Node tests passed (manifest shape, bounds, path safety, mapped navigation, core files, legacy entry points, non-overlapping duplicate import-menu rectangles).
- In-memory Chromium smoke checks passed using the supplied local screenshot originals: rendering, Videos/Books menu clicks, prototype-only notice, hotspot toggle, toolbar hide/restore, and overlay geometry at 1440/960/390 px. No page JavaScript errors in this smoke run.
- Normal localhost and file-URL end-to-end navigation was blocked by the execution environment (`ERR_BLOCKED_BY_ADMINISTRATOR`). Full browser history, two-iframe synchronization and deployment still require checking in an ordinary local browser. These are implemented, not claimed to have passed that unavailable end-to-end test.
