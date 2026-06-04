# Planner — Design Spec

## Feature Summary

A route planning workspace for comparing GPX routes across groups (e.g. "Sarah's Routes" vs "Zoe's Routes"). Crew uploads GPX files into named groups, toggles routes on/off, expands waypoint details, and compares everything on a shared map. Plans persist in the database, support multiple plans per user, and generate shareable read-only links with toggle controls. Standalone tool with no Journey integration.

## Primary User Action

Upload GPX files into groups and visually compare routes on the map to decide which passage plan to use.

## Design Direction

- **Color strategy**: Full Palette (project default). Semantic colors for UI chrome. Group hue families for route data — auto-assigned from a pool of 6 distinct OKLCH hues (blues, corals, greens, ambers, teals, pinks), each with 3-4 lightness steps for individual routes within.
- **Theme scene**: Crew member at a nav table or ashore on a laptop, planning a crossing in daylight or well-lit cabin. Focused, comparing options methodically. Light theme default, dark/night watch inherited from the admin shell.
- **Anchors**: Navionics route planner (nautical context, route layering), Figma's layer panel (toggle/expand/group hierarchy), Strava route builder (upload-and-compare flow).

## Scope

- **Fidelity**: Production-ready
- **Breadth**: Two pages — plan index + plan detail (plus shared read-only variant)
- **Interactivity**: Full CRUD — upload, group, toggle, remove, expand, share
- **Time intent**: Ship it

## Data Model

### Hierarchy

Plan → Groups → Routes (individual GPX files)

### Tables

**plans**
- `id`, `slug` (unique, for shareable URLs), `title`, `user_id`, `share_token` (nullable, for public access), `timestamps`

**plan_groups**
- `id`, `plan_id` (FK), `name`, `color_index` (integer, maps to hue family pool), `sort_order`, `timestamps`

**plan_routes**
- `id`, `plan_group_id` (FK), `name` (from GPX filename or track name), `gpx_path` (stored file), `distance_nm` (calculated), `is_enabled` (boolean, default true), `color_index` (integer, shade within group hue family), `track_points` (JSON, array of [lat, lng] for polyline rendering), `waypoints` (JSON, array of {name, lat, lng}), `sort_order`, `timestamps`

### Color Hue Families

Six group hue families, auto-assigned by `color_index`:

| Index | Family | Base Hue (OKLCH) |
|-------|--------|-------------------|
| 0 | Blues | hue 245 |
| 1 | Corals | hue 25 |
| 2 | Greens | hue 150 |
| 3 | Ambers | hue 70 |
| 4 | Teals | hue 178 |
| 5 | Pinks | hue 330 |

Each family has 4 lightness steps for individual routes within a group (L: 0.55, 0.45, 0.35, 0.25 at chroma ~0.14-0.18). Routes cycle through steps by their `color_index`.

## Layout Strategy

### Plan Index Page

Standard admin page. Page title "Planner", `+ New Plan` button. Grid of plan cards (name, group count, route count, created date, share status). Empty state when no plans exist.

### Plan Detail Page

- **Page header**: plan name (editable inline), meta bar (group/route counts, created date), Share + New Group actions
- **Map panel**: ~45% viewport height, `border-radius: 16px`, Sea Chart / Satellite toggle top-right. All enabled routes rendered with group hue families. Start/end pins with labels. Named waypoints as smaller markers. Default position: last known GPS position of the ship, falling back to Lagos Marina, Portugal (37.1028, -8.6740).
- **Groups grid**: `grid-template-columns: repeat(2, 1fr)` — two groups side by side. If >2 groups, additional rows wrap below. Each group is a `.panel` card containing:
  - Group header: color swatch, editable name, route count badge, edit/delete actions
  - Route rows: color dot, expandable name (chevron), distance + waypoint stats, toggle, remove button
  - Expanded waypoints: vertical timeline with start/end badges, waypoint names + coords
  - Per-group dropzone: dashed border area at bottom of the group card — "Drop GPX files or click to browse"

### Shared View

Same layout as plan detail. Read-only badge near plan name. Route toggles work (client-side only, not persisted). No upload dropzones, no remove buttons, no inline editing, no New Group button, no Share button.

