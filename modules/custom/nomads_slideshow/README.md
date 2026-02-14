# Nomads Slideshow

Splide-based slideshow field formatter for teaser cards and listing views.

## Installation

1) Copy the module to:
   `app/modules/custom/nomads_slideshow`
2) Place Splide vendor assets locally:
   - `vendor/splide/js/splide.min.js`
   - `vendor/splide/css/splide.min.css`
3) Enable the module and clear caches.

## Splide assets

This module expects Splide assets to be placed locally under the module. Provide the minified JS/CSS files for your chosen Splide version (e.g. the latest stable version from the Splide release you use site-wide).

## Usage

- Go to the field display or view mode for your content type.
- Choose one of the formatters:
  - "Nomads Slideshow (Splide) for Image" (image fields)
  - "Nomads Slideshow (Splide) for Media Images" (media image references)
- Configure image style, autoplay, arrows, pagination, aspect ratio, and lazy loading.

## Views teaser cards

When using a View with teaser cards, select the relevant field formatter in the view mode used by the View (or in the view field display if rendering fields). The Splide library is only attached when there are at least two images.
