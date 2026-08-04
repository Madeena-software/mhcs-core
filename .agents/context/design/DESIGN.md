---
name: Corporate Precision System
colors:
  surface: '#fcf9f8'
  surface-dim: '#dcd9d9'
  surface-bright: '#fcf9f8'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f6f3f2'
  surface-container: '#f0edec'
  surface-container-high: '#ebe7e7'
  surface-container-highest: '#e5e2e1'
  on-surface: '#1c1b1b'
  on-surface-variant: '#404751'
  inverse-surface: '#313030'
  inverse-on-surface: '#f3f0ef'
  outline: '#717882'
  outline-variant: '#c0c7d3'
  surface-tint: '#0061a3'
  primary: '#005c9b'
  on-primary: '#ffffff'
  primary-container: '#1375c0'
  on-primary-container: '#f5f7ff'
  inverse-primary: '#9ecaff'
  secondary: '#006879'
  on-secondary: '#ffffff'
  secondary-container: '#1adcfd'
  on-secondary-container: '#005d6c'
  tertiary: '#894900'
  on-tertiary: '#ffffff'
  tertiary-container: '#ad5d00'
  on-tertiary-container: '#fff6f2'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d1e4ff'
  primary-fixed-dim: '#9ecaff'
  on-primary-fixed: '#001d36'
  on-primary-fixed-variant: '#00497d'
  secondary-fixed: '#a9edff'
  secondary-fixed-dim: '#0ed9fa'
  on-secondary-fixed: '#001f26'
  on-secondary-fixed-variant: '#004e5b'
  tertiary-fixed: '#ffdcc3'
  tertiary-fixed-dim: '#ffb77d'
  on-tertiary-fixed: '#2f1500'
  on-tertiary-fixed-variant: '#6e3900'
  background: '#fcf9f8'
  on-background: '#1c1b1b'
  surface-variant: '#e5e2e1'
typography:
  display-lg:
    fontFamily: IBM Plex Sans
    fontSize: 56px
    fontWeight: '600'
    lineHeight: 64px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: IBM Plex Sans
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: IBM Plex Sans
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
  headline-md:
    fontFamily: IBM Plex Sans
    fontSize: 24px
    fontWeight: '500'
    lineHeight: 32px
  body-lg:
    fontFamily: IBM Plex Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: IBM Plex Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: IBM Plex Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-lg:
    fontFamily: IBM Plex Sans
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: IBM Plex Sans
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
  container-max: 1280px
---

## Brand & Style

This design system embodies a **Corporate / Modern** aesthetic, prioritizing reliability, clarity, and systematic precision. It is engineered for enterprise environments, fintech, and high-utility SaaS platforms where trust and information density are paramount. 

The visual language is structured and professional, utilizing a rigorous grid and a balanced distribution of color. The primary emotional response is one of stability and technological competence, achieved through the strategic use of technical blue tones and high-contrast accents. White space is used intentionally to organize complex data, ensuring the interface remains breathable despite its high-information nature.

## Colors

The palette is centered around a "Trust Blue" (#1375C0) and an energetic "Vibrant Cyan" (#00D7F8) secondary accent. 

- **Primary:** Reserved for main actions, active states, and structural branding elements.
- **Secondary:** Used sparingly for emphasis, progress indicators, and interactive highlights to draw the eye without overwhelming the professional tone.
- **Neutral:** A deep black (#101010) is used for high-contrast typography, while varying shades of cool grays define surface tiers and borders.
- **Surface:** The system uses a clean, white-dominant base to ensure maximum legibility and a contemporary, "light" feel.

## Typography

This design system exclusively uses **IBM Plex Sans** to convey a systematic, engineering-led personality. 

- **Headlines:** Use SemiBold weights with tighter tracking to create a strong visual anchor.
- **Body:** Standard weights are used for readability, with a generous line-height of 1.5x to ensure ease of scanning in data-heavy views.
- **Labels:** Small labels and captions use a slightly heavier weight (Medium/SemiBold) and increased letter spacing to maintain legibility at reduced scales.
- **Scaling:** Headlines scale down by approximately 15% on mobile devices to prevent excessive wrapping.

## Layout & Spacing

The layout is built on a **Fluid Grid** system with a strict 8px baseline. 

- **Desktop:** 12-column grid with 24px gutters and 48px side margins. Maximum container width is capped at 1280px to preserve line length for readability.
- **Tablet:** 8-column grid with 24px gutters and 32px side margins.
- **Mobile:** 4-column grid with 16px gutters and 16px side margins.

Spacing tokens follow a geometric scale (8, 16, 24, 32, 48, 64) to ensure rhythmic consistency across the application. Padding within components like cards and modals should favor the 24px (3x) unit to reinforce the open, professional aesthetic.

## Elevation & Depth

Visual hierarchy is established using **Tonal Layers** and subtle **Ambient Shadows**. 

- **Surface Tiers:** Backgrounds use pure white, while nested containers (like sidebars or cards) use a very light gray (#F5F7FA) or 1px strokes in a light neutral to define boundaries without adding visual "weight."
- **Shadows:** When depth is required (e.g., dropdowns, modals), use extra-diffused shadows with a low-opacity blue tint. For example: `0px 4px 20px rgba(19, 117, 192, 0.08)`.
- **Interaction:** Lift effects on hover should be subtle—moving from a flat stroke to a soft shadow—to maintain the grounded, professional feel.

## Shapes

The system uses a **Rounded** shape language to balance the technical rigidity of the typography.

- **Standard Components:** Buttons, input fields, and small chips use a 0.5rem (8px) corner radius.
- **Large Components:** Cards and modals use a 1rem (16px) radius to create a distinct container feel.
- **Icons:** Should follow the 8px rounding logic where possible, avoiding sharp 90-degree corners to remain cohesive with the UI elements.

## Components

- **Buttons:** Primary buttons use the #1375C0 background with white text. Secondary buttons use a #1375C0 outline or a light blue container. High-action accents can utilize the #00D7F8 Cyan specifically for "new" or "success" related signals.
- **Input Fields:** Use a 1px neutral border (#D1D5DB) that shifts to Primary Blue on focus. Labels sit clearly above the field in Label-LG style.
- **Cards:** White background with a 1px light gray border. In specific dashboard contexts, a Primary Blue top-border (4px) can be used to categorize high-priority cards.
- **Chips:** Soft-rounded (8px) with background colors derived from the "container" tokens (e.g., Primary-Container).
- **Lists:** Clean, horizontal dividers with 16px of vertical padding per item. Interactive list items should show a #F5F7FA hover state.
- **Gradients:** A subtle linear gradient from Primary Blue to Secondary Cyan can be used for Hero sections or data visualization highlights, but should be avoided for functional interactive components to maintain clarity.