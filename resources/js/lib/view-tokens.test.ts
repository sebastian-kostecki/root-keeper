/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vite-plus/test';

// Control for the design-token contract of the /persons, /sources and /places list views, the /tree
// cards and panels: the colours, spacing and focus rings of these files come from tokens
// (resources/css/app.css) and shared constants (lib/focus-ring.ts, tree/card-styles.ts),
// never from literals written in the view.

const here = dirname(fileURLToPath(import.meta.url));
const jsRoot = resolve(here, '..');

/**
 * The files that render the /persons, /sources and /places list views, the /tree cards and panels,
 * relative to resources/js. family-sheet.tsx and ancestor-chart.tsx are listed; their geometry
 * (column widths, tree lines) is computed at runtime, so `style={` is the one exception (see EXCEPTIONS).
 */
const VIEW_FILES = [
    'pages/persons/index.tsx',
    'components/persons/persons-list.tsx',
    'components/persons/persons-table.tsx',
    'components/persons/persons-empty-state.tsx',
    'components/persons/person-list-parts.tsx',
    'components/persons/event-source-status.tsx',
    'components/persons/partial-date-input.tsx',
    'components/table-parts.tsx',
    'pages/sources/index.tsx',
    'components/sources/sources-filters.tsx',
    'components/sources/sources-gallery.tsx',
    'components/sources/sources-table.tsx',
    'components/sources/sources-empty-state.tsx',
    'components/sources/source-thumbnail.tsx',
    'pages/places/index.tsx',
    'components/places/places-empty-state.tsx',
    'components/localities/localities-table.tsx',
    'components/parishes/parishes-table.tsx',
    'components/tree/ancestor-card.tsx',
    'components/tree/ancestor-chart.tsx',
    'components/tree/card-avatar.tsx',
    'components/tree/family-sheet.tsx',
    'components/tree/sheet-person-card.tsx',
    'components/tree/unknown-person-card.tsx',
    'components/tree/union-node.tsx',
    'components/tree/marriage-rings-icon.tsx',
    'components/tree/add-person-panel.tsx',
    'components/tree/marry-couple-dialog.tsx',
    'components/tree/card-styles.ts',
    'components/tree/add-person-config.ts',
    'components/side-panel.tsx',
    'components/persons/person-modal.tsx',
    'components/persons/events-tab.tsx',
    'components/timeline.tsx',
    'components/sources/source-panel.tsx',
    'components/localities/locality-panel.tsx',
    'components/parishes/parish-panel.tsx',
    'components/marriages/marriage-panel.tsx',
];

const PALETTE_COLOURS =
    'red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone';

type Rule = {
    name: string;
    pattern: RegExp;
    fix: string;
};

const RULES: Rule[] = [
    {
        name: 'hex colour',
        pattern: /#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b/g,
        fix: 'use a colour token from resources/css/app.css (@theme) through its role class, e.g. bg-accent or text-muted-foreground',
    },
    {
        name: 'rgb/oklch colour function',
        pattern: /\b(?:rgb|oklch)\(/g,
        fix: 'use a colour token from resources/css/app.css (@theme) through its role class',
    },
    {
        name: 'Tailwind palette class',
        pattern: new RegExp(
            `\\b(?:bg|text|border|ring|from|to|via)-(?:${PALETTE_COLOURS})-\\d{2,3}\\b`,
            'g',
        ),
        fix: 'use a role class: text-destructive-foreground for errors, bg-accent / text-muted-foreground for neutral states',
    },
    {
        name: 'arbitrary pixel value',
        pattern: /[\w:.-]*-\[-?\d+(?:\.\d+)?px\]/g,
        fix: 'use a scale class (e.g. ring-3, size-4), or the shared focusRing from lib/focus-ring.ts for focus rings',
    },
    {
        name: 'inline style attribute',
        pattern: /\sstyle=\{?/g,
        fix: 'move the value into a class that uses a token (no inline style in views)',
    },
];

/**
 * Literals that stay on purpose. Each entry names the file, the token and the reason.
 * Keep this list short: a new exception needs its own deferred item in the change's research.
 */
const EXCEPTIONS: { file: string; token: string; reason: string }[] = [
    {
        file: 'components/persons/person-list-parts.tsx',
        token: 'align-[-2px]',
        reason: 'D7: optical alignment of the list icon, no token yet (research.md, deferred D7)',
    },
    {
        file: 'components/sources/source-panel.tsx',
        token: 'style={',
        reason: "szerokość paska postępu wysyłania pliku liczona w runtime (procent); odroczone: pasek przez zmienną CSS lub <progress> w osobnej zmianie (plan.md, What We're NOT Doing)",
    },
    {
        file: 'components/tree/ancestor-chart.tsx',
        token: 'style={',
        reason: 'geometria wykresu przodków liczona w runtime (szerokość rzędu i kolumny w rem, położenie linii łączących); kolory linii idą przez tokeny',
    },
    {
        file: 'components/tree/family-sheet.tsx',
        token: 'style={',
        reason: 'geometria kolumn i komórek arkusza liczona w runtime (rem z COLUMN_REM / liczby dzieci, położenie ramion łącznika); kolory i grubości linii idą przez tokeny',
    },
];

type Violation = {
    file: string;
    line: number;
    token: string;
    rule: string;
    fix: string;
};

function scanFile(relativePath: string): Violation[] {
    const source = readFileSync(resolve(jsRoot, relativePath), 'utf8');
    const violations: Violation[] = [];

    source.split('\n').forEach((text, index) => {
        for (const rule of RULES) {
            for (const match of text.matchAll(rule.pattern)) {
                const token = match[0].trim();
                const excepted = EXCEPTIONS.some(
                    (exception) =>
                        exception.file === relativePath &&
                        exception.token === token,
                );

                if (!excepted) {
                    violations.push({
                        file: relativePath,
                        line: index + 1,
                        token,
                        rule: rule.name,
                        fix: rule.fix,
                    });
                }
            }
        }
    });

    return violations;
}

function describeViolations(violations: Violation[]): string {
    return violations
        .map(
            (violation) =>
                `resources/js/${violation.file}:${violation.line} — ${violation.rule} "${violation.token}": ${violation.fix}`,
        )
        .join('\n');
}

describe('list views, tree cards and panels use tokens, not literals', () => {
    it('has no hex, palette, arbitrary pixel or inline style values in the view files', () => {
        const violations = VIEW_FILES.flatMap(scanFile);

        expect(violations, describeViolations(violations)).toEqual([]);
    });

    it('scans every file it names (none is missing)', () => {
        for (const relativePath of VIEW_FILES) {
            expect(() =>
                readFileSync(resolve(jsRoot, relativePath), 'utf8'),
            ).not.toThrow();
        }
    });
});
