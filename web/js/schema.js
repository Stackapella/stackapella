// Checks JSON against the contract's JSON Schema subset (contract/api.md, "JSON Schema: the subset
// we use"): type (with "null" and lists), properties, required, additionalProperties: false,
// items, enum, format (date-time, uri), $ref (other.json, other.json#/$defs/x, #/$defs/x), $defs.
// The backend's twin is server/app/Support/Contract/SchemaChecker.php; the formats and the
// keywords allowed are the same, so the two can't disagree.

/**
 * The problems with `value` against schema `name` ('template.json'), as a list of
 * "path: problem" strings (empty when it passes). `load(name)` returns a schema's JSON.
 */
export async function check(value, name, load) {
    const schemas = new Map();
    const get = async (file) => {
        if (!schemas.has(file)) schemas.set(file, await load(file));
        return schemas.get(file);
    };
    const problems = [];
    await walk(value, await get(name), name, '$', problems, get);
    return problems;
}

const KEYWORDS = new Set(['type', 'properties', 'required', 'additionalProperties', 'items', 'enum', 'format', '$ref', '$defs', '$id', 'title', 'description']);

async function walk(value, schema, file, path, problems, get) {
    for (const keyword of Object.keys(schema)) {
        if (!KEYWORDS.has(keyword)) problems.push(`${file}: the keyword ${keyword} isn't in the subset`);
    }
    if (schema.format && !['date-time', 'uri'].includes(schema.format)) {
        problems.push(`${file}: unknown format ${schema.format}`);
    }
    if (schema.$ref) {
        const [target, pointer] = schema.$ref.split('#');
        const targetFile = target || file;
        let resolved = await get(targetFile);
        for (const part of (pointer ?? '').split('/').filter(Boolean)) resolved = resolved?.[part];
        if (!resolved) {
            problems.push(`${path}: unknown $ref ${schema.$ref}`);
            return;
        }
        await walk(value, resolved, targetFile, path, problems, get);
        return;
    }
    if (schema.type !== undefined) {
        const types = [].concat(schema.type);
        if (!types.some((type) => is(value, type))) {
            problems.push(`${path}: expected ${types.join(' or ')}, got ${kind(value)}`);
            return;
        }
    }
    if (schema.enum && !schema.enum.includes(value)) {
        problems.push(`${path}: ${JSON.stringify(value)} isn't one of ${schema.enum.join(', ')}`);
    }
    if (schema.format && typeof value === 'string') {
        if (schema.format === 'date-time' && !(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/.test(value) && !Number.isNaN(Date.parse(value)))) {
            problems.push(`${path}: not a date-time with seconds and a time zone: ${value}`);
        }
        if (schema.format === 'uri' && !isUrl(value)) {
            problems.push(`${path}: not a URI: ${value}`);
        }
    }
    if (is(value, 'object')) {
        for (const key of schema.required ?? []) {
            if (!(key in value)) problems.push(`${path}: ${key} is required`);
        }
        for (const [key, item] of Object.entries(value)) {
            const property = schema.properties?.[key];
            if (property) {
                await walk(item, property, file, `${path}.${key}`, problems, get);
            } else if (schema.additionalProperties === false) {
                problems.push(`${path}: ${key} isn't allowed`);
            }
        }
    }
    if (Array.isArray(value) && schema.items) {
        for (const [i, item] of value.entries()) {
            await walk(item, schema.items, file, `${path}[${i}]`, problems, get);
        }
    }
}

/** Like PHP's FILTER_VALIDATE_URL: a scheme and a host. */
function isUrl(value) {
    try {
        const url = new URL(value);
        return /^[a-z][a-z0-9+.-]*:$/i.test(url.protocol) && !!url.hostname;
    } catch {
        return false;
    }
}

function is(value, type) {
    switch (type) {
        case 'null': return value === null;
        case 'object': return value !== null && typeof value === 'object' && !Array.isArray(value);
        case 'array': return Array.isArray(value);
        case 'integer': return Number.isInteger(value);
        case 'number': return typeof value === 'number' && Number.isFinite(value);
        default: return typeof value === type;
    }
}

function kind(value) {
    return value === null ? 'null' : Array.isArray(value) ? 'array' : typeof value;
}
