# v0.6.0
## 06/04/2026

1. [](#new)
    * **Embed mode** — `?gravmud-embed=1` (alias `gravity-embed`) serves chromeless pages with `embed.html.twig`, theme CSS, and `frame-ancestors *` CSP for iframe demos
    * Blog dispatch fences: `blog-post-header`, `blog-teaser`, `blog-index`, `blog-body`
    * Video, gallery, carousel, wiki, badges, and receipts fence renderers
    * Compiler hooks for Commentz, Forumz, Messenger, Mud Bazaar, Swag Store, and FlipZine theme config

1. [](#improved)
    * Expanded `MudDesignSpec` and `MudAlphaCompiler` parity with gravmud.site production pages
    * README fence cheat sheet and install URLs updated for 0.6.0

# v0.5.0
## 05/30/2026

1. [](#new)
    * Public GitHub release — Grav MUD Alpha compiler plugin
    * `.mud` page extension registration for Grav 1.7 and 2.0
    * MarkUpDown Design Spec compiler — `@@@` tokens, `:::` layout fences, `++` NEXT Object Notation
    * Design Spec fences: hero, cards, pricing, timeline, compare, code, wiki, manifesto, and more
    * Grav 2.0 compatibility flag in blueprints

# v0.3.0
## 05/2026

1. [](#new)
    * Design Spec fence renderer (`MudDesignSpec`)
    * Lone Mamber layout primitives
    * Asset base URL injection from active theme

# v0.1.0
## 04/2026

1. [](#new)
    * Initial `.mud` extension hook via Language reflection
    * `onPageContentRaw` compile pipeline
    * Twig site variables for badge metadata