## Map Tile Layers

### Sea Chart (default)

Existing OpenSeaMap tiles via `MapTileController`, theme-aware (light/dark/night).

### Satellite

Esri World Imagery tiles. Proxied through a new `MapTileController` method with disk caching (same pattern as OpenSeaMap). No API key required. Tile URL: `https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}`

## Key States

| State | What the user sees |
|---|---|
| **Empty plan index** | Centered prompt: "No plans yet" + `+ Create your first plan` button |
| **Empty plan (no groups)** | Map centered on last known ship position (fallback: Lagos Marina 37.10N 8.67W), single prompt below: "Add a group to get started" + `+ New Group` button |
| **Empty group (no routes)** | Group card with header + prominent dropzone: "Drop GPX files here to add routes" |
| **Uploading** | Dropzone shows progress — filename + spinner per file |
| **GPX parse error** | Inline error below the dropzone within the group card. Scarlet/error text, dismissible. "Could not parse filename.gpx — invalid GPX format" |
| **Route disabled** | Route row dimmed (text-dim color), route line on map uses dashed stroke at reduced opacity |
| **Route expanded** | Waypoint timeline opens below the route row. Map highlights that specific route |
| **Shared view** | Read-only badge near plan name. Toggle controls present, upload/edit/remove absent |

## Interaction Model

- **Upload**: Drag GPX files onto a group's dropzone, or click to browse. Multiple files accepted. Each parsed file becomes a route in that group. Auto-assigned a color from the group's hue family.
- **Toggle**: Click the toggle switch on a route row. Route line appears/disappears on map. Immediate, no confirmation.
- **Remove**: Click the x button. Styled HTML confirmation modal (never `confirm()`). Route removed from group and map.
- **Expand**: Click the route name or chevron. Waypoint timeline opens below. Clicking a waypoint pans the map to it.
- **Group create**: Click `+ New Group`. New empty group card appears in the grid with the name field focused and editable. Color auto-assigned from the next available hue family. Press Enter or blur to confirm.
- **Group delete**: Edit action on group header. Styled confirmation modal. Removes group and all its routes.
- **Share**: Click Share button. Generates a shareable URL (plan slug + share token). Copy-to-clipboard with toast feedback.
- **Map tile switch**: Toggle between Sea Chart (OpenSeaMap) and Satellite (Esri World Imagery). Immediate tile swap.
- **Plan name edit**: Click plan name in page header to edit inline. Enter or blur to save.

## Pages and Routes

| Route | Controller Method | Page Component | Auth |
|---|---|---|---|
| `GET /admin/planner` | `PlannerController@index` | `Admin/Planner.vue` | auth |
| `POST /admin/planner` | `PlannerController@store` | — (redirect) | auth |
| `GET /admin/planner/{plan}` | `PlannerController@show` | `Admin/PlannerShow.vue` | auth |
| `PUT /admin/planner/{plan}` | `PlannerController@update` | — (Inertia) | auth |
| `DELETE /admin/planner/{plan}` | `PlannerController@destroy` | — (redirect) | auth |
| `POST /admin/planner/{plan}/groups` | `PlanGroupController@store` | — (Inertia) | auth |
| `PUT /admin/planner/groups/{group}` | `PlanGroupController@update` | — (Inertia) | auth |
| `DELETE /admin/planner/groups/{group}` | `PlanGroupController@destroy` | — (Inertia) | auth |
| `POST /admin/planner/groups/{group}/routes` | `PlanRouteController@store` | — (Inertia) | auth |
| `PUT /admin/planner/routes/{route}` | `PlanRouteController@update` | — (Inertia) | auth |
| `DELETE /admin/planner/routes/{route}` | `PlanRouteController@destroy` | — (Inertia) | auth |
| `GET /planner/{plan:slug}` | `PublicPlannerController@show` | `Public/Planner.vue` | share_token |

## Navigation

Add "Planner" to the admin sidebar in `AdminLayout.vue`, positioned after "Ship's Log" and before "Tracks". Icon: `map-pin` or similar route/waypoint icon. Add breadcrumb entries for `Admin/Planner` and `Admin/PlannerShow`.
