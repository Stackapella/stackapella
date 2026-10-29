"""The web app's contract test, without a browser: every operation in contract/api.md's Operations
table has the same method and path in web/js/api.js's OPERATIONS, and api.js has none api.md
lacks (web/tests/api.test.js, "every operation in api.md has its function in api.js"). Operations
only the native apps call ("An app ..." in the Who column) aren't the web client's, as
web/js/api-mock.js's parseContract() reads them. Run before landing a contract change, so a new
operation never lands without its api.js line. Exit 1 and say which when they differ.

    python3 -I server/tool/contract-client-check.py [repo root]
"""

import pathlib
import re
import sys

root = pathlib.Path(sys.argv[1] if len(sys.argv) > 1 else pathlib.Path(__file__).resolve().parents[2])
markdown = (root / 'contract/api.md').read_text()
api = (root / 'web/js/api.js').read_text()

table = re.search(r'## Operations\n[\s\S]*?\n\n((?:\|.*\n)+)', markdown)
routes = {}
for row in (table.group(1).split('\n') if table else []):
    match = re.match(r'^\| `([\w.]+)` \| `(\w+) ([^`]+)` \| ([^|]*)\|', row)
    if match and not re.match(r'^An app\b', match.group(4).strip()):
        routes[match.group(1)] = (match.group(2), match.group(3))

block = re.search(r'export const OPERATIONS = \{\n([\s\S]*?)\n\};', api)
operations = {}
for line in (block.group(1).split('\n') if block else []):
    match = re.match(r"\s*'([\w.]+)': \['(\w+)', '([^']+)'\],", line)
    if match:
        operations[match.group(1)] = (match.group(2), match.group(3))

problems = []
if len(routes) < 10 or len(operations) < 10:
    problems.append(f'read {len(routes)} operations from api.md and {len(operations)} from api.js: the formats changed?')
for name, route in sorted(routes.items()):
    if operations.get(name) != route:
        theirs = ' '.join(operations[name]) if name in operations else 'nothing'
        problems.append(f"{name}: api.md {' '.join(route)}, api.js {theirs}")
for name in sorted(set(operations) - set(routes)):
    problems.append(f'{name} is in api.js but not in api.md')

if problems:
    print('FAIL contract vs web/js/api.js (land the api.js line first):')
    for problem in problems:
        print('  ' + problem)
    sys.exit(1)
print(f'contract vs api.js: {len(routes)} operations match')
