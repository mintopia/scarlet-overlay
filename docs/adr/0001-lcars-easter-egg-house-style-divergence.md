# 1. LCARS easter egg deliberately diverges from Scarlet's house style

Date: 2026-06-14

## Status

Accepted

## Context

Scarlet's `PRODUCT.md` sets a restrained maritime register and explicitly
anti-references "gaming-style stream overlays with neon colors and aggressive
animations" and "nautical themes that lean into cliché". The admin design system
(`DESIGN.md`) is a cool, light, low-chroma instrument aesthetic.

The LCARS Panel (see [[lcars-panel]] in CONTEXT.md) is, by definition, the
opposite: black background, saturated TNG-era oranges/mauves/blues, the Antonio
display face, animated "living instrument" motion, and Star-Trek bridge-station
flavour. Taken as a normal product surface it would fail Scarlet's own brand
test and the impeccable "AI slop / category-reflex" checks.

## Decision

Treat the LCARS Panel as a **sanctioned exception** to the house style, justified
entirely by it being a *hidden, opt-in easter egg*:

- It is unreachable except via a deliberate Konami-code unlock on either the public
  or the admin dashboard. No casual user or stream viewer stumbles onto it.
- It is public (route `/lcars`, no auth) but exposes only data the public voyage
  dashboard already broadcasts, so making it public leaks nothing new (see PLAN.md
  §2 4b). It was originally admin-gated; opened up by request once it was confirmed
  to carry no non-public data.
- Its styling is fully self-contained (scoped CSS + its own font load) and must
  not leak into, restyle, or share tokens with any production surface.
- It reuses Scarlet's existing data layer (the `metrics` reverb channel and the
  metric registry) read-only; it adds no new telemetry and changes no product
  behaviour.

## Consequences

- The panel is exempt from `PRODUCT.md` anti-references and the impeccable
  category-reflex checks, because its whole point is the pastiche. Reviewers
  should not "fix" it toward the house style.
- The divergence is cheaply reversible: the egg is isolated, so removing or
  changing it touches nothing else.
- Strict isolation is a hard requirement: any LCARS styling that bleeds into the
  admin theme is a defect, not a feature.
- Star Trek / LCARS is Paramount intellectual property. This is acceptable only
  as a private, non-commercial easter egg on a personal project; it must not be
  promoted as a product feature, and only freely-licensed assets (e.g. the
  Antonio webfont) may be used.
