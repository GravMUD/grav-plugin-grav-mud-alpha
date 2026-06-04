# Grav MUD Alpha

**Site:** [alpha.gravmud.site](https://alpha.gravmud.site) · **Repo:** [GravMUD/grav-plugin-grav-mud-alpha](https://github.com/GravMUD/grav-plugin-grav-mud-alpha)

**MIT compiler plugin for Grav** — registers the `.mud` page extension and compiles MarkUpDown Design Spec to HTML.

> *Human/AI-friendly design language for flat-file CMS — no database, no drag-and-drop guilt.*

## What it does

- Registers **`.mud`** as a discoverable page extension (before Grav scans pages)
- Compiles **MarkUpDown Design Spec** on `onPageContentRaw`
- Supports **`@@@` design tokens**, **`:::` layout fences**, and **`++` NEXT Object Notation**
- Works on **Grav 1.7** and **Grav 2.0**

Pair with the **[grav-mud-site](https://github.com/GravMUD/grav-theme-grav-mud-site)** theme (coming soon) or your own Twig theme.

## Requirements

| Package | Version |
|---------|---------|
| [Grav](https://github.com/getgrav/grav) | `>=1.7.0` |

No Admin2 or API plugin required — this is a front-end page compiler.

## Installation

### GPM (once listed)

```bash
bin/gpm install grav-mud-alpha
```

### Manual

```bash
bin/gpm direct-install https://github.com/GravMUD/grav-plugin-grav-mud-alpha/releases/download/0.6.0/grav-plugin-grav-mud-alpha.zip
```

Or extract to `user/plugins/grav-mud-alpha`, then clear cache.

### Full starter overlay

For plugin + theme + demo pages, use the package on [gravmud.site/get-started](https://gravmud.site/get-started):

```powershell
# From GRAV-MUD monorepo
.\scripts\build-package.ps1
```

## Quick example

```mud
---
title: Hello MUD
format: mud-spec
process:
  markdown: false
---

@@@
name: grav-official
layout: promo
@@@

::: hero
eyebrow: Hello
title: Grav
accent: MUD
lead: One file. Full section.
:::
```

Save as `user/pages/01.home/default.mud`, enable the plugin, clear cache.

## Layout fence cheat sheet

Full reference with live **source | HTML** previews: **[gravmud.site/spec](https://gravmud.site/spec)**

Every fence also accepts a `spec-*` alias (e.g. `spec-hero`). Page wrapper: `@@@` … `@@@` with `name:` and `layout:`.

| Fence | Purpose |
|-------|---------|
| `::: hero` | Landing band — eyebrow, title, accent, lead, CTAs |
| `::: quote` | Pull quote + cite |
| `::: cards` | Card grid (`card:` blocks) |
| `::: timeline` | Dated events (`event:` blocks) |
| `::: compare` | Two-column comparison table (`row:` blocks) |
| `::: pricing` | Tier cards (`plan:` blocks) |
| `::: manifesto` | Closing pitch + signoff |
| `::: code` | Escaped doc sample |
| `::: video` | YouTube, Vimeo, or self-hosted embed |
| `::: gallery` / `::: carousel` | Image grid or reel |
| `::: blog-post-header` | Dispatch title block — date, dek, back link |
| `::: blog-teaser` | Homepage “latest post” promo |
| `::: blog-index` | Blog feed listing (`post:` / `posts:`) |
| `::: blog-body` | Article body wrapper (markdown `body:`) |
| `::: teeman` / `::: profile` | Avatar + cover art row (expose sites) |
| `::: receipts` / `::: trophies` / `::: guestbook` | Receipt/archive blocks |

### Blog dispatch pattern

Typical post: header fence, then profile/body (Lone Mamber uses `blog-body` on `::: teeman`):

```mud
::: blog-post-header
date: 1 Jun 2026
title: Lone Mamber GRAVitates
dek: Blogspot → Joomla → Grav CMS — the relaunch receipt.
back: /blog/
byline: Dr. D. Charles Caynes · FutureVision Labs
:::

::: teeman id=author blog-body article-cover
title: Dispatch hero caption
avatar: lone-mamber-avatar.png
cover: lone-mamber-hero.png
:::

Prose continues here — markdown paragraphs, `::: compare`, etc.
```

Homepage teaser:

```mud
::: blog-teaser
eyebrow: Latest dispatch
title: Why Grav MUD exists
lede: One `.mud` file. Full layout. Zero Twig soup.
link: /blog/why-grav-mud
link-label: Read the post →
:::
```

## Configuration

Admin → Plugins → **Grav MUD Alpha**

| Setting | Description |
|---------|-------------|
| **Plugin Status** | Enable/disable `.mud` registration and compilation |

## Commercial plugins

This repo is **MIT and free forever**. Commercial GravMUD plugins (EvvyTink Admin, Commentz, Forumz, Mud Bazaar, etc.) are **not** included — see [gravmud.site/marketplace](https://gravmud.site/marketplace).

## Development

```bash
user/plugins/grav-mud-alpha/
  grav-mud-alpha.php          # Extension hook + compile pipeline
  classes/MudAlphaCompiler.php
  classes/MudDesignSpec.php
```

Build a release zip from the GRAV-MUD monorepo:

```powershell
.\scripts\build-grav-mud-alpha-gpm.ps1
```

## Attribution

- **[Grav CMS](https://getgrav.org)** — flat-file foundation
- **Code Designer / mud-parser** — TypeScript reference; PHP compiler targets parity

## Author

**FutureVision Labs · Team DC**  
Damian Caynes — [gravmud.site](https://gravmud.site)
