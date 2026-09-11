# Approved identity source

`approved-ui-kit.png` is a byte-for-byte copy of `UI/approved/design-system/master-ui-kit.png`.

The brand component displays only the original logo rectangle using CSS clipping. The mark and lettering are not redrawn or substituted with a font. Public and inverse logo positions are separately mapped. This preserves the supplied raster identity until standalone original logo assets are available.

`ui-kit.css` contains the palette, typography, spacing and radius tokens printed in the kit, and responsive image viewports for the horizontal logo, inverse sidebar logo and mark. Rectangle coordinates use the original 1672 × 941 image dimensions.

`../../favicon.svg` embeds the same unmodified PNG inside an SVG viewport around the original mark. This replaces the previous approximate vector drawing. Embedding is necessary because favicon SVGs cannot depend on loading another external image.
