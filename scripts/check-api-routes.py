"""Compare the live Symfony route table with the committed OpenAPI operations."""
import json
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[1]
contract = json.loads((root / 'docs/api/openapi.json').read_text())
routes = json.loads(Path(sys.argv[1]).read_text())
expected = {(method.upper(), '/api/v1' + path) for path, operations in contract['paths'].items() for method in operations if method in {'get', 'post', 'put', 'patch', 'delete'}}
actual = {(method, route['path']) for route in routes.values() if route['path'].startswith('/api/v1') for method in route['method'].split('|')}
if expected != actual:
    raise SystemExit(f'Contract-only operations: {sorted(expected - actual)}; Symfony-only operations: {sorted(actual - expected)}')
print(f'API route contract synchronized: {len(expected)} operations.')
