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
