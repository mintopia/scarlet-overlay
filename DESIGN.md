# Scarlet Admin Design System

Visual language for the admin interface only. The stream overlay and public dashboard have their own distinct designs. All values are the source of truth for admin pages; implementation lives in `resources/css/app.css` via Tailwind 4's `@theme` block.

## Color Strategy

**Full Palette.** Five named roles, each used deliberately. Color carries semantic meaning, not decoration.

### Neutrals (hue 205, cool maritime tint)

All neutrals share hue 205. Low chroma (0.004-0.014) keeps them from reading as "blue" while ensuring pure greys never appear.

| Token | OKLCH | Role |
|---|---|---|
| `--color-bg` | `oklch(0.96 0.01 205)` | Page background |
| `--color-surface` | `oklch(0.99 0.004 205)` | Panel/card surface |
| `--color-border` | `oklch(0.89 0.012 205)` | Panel borders |
| `--color-border-light` | `oklch(0.93 0.006 205)` | Dividers within panels |

### Text

| Token | OKLCH | Role |
|---|---|---|
| `--color-text-primary` | `oklch(0.15 0.025 205)` | Headings, primary values |
| `--color-text-secondary` | `oklch(0.38 0.02 205)` | Body text, descriptions |
| `--color-text-dim` | `oklch(0.52 0.014 205)` | Labels, units, metadata |

### Semantic Colors

Each semantic color has a base, a bg (8-15% opacity wash for backgrounds), and a border variant.

| Role | Base | Usage |
|---|---|---|
| **Teal** | `oklch(0.42 0.14 178)` | Navigation: heading, COG, speed, position, teal-accented panel titles |
| **Amber** | `oklch(0.48 0.17 70)` | Wind: true wind speed, TWA, Beaufort, point of sail |
| **Blue** | `oklch(0.42 0.14 245)` | Depth, pressure, water-related data |
| **Green** | `oklch(0.45 0.16 150)` | Positive state: charging, healthy, online, battery OK |
| **Scarlet** | `oklch(0.48 0.22 25)` | Brand accent: compass north, boat name, discharge/negative power, errors |

### Dark Mode Overrides

Dark mode (`data-theme="dark"`) adjusts all tokens. Key principles:

- **Neutrals lose chroma** as lightness drops (0.010-0.012), preventing a cold blue tint
- **Semantic colors maintain full chroma** for readability against dark surfaces
- **Bg washes use 12% alpha** (vs 8% in light mode) for visibility on dark backgrounds

| Token | Light | Dark | Notes |
|---|---|---|---|
| `--color-bg` | `oklch(0.96 0.01 205)` | `oklch(0.15 0.010 205)` | Lower chroma in dark |
| `--color-surface` | `oklch(0.99 0.004 205)` | `oklch(0.21 0.012 205)` | 0.06 gap from bg for layer separation |
| `--color-border` | `oklch(0.89 0.012 205)` | `oklch(0.28 0.010 205)` | |
| `--color-text-primary` | `oklch(0.15 0.025 205)` | `oklch(0.93 0.005 205)` | Minimal chroma avoids blue-tinted text |
| `--color-teal` | `oklch(0.42 0.14 178)` | `oklch(0.66 0.14 178)` | Full chroma preserved |
| `--color-amber` | `oklch(0.48 0.17 70)` | `oklch(0.76 0.17 70)` | Full chroma preserved |
| `--color-blue` | `oklch(0.42 0.14 245)` | `oklch(0.64 0.14 245)` | Full chroma preserved |

### Shadow

| Theme | Value |
|---|---|
| Light | `0 1px 5px oklch(0.2 0.02 205 / 0.08)` |
| Dark | `0 2px 8px oklch(0.0 0.0 0 / 0.4)` |
| Night | `0 2px 8px oklch(0.0 0.0 0 / 0.5)` |

No elevation hierarchy needed; panels sit flat on the page.

## Typography

### Fonts

| Family | Weight Range | Role |
|---|---|---|
| **Nunito Sans** | 300-800 | Display numbers, page titles, data values. All numeric/quantitative content. |
| **DM Sans** | 400-800 | Body text, labels, section headers, UI chrome. All qualitative/textual content. |

### Type Scale (fixed rem, product register)

| Tier | Size | Weight | Family | Usage |
|---|---|---|---|---|
| **Hero** | 72px | 700 | Nunito Sans | Primary instrument readings |
| **Display** | 44px | 700 | Nunito Sans | Weather temperature, large compass center numbers |
| **Instrument** | 26-28px | 600-800 | Nunito Sans | Compass headings, wind dial center value, chart headline values |
| **Secondary Value** | 16-18px | 600 | Nunito Sans | Data row values (nav, voltage, weather rows), chart current values |
| **Page Title** | 24px | 800 | Nunito Sans | Page heading (h1) |
| **Panel Title** | 11px | 800, 2.5px tracking | DM Sans | Panel section headers, uppercase |
| **Label** | 12px | 500 | DM Sans | Data row labels, form labels |
| **Sub-label** | 9-10px | 700-800, 1.5-2px tracking | DM Sans | Instrument sub-labels (HDG, COG, TWA), uppercase |
| **Meta** | 11px | 500 | DM Sans or Nunito Sans | Units (kts, °M), sub-notes, tertiary info |
| **Link** | 11px | 700 | DM Sans | Explore links, action text |

