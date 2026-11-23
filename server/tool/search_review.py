"""Wednesday's Search Console review, fast (docs/marketing/search-review.md).

Run from server/ after `php artisan app:pull-search-console --days=7` (and, for the 28-day view, --days=28):

    python3 tool/search_review.py                 # the newest pull against every earlier one, by matching window
    python3 tool/search_review.py --since 2026-11-18

It reads the saved pulls (storage/app/growth/search-console/sc-*.json, gitignored: never commit or paste them) and
docs/marketing/search-log.md, and prints, for this Mac only:
  1. totals for each read with the same window length (clicks, impressions, CTR, position);
  2. each page search-log.md changed since --since: its numbers in the read before the change and now;
  3. pages to improve (50+ impressions, CTR under 2% or position 8 to 20), the rule Admin > Growth > Search uses;
  4. pages that are new since the previous read, and their first numbers;
  5. searches new since the previous read, by impressions.
"""
import argparse
import datetime as dt
import glob
import json
import os
import re

HERE = os.path.dirname(os.path.abspath(__file__))
PULLS = os.environ.get('SC_PULLS') or os.path.join(HERE, '..', 'storage', 'app', 'growth', 'search-console')
LOG = os.environ.get('SC_LOG') or os.path.join(HERE, '..', '..', 'docs', 'marketing', 'search-log.md')
SITE = 'https://stackapella.com'


def load():
    out = []
    for f in sorted(glob.glob(os.path.join(PULLS, 'sc-*.json'))):
        with open(f) as fh:
            d = json.load(fh)
        w = d.get('window') or ['', '']
        days = (dt.date.fromisoformat(w[1]) - dt.date.fromisoformat(w[0])).days + 1 if w[0] else 0
        out.append({'file': os.path.basename(f), 'days': days, **d})
    # One pull per window: the last of the day wins.
    seen = {}
    for p in out:
        seen[(tuple(p['window']), p['days'])] = p
    return sorted(seen.values(), key=lambda p: p['window'][1])


def by_page(rows):
    return {r['keys'][0].replace(SITE, '') or '/': r for r in rows}


def fmt(r):
    if r is None:
        return 'not shown'
    return f"{r['clicks']:>4} clicks {r['impressions']:>6} impr  CTR {100 * r['ctr']:5.1f}%  pos {r['position']:5.1f}"


def logged(since):
    rows = []
    if not os.path.exists(LOG):
        return rows
    for line in open(LOG):
        m = re.match(r'\|\s*(\d{4}-\d{2}-\d{2})\s*\|\s*([^|]+)\|', line)
        if not m or m.group(1) < since:
            continue
        for path in re.findall(r'(/[a-z0-9/<>_-]+)', m.group(2)):
            if '<' not in path:
                rows.append((m.group(1), path))
    return rows


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--since', default=(dt.date.today() - dt.timedelta(days=7)).isoformat())
    args = ap.parse_args()
    pulls = load()
    if not pulls:
        raise SystemExit('No pulls yet: run php artisan app:pull-search-console first.')
    now = pulls[-1]
    same = [p for p in pulls if p['days'] == now['days']]
    prev = same[-2] if len(same) > 1 else None

    print(f"== 1. Totals, {now['days']}-day windows")
    for p in same:
        t = p['totals']
        print(f"  {p['window'][0]} to {p['window'][1]}  {t['clicks']:>5} clicks {t['impressions']:>7} impr  CTR {100 * t['ctr']:5.2f}%  pos {t['position']:5.1f}")

    pages_now = by_page(now['pages'])
    print(f"\n== 2. Pages changed since {args.since} (search-log.md): the read before the change, and now")
    for day, path in logged(args.since):
        before = next((p for p in reversed(same) if p['window'][1] < day), None)
        b = by_page(before['pages']).get(path) if before else None
        print(f"  {day}  {path}\n      before: {fmt(b)}\n      now:    {fmt(pages_now.get(path))}")

    print('\n== 3. Pages to improve (50+ impressions; CTR under 2% or position 8 to 20)')
    todo = [(k, r) for k, r in pages_now.items() if r['impressions'] >= 50 and (r['ctr'] < 0.02 or 8 <= r['position'] <= 20)]
    for k, r in sorted(todo, key=lambda kr: -kr[1]['impressions'])[:15]:
        print(f"  {k:<55} {fmt(r)}")

    if prev:
        old = by_page(prev['pages'])
        print('\n== 4. Pages new since the previous read')
        for k, r in sorted(((k, r) for k, r in pages_now.items() if k not in old), key=lambda kr: -kr[1]['impressions'])[:20]:
            print(f"  {k:<55} {fmt(r)}")
        oldq = {r['keys'][0] for r in prev['queries']}
        print('\n== 5. Searches new since the previous read')
        for r in sorted((r for r in now['queries'] if r['keys'][0] not in oldq), key=lambda r: -r['impressions'])[:20]:
            print(f"  {r['keys'][0][:55]:<55} {fmt(r)}")


if __name__ == '__main__':
    main()
