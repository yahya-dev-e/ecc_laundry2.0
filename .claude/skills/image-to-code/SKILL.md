---
name: image-to-code
description: Image-first website design-to-code skill. For visually important web design tasks, generate reference design images first, deeply analyze spacing, typography, and layout, and then implement the frontend matching the generated design.
---

# Image-First Website Design to Code

Directive for transforming visual website design concepts into high-fidelity, production-ready frontend code.

---

## 1. Core Workflow Order

For any visually important website or UI task, follow this strict three-step sequence:

```text
1. Image Generation First  -->  Create clear, large section-specific reference image(s)
2. Deep Image Analysis     -->  Extract exact typography, spacing scale, colors, and layout
3. Implementation Third    -->  Write production code faithfully reflecting the design
```

Do not skip straight to freeform coding when visual reference generation is available.

---

## 2. Section Image Generation Directives

* **Avoid Tiny Compressed Collages**: Do not squeeze an entire multi-section website into a single unreadable image where text and buttons are microscopic.
* **Generate Section-Specific Images**:
  - Hero section → dedicated image
  - Feature / Bento section → dedicated image
  - Interactive panel / Dashboard section → dedicated image
* **Do Not Crop Old Images**: If detail is unclear, generate a fresh standalone image for that section rather than cropping or zooming in on pixelated fragments.
* **Maintain Visual Consistency**: Keep identical color palettes, typography mood, button styles, and corner radius logic across all section images.

---

## 3. Deep Visual Analysis Checklist

Before writing any code, systematically extract the following tokens from the reference image(s):

1. **Typography**:
   - Headline scale, weight, and letter spacing (`tracking-tight`, uppercase vs title case).
   - Paragraph font size, line-height (`leading-relaxed`), and line-wrapping behavior.
2. **Color Palette**:
   - Background colors (neutral dark, off-white, light gray tint).
   - Primary and secondary brand accent colors.
   - Text color hierarchy (primary headline, secondary text, muted captions).
3. **Spacing & Layout Rhythm**:
   - Grid layout (columns, asymmetric spans, gutters).
   - Section padding (`py-12`, `py-16`, `py-20`).
   - Card internal padding (`p-4`, `p-6`).
4. **Component Details**:
   - Corner radius scale (`rounded-lg`, `rounded-xl`, `rounded-full`).
   - Button styling (solid, ghost, border thickness, pill shapes).
   - Shadow and border treatments (`border border-slate-200`, subtle elevation).

---

## 4. Faithful Translation to Code

* **Avoid Cards-Inside-Cards Clutter**: Do not nest excessive containers, pills, and boxes inside each other. Keep layouts breathable.
* **Match Visual Proportions**: Use CSS Grid and Flexbox to replicate the extracted alignment and responsive behavior.
* **High Contrast & Clarity**: Ensure text readability and contrast match or exceed the generated reference image.
