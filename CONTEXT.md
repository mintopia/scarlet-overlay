# Scarlet — Context Glossary

A glossary of the ubiquitous language for the Scarlet boat-telemetry application.
Definitions only — no implementation details.

## Terms

### LCARS Panel
The Star-Trek-themed easter egg: an admin-only screen that re-presents Scarlet's
existing live boat telemetry in the visual style of an LCARS console
(the "Library Computer Access/Retrieval System" interface seen on TNG/DS9/VOY-era
Star Trek). It is a **read-only alternate presentation** of data Scarlet already
collects — not a new data source or a new system. It is **public** (route `/lcars`,
no auth), exposing only the telemetry the public voyage dashboard already broadcasts;
it stays hidden because it is reachable only via the Konami code on either the public
or the admin dashboard.

### Metric Catalog
The existing set of ~36 named telemetry metrics Scarlet already exposes, grouped as:
**navigation, wind, power, tanks, environment, cabin, tracker** — plus GPS position,
open-meteo weather, and computed sun times. The LCARS Panel draws exclusively from
this catalog; it introduces no new metrics.

### Metric Group
A named cluster of related metrics (e.g. `navigation`, `power`) as defined in the existing
metric config. The relationship to Console Sections is **many-to-many**: one Bridge Station
may draw from several Metric Groups (e.g. OPS = tanks + tracker + cabin), and a single Metric
Group's metrics may surface in more than one Station. Metric Groups are a data-layer concept;
Bridge Stations are a presentation concept layered over them.

### Console Section
One selectable view within the LCARS Panel. The Panel is an **interactive sectioned
console** (prototype "A"): a left "elbow" rail of buttons selects which Console Section
is foregrounded in the main area. Only one Section is shown at a time. Each Section is
**graph-led** — its metrics are presented primarily as visualisations (gauges, bar
graphs, line history), not as a table of text readouts. A Section is either the **MSD**
landing view or a **Bridge Station**; a Bridge Station is a curated view over one *or
more* Metric Groups (the mapping is deliberately not one-to-one — e.g. OPS draws from the
tanks, tracker, and cabin groups). Each displayed metric carries a **presentation
contract**: its source key, unit, valid range/wraparound, formatting, freshness, and
null/categorical behaviour.

### Elbow Rail
The left vertical column of the LCARS frame: a stack of coloured buttons (one per Console
Section) joined to the top bar by the signature LCARS "elbow" curve. Selecting a button
switches the foregrounded Console Section.

### Bridge Station
The flavour given to each Console Section: rather than raw metric-group names, Sections are
named after Star Trek bridge stations, but **only where backed by real Scarlet telemetry**
(no faked/theatre panels). The locked roster is:
- **CONN** — steering & sail trim: SOG, STW, heading, COG, heel, pitch, VMG, apparent wind angle.
- **NAVIGATION** — position & route: GPS pos, satellites, HDOP, depth, waypoint distance/TTG, trip log.
- **ENGINEERING** — power & systems: house battery bank (SOC/V/A/power/time-remaining), engine
  battery, tracker health (CPU/heap/uptime). The "power core".
- **OPS** — consumables & comms: fuel/water tanks, LTE/WiFi connectivity, and cabin
  temp/humidity (crew quarters / life support).
- **SCIENCE** — conditions & almanac: weather (air temp, pressure, wind, waves, current), sea
  temperature, computed sun/twilight times.

Dropped stations (Tactical, Medical/standalone) had no honest data source and were cut.

### MSD (Master Systems Display)
The Panel's landing/home view: a Star-Trek-MSD-style overview showing the boat **name** and a
stylised vessel schematic with live sensor call-outs, before any Bridge Station is selected.

## Data Layer Terms

### Canonical Metric
A logical, definitively-named telemetry quantity (e.g. "fresh water level", "house battery SOC"),
independent of *which* underlying sensor or time-series currently provides it. It is the single
stable identity that overlays, dashboards, the ship log, and explore bind to. Distinct from a raw
series name like `scarlet_mqtt_percent{topic="watertank"}`, which is merely one possible source.

### Definitive Source
For a Canonical Metric, the authoritative underlying data. A Definitive Source is **not a single
query** — it is expressed as a Source Priority Chain, because the trustworthy source can change over
time (firmware/config drift, sensor outages).

### Source Priority Chain
The ordered list of underlying series for a Canonical Metric, highest-trust first; lower entries are
used only for timestamps where higher entries have no data. Example — fuel level: SignalK
`tanks_fuel_0_currentLevel` (intended/preferred) → MQTT `tanklevel` (historical fallback that
actually carried the data). Fresh water: SignalK `freshWater_0` → MQTT `watertank`.

### Canonical Catalog
The complete, **DB-backed, editable** set of Canonical Metric definitions — identity, unit, Source
Priority Chain, transforms, volatility/trend, Staleness Threshold, and display label. Seeded from a
code baseline and validated on save. It is the source of truth for the **data layer**, replacing the
hand-edited `config/scarlet.php` registry whose silent drift from reality is the problem being solved.

### Value Age
Seconds elapsed since a Canonical Metric's most recent real sample. Reported alongside every current
value so consumers can show recency and never silently present stale data as live.

### Staleness Threshold
The per-metric maximum Value Age beyond which a current value is flagged stale. Source-aware: an
hourly saver-mode tracker metric tolerates a far larger age than a 15-second SignalK reading.

### Volatile Metric
A Canonical Metric whose raw signal is dominated by short-term noise rather than real change — e.g.
fuel and fresh-water tanks, where sloshing causes sample-to-sample swings far larger than the genuine
daily drift. Its headline reading is a Trend Value, not the last raw sample.

### Trend Value
The smoothed "current reading" of a Volatile Metric: a `median_over_time` over a per-metric window
(default 10 minutes; median chosen because it collapses sloshing jitter to ~zero on real at-sea data).
The raw series remains available for charts.

### Fix-Forward + Additive Backfill
The normalisation method for the Canonical Catalog: (1) correct ingestion so **new** data lands in
canonical form, (2) **additively** rebuild clean canonical history alongside the messy originals
(non-destructive), (3) verify the canonical series, then (4) prune the legacy series. Backups precede
any pruning. Contrasted with destructive in-place relabel, which is explicitly rejected.

### Data Catalog vs Presentation Config
A boundary. The **Data Catalog** (this program) defines what a metric *is* and where it comes from
(identity, source, unit, trend, freshness). **Presentation Config** (colours, chart types, explore
grouping, dashboard layout) defines how it *looks* and is owned by the deferred Audience Dashboard
designer — it stays in code for now.

### Audience Dashboard
One of the role-scoped admin dashboards — **Main**, **Skipper/Captain**, **Ops**, **Tech** — each a
curated view over the Canonical Catalog for a particular reader. Their in-browser visual designer is a
**deferred follow-on program**, not part of the data-layer work.

### EcoFlow Curated Set
The bounded subset of the hundreds of EcoFlow Delta MQTT fields promoted to Canonical Metrics: input
and output power across the unit's input/output channels, plus general status (SOC, voltage, current,
temperature, time-remaining, cycles/SOH). Per-cell BMS internals remain raw and un-canonicalised.
