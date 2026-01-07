# Social Metadata Feature

Welcome to the [StaticForge](https://calevans.com/staticforge) Social Metadata feature! We know how important it is for your content to look great when shared on social media. Whether it's a link dropped in Slack, a tweet, or a Facebook post, you want that rich preview card with a nice image and title.

This feature handles all the heavy lifting for you. It automatically generates Open Graph (Facebook, LinkedIn, etc.) and Twitter Card metadata tags and injects them right into your page's `<head>`.

---

## How It Works

You don't need to do anything special. StaticForge looks at the frontmatter you're already writing for your pages and uses it to build the metadata.

1.  **You Write Content**: Add standard fields like `title`, `description`, and `hero` (or `image`) to your page's frontmatter.
2.  **We Generate Tags**: During the build process, we convert that data into `<meta property="og:title">`, `<meta name="twitter:card">`, and more.
3.  **We Inject**: We find the `</head>` tag in your HTML and slip those meta tags in right before it closes.

---

## Configuration

While per-page data is great, you don't want to repeat your Twitter handle on every single file. That's where `siteconfig.yaml` comes in. You can set site-wide defaults that act as a fallback.

Add this to your `siteconfig.yaml`:

```yaml
social:
  enabled: true # Set to false to disable sitewide
  twitter_handle: "@calevans"
  default_image: "/assets/images/social-share.jpg"
```

*   **`enabled`**: Set to `false` to completely disable social metadata generation for the entire site.
*   **`twitter_handle`**: The Twitter username for the site or author (e.g., `@calevans`).
*   **`default_image`**: A fallback image URL to use if a specific page doesn't define one.

We also use your main `site.name` from `siteconfig.yaml` to populate the `og:site_name` property.

---

## Usage in Content

StaticForge is smart about finding your metadata. It looks for specific `social` overrides first, then falls back to your standard frontmatter.

Here is what your Markdown frontmatter might look like:

```yaml
---
title: "My Awesome Blog Post"
description: "Learn how to build static sites with PHP."
hero: "/assets/images/posts/static-sites.jpg" # Standard image field
author: "@calevans"
type: "article"

# Optional: Dedicated Social Block for overrides
social:
  title: "Clickbait Title"                              # Override page title
  description: "Short summary"                          # Override page description
  facebook_image: "/assets/images/posts/og-1200.jpg"    # Specific override for FB
  twitter_image: "/assets/images/posts/twitter-2x1.jpg" # Specific override for Twitter
---
```

### Disabling for a Specific Page

If you want to disable social metadata for a specific page (like a private landing page), you can set `social: false` in the frontmatter:

```yaml
---
title: "Private Page"
social: false
---
```

### Supported Fields & Fallbacks

Here is the full list of frontmatter keys we look for and what they map to:

| Frontmatter Key | Fallback Chain (checked in order) | Maps To |
| :--- | :--- | :--- |
| **Title** | `social.title` → `title` → `site.title` | `og:title`, `twitter:title` |
| **Description** | `social.description` → `description` → `site.description` | `og:description`, `twitter:description` |
| **Image** | `social.image` → `image` → `hero` → `social.default_image` | `og:image`, `twitter:image` |
| **Facebook Image** | `social.facebook_image` → *Generic Image* | `og:image` |
| **Twitter Image** | `social.twitter_image` → *Generic Image* | `twitter:image` |
| **Alt Text** | `social.image_alt` → `image_alt` | `og:image:alt`, `twitter:image:alt` |
| **Creator** | `social.creator` → `author` | `twitter:creator` |
| **URL** | `url` | `og:url` |
| **Type** | `type` (defaults to `website`) | `og:type` |

---

## Site Auditing

This feature integrates with the StaticForge Auditor. When you run `staticforge site:audit`, we will check your compiled pages for missing social tags.

The auditor checks for the presence of:
*   Open Graph: `og:title`, `og:description`, `og:image`, `og:url`
*   Twitter: `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`

If any of these are missing, you will see a warning in your audit report.

---

## Troubleshooting

**"I don't see the tags in my source code!"**
Check your template. This feature looks for the `</head>` closing tag to know where to insert the metadata. If your template is missing that tag (or if it's malformed), we can't inject the metadata.

**"The image isn't showing up on Twitter!"**
Make sure your `image` path is correct. While we support relative paths, social networks often prefer absolute URLs (starting with `https://`). We try to help, but providing a full URL or ensuring your `SITE_BASE_URL` is set correctly in `.env` is best practice.
