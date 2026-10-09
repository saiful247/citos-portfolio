# CITOS — static site (HTML + CSS)

The public website for research project **R26-DS-013** (SLIIT Faculty of Computing),
the Comprehensive Intelligent Transport Observation System. It is a plain HTML and
CSS clone of the Next.js version in `../citos`: no framework, no build step and no
JavaScript.

## Pages

| File                      | Page                                                                 |
| ------------------------- | -------------------------------------------------------------------- |
| `index.html`              | Home: hero, four research areas, system overview, highlights, NCG demonstration |
| `domain.html`             | Group research story: overview, literature / problem / gap / objectives / methodology / technologies tabs, architecture, direction and limits |
| `research.html`           | All six research components, the GraphRAG deep dive and the publication |
| `driver-assessment.html`  | Intelligent Bus Driver Assessment & Explainable AI (IT22071484)      |
| `driver-fatigue.html`     | Driver Fatigue and Drowsiness Detection (IT22071620)                 |
| `driver-distraction.html` | Context-Aware Driver Distraction Detection (IT22232472)              |
| `milestones.html`         | 2026 Regular Batch timeline with a Group / Individual filter          |
| `documents.html`          | All project and component documents                                  |
| `presentations.html`      | Presentation slides carousel                                         |
| `about.html`              | Researchers and supervisors                                          |
| `contact.html`            | Contact details and the message form                                 |
| `404.html`                | Not-found page                                                       |

```
css/styles.css             the only stylesheet
assets/images/             logo (PNG and SVG favicon)
assets/figures/            driver assessment figures
assets/figures/fatigue/    driver fatigue figures
assets/photos/             NCG demonstration photographs
```

Research PDFs are not stored in this folder. They open from Google Cloud Storage
(`storage.googleapis.com/citos-portfolio/...`), including the driver distraction
documents listed in `citos/public/kauz_documents/links.txt`.

## How the interactive parts work without JavaScript

| Feature | Technique |
| --- | --- |
| Domain tabs, architecture explorer, gaze states, risk scenarios, stream selector | Hidden radio inputs inside a `.switch`; `<label for>` elements select them; `:has(> input[data-at="n"]:checked)` shows panel `n` |
| Component detail windows | HTML `popover` + `popovertarget`, `::backdrop`, `body:has(.modal:popover-open)` locks scrolling |
| Mobile menu | HTML `popover` with `@starting-style` transitions |
| NCG photos and presentation carousels | Radio inputs + labels for Previous / Next / dots; the slide track moves with a `--i` custom property |
| Milestone filter | A real `<select>`; `:has(option[value="Group"]:checked)` hides the other scope |
| Header frosting on scroll, reveal-on-scroll | Scroll-driven animations (`animation-timeline: scroll()` and `view()`) |
| Form validation | HTML attributes and `:user-invalid` |

Other modern CSS used: cascade layers, design tokens as custom properties, native
nesting, range media queries, `@property`, `color-mix()`, logical properties and
`prefers-reduced-motion` support.

## Differences from the Next.js version

Only where a feature needed JavaScript or a server:

- **Contact form**: there is no server to send email, so the form uses `action="mailto:"`.
- **Carousels** do not auto-advance; visitors use Previous / Next or the dots.
- **Milestone status** (Completed / Upcoming) was set on 10 October 2026. To update one,
  change its `data-status` to `done` or `upcoming` and its badge text.
- **Footer year** is written as 2026.

## Running it

Open `index.html` in a browser, or upload the whole folder to any web host.
The total size is about 4 MB.
