#!python2

# Detect and print the percentage of skin color in an image file.
# Usage: `python2 skin-color-detector.py image.jpg`

import os, glob, sys
from PIL import Image

def get_skin_ratio(suspect_image):
    cropped_image = suspect_image.crop((
        int(suspect_image.size[0] * 0.2),
        int(suspect_image.size[1] * 0.2),
        suspect_image.size[0] - int(suspect_image.size[0] * 0.2),
        suspect_image.size[1] - int(suspect_image.size[1] * 0.2)
    ))

    skin = sum([count for count, rgb in cropped_image.getcolors(cropped_image.size[0] * cropped_image.size[1]) if rgb[0] > 60 and rgb[1] < (rgb[0] * 0.85) and rgb[2] < (rgb[0] * 0.7) and rgb[1] > (rgb[0] * 0.4) and rgb[2] > (rgb[0] * 0.2)])

    return float(skin) / float(cropped_image.size[0] * cropped_image.size[1])

suspect_image_file = sys.argv[1]
image = Image.open(suspect_image_file)

skin_percent = get_skin_ratio(image) * 100

print skin_percent
