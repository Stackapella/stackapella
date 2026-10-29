"""Average the sub-frames render.py captures into one motion-blurred frame.

average() takes the JPEG (or PNG) bytes of a frame's samples and returns one Pillow image. Averaging
is a pairwise tree of ImageChops.add(scale=2), which is an exact mean for power-of-two sample counts
and keeps rounding to one step per level. Needs only Pillow.

    python3 blend.py out.jpg a.jpg b.jpg ...    average some stills by hand
"""
import io
import sys

from PIL import Image, ImageChops


def average(samples):
    frames = [Image.open(io.BytesIO(s)).convert('RGB') for s in samples]
    while len(frames) > 1:
        frames = [ImageChops.add(frames[i], frames[i + 1], scale=2.0) if i + 1 < len(frames) else frames[i]
                  for i in range(0, len(frames), 2)]
    return frames[0]


if __name__ == '__main__':
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    average([open(p, 'rb').read() for p in sys.argv[2:]]).save(sys.argv[1], quality=94, subsampling=0)
