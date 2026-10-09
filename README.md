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
| `driver-behaviour.html`   | RouteGuard: Route-Aware Driver Behaviour Analytics (IT22297440)      |
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
assets/figures/behaviour/  driver behaviour figures
assets/photos/             NCG demonstration photographs
```

Research PDFs are not stored in this folder. They open from Google Cloud Storage
(`storage.googleapis.com/citos-portfolio/...`), including the documents listed in
`citos/public/kauz_documents/links.txt` (driver distraction) and
`citos/public/harry_component/links.txt` (driver behaviour).

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

- **Carousels** do not auto-advance; visitors use Previous / Next or the dots.
- **Milestone status** (Completed / Upcoming) was set on 10 October 2026. To update one,
  change its `data-status` to `done` or `upcoming` and its badge text.
- **Footer year** is written as 2026.

## Contact form

The form posts to `contact.php`, the only server-side file. It validates the
message, emails it to `CONTACT_RECIPIENTS` over SMTP, sends the visitor a
confirmation, and redirects back to `contact.html#sent` (or `#send-error`,
`#send-invalid`, `#send-limit`). CSS `:target` shows the matching message.
It needs PHP 7.4+ with `openssl`, which any host that runs WordPress has.

Settings come from `.env` (copy `.env.example` and fill it in):

| Variable | Meaning |
| --- | --- |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE` | SMTP server. Port 465 uses TLS, 587 uses STARTTLS |
| `SMTP_USER`, `SMTP_PASS` | Login. For Gmail use an App Password |
| `MAIL_FROM` | From header. With Gmail it must be `SMTP_USER` |
| `CONTACT_RECIPIENTS` | Comma-separated team addresses |
| `CONTACT_SEND_AUTOREPLY` | `true` sends the visitor a confirmation |

**Keep `.env` private.** `contact.php` looks for it one folder *above* the site
first, so the safest upload is:

```
public_html/        ← or wherever the course web space starts
  .env              ← here, outside the site folder, if the host allows it
  citos/            ← this folder: index.html, contact.php, …
```

If it has to sit inside the site folder, `.htaccess` blocks downloads of every
dotfile (Apache hosts). `.env` is git-ignored. Submissions are limited to 5 per
IP every 10 minutes, and a hidden honeypot field filters simple bots.

## Deploy to Cloud Run

The `Dockerfile` builds a `php:8.3-apache` image: Apache serves the pages and
runs `contact.php`. It listens on Cloud Run's `$PORT` (8080 by default).
`.env` is never copied into the image (`.dockerignore`), so the SMTP settings
are added to the Cloud Run service instead.

**From GitHub (continuous deployment):** Cloud Run → *Create service* →
*Continuously deploy from a repository* → pick this repo and branch →
*Build type: Dockerfile*, source location `/Dockerfile` → container port `8080`
→ *Allow unauthenticated invocations*. Under *Variables & secrets* add every
key from `.env.example` with the real values (put `SMTP_PASS` in Secret Manager
and reference it as a secret).

**From the command line:**

```
gcloud run deploy citos-basic --source . --region asia-south1 --port 8080 --allow-unauthenticated   --set-env-vars "^;^SMTP_HOST=smtp.gmail.com;SMTP_PORT=465;SMTP_USER=you@gmail.com;MAIL_FROM=CITOS Research <you@gmail.com>;CONTACT_RECIPIENTS=IT2207184@my.sliit.lk,saifulis.4965@gmail.com;CONTACT_SEND_AUTOREPLY=true"   --set-secrets SMTP_PASS=citos-smtp-pass:latest
```

(`^;^` makes `;` the separator, because the recipient list contains commas.
Create the secret first with `gcloud secrets create citos-smtp-pass --data-file=-`.)

**Locally:** `docker compose up --build`, then open http://localhost:8080
(compose reads the SMTP settings from `.env`).

## Running it

Open `index.html` in a browser to view the pages. The contact form needs PHP,
so upload the folder to the course web (or run `php -S localhost:8000` in it).
The total size is about 5 MB.