### Numeric Convention

- Nunito Sans for ALL numbers, never DM Sans or monospace
- Negative letter-spacing on large numbers: -2.5px at 72px, -1px at 28px, -0.2px at 16px
- Units sit beside the value in dim text (text-3), not inside the number

## Layout

### Page Structure

- Max width: 1200px, centered
- Page padding: 24px 32px
- Panel gap: 16px (consistent between all panels in a grid)
- Panels use 16px border-radius

### Panel Anatomy

```
┌─ Panel ──────────────────────────────┐
│ PANEL TITLE (11px/800/2.5px track)   │  ← 18px top, 24px sides
│                                      │
│ [content]                            │  ← 10px top, 24px sides, 22px bottom
│                                      │
│ Explore →                            │  ← margin-top: auto (pins to bottom)
└──────────────────────────────────────┘
```

- Panel titles: left-aligned for data panels, center-aligned for instrument/dial panels
- Panel titles can be colored to match their semantic role (teal for heading, amber for wind)
- Panels use `display: flex; flex-direction: column` so explore links pin to the bottom
- No tinted panel backgrounds. Color comes from the data, not the container.

### Grid Patterns

- **Primary row**: 3 equal columns (`1fr 1fr 1fr`)
- **Secondary row**: 3 equal columns
- **2-column split**: `3fr 2fr` for asymmetric content (tanks vs environment)
- Always use gap, never margins between grid items

### Value Hierarchy

Every data display on every page follows this three-tier system:

| Tier | Font | Size | Weight | Color | Example |
|---|---|---|---|---|---|
| **Primary** | Nunito Sans | 26-72px | 600-700 | Semantic color | SOG `6.2`, HDG `274°` |
| **Secondary** | Nunito Sans | 16px | 600 | `--text` or semantic | Nav rows, voltages, weather values |
| **Tertiary** | DM Sans | 12px | 500 | `--text-3` | Labels: "Next Waypoint", "House Battery" |

Units and metadata are 11px, weight 400-500, color `--text-3`.

## Components

### Level Bar

Horizontal bar showing tank/resource levels. 8-10px height, 4-5px radius.

- Track: `oklch(0.93 0.01 205)`
- Fill: gradient using semantic color (green for battery, amber for fuel, blue for water)
- Optional glow: `box-shadow: 0 0 6px color / 0.12-0.15`
- Label (56px width) | Track (flex) | Value (32-34px width, right-aligned)

### Sparkline

SVG chart for trend data. 22-44px height depending on context.

- Stroke: semantic color, 1.5-1.8px width, round linecap, 0.5-0.6 opacity
- Fill: same color at 0.04-0.08 opacity below the line
- Current value dot: 3px radius, semantic color fill, white 1.5px stroke
- Power sparkline: scarlet for discharge, green for charge, dashed zero line

### Compass Rose (Heading)

SVG instrument showing heading arrow + COG dashed line.

- Face: subtle teal tint `oklch(0.99 0.01 178)`, border matches panel border
- Three ring levels: solid outer, solid inner, dashed inner-most
- Three tick levels: major (cardinal, 1.2px), minor (intercardinal, 0.5px), fine (10°, 0.3px)
- N in scarlet, E/S/W in dim text
- HDG: solid arrow with polygon head, teal
- COG: dashed line, teal at 0.3 opacity

### Wind Dial

SVG instrument showing TWA arrow with TWS in center.

- Point of sail zones rendered as colored wedges (0.07-0.14 opacity): no-go (scarlet), close hauled/reach (teal), beam/broad reach (amber), running (green-amber)
- TWA arrow: animated dash, amber, with feather marks
- Center circle with TWS number (26-28px, weight 800, amber) and "kts" label
- Bow marker and boat silhouette in teal

### Weather Hero

Full-width gradient strip at top of weather panel, flush with panel border-radius.

- Gradient: `linear-gradient(140deg, oklch(0.93 0.045 210), oklch(0.95 0.035 195), oklch(0.94 0.04 75 / 0.4))`
- Sun glow: `::after` pseudo with blurred circle
- Contains: weather icon SVG, temperature (44px/700), condition text, sea temp

### Status Badge

Pill showing boat state (Under Sail, Under Power, In Port).

- Background: green wash `oklch(0.92 0.05 150)`
- Text: dark green `oklch(0.28 0.1 150)`, 12px, weight 800, uppercase, 1.2px tracking
- Dot indicator: 7px, semantic green

### Data Row

Key-value pair for supporting information.

- Flex row, space-between, baseline-aligned
- Label: tertiary tier (12px/500/text-3)
- Value: secondary tier (16px/600/Nunito Sans)
- Padding: 3-4px vertical

### Explore Link

- Plain text, no container: 11px, weight 700, teal color
- `margin-top: auto` within flex panel to pin to bottom
- Hover: underline
- Text: "Explore →"

## Anti-Patterns (Do Not Use)

- Monospace fonts for numbers (use Nunito Sans)
- Tinted panel backgrounds (color comes from data, not containers)
- Pill badges for contextual labels (use plain colored text)
- Background gradients on the page body
- Side-stripe borders on panels
- Hero-metric template (big number + small label + supporting stats in a card)
- Identical card grids
