import { describe, expect, it } from 'vite-plus/test';
import { PANEL_PROPS, panelVisitOptions, withPanel } from './side-panel-url';

describe('withPanel', () => {
    it('opens a panel on a URL without a query', () => {
        expect(withPanel('/tree', 'person', 7)).toBe('/tree?person=7');
    });

    it('replaces the id of the same key', () => {
        expect(withPanel('/tree?person=7', 'person', 9)).toBe('/tree?person=9');
    });

    it('removes other panel keys when opening a panel', () => {
        expect(withPanel('/tree?person=7', 'demo', 'a')).toBe('/tree?demo=a');
    });

    it('opens the locality panel and drops the key of another panel', () => {
        expect(withPanel('/localities?person=3', 'locality', 'new')).toBe(
            '/localities?locality=new',
        );
    });

    it('opens the parish panel and drops the key of another panel', () => {
        expect(withPanel('/parishes?parish=3&locality=2', 'locality', 5)).toBe(
            '/parishes?locality=5',
        );
        expect(withPanel('/parishes?locality=2', 'parish', 'new')).toBe(
            '/parishes?parish=new',
        );
    });

    it('removes only its own key on null', () => {
        expect(withPanel('/tree?person=7&sort=name', 'person', null)).toBe(
            '/tree?sort=name',
        );
        expect(withPanel('/tree?demo=1&sort=name', 'person', null)).toBe(
            '/tree?demo=1&sort=name',
        );
    });

    it('keeps unrelated parameters and the hash', () => {
        expect(withPanel('/tree?spouse=3&sort=name#top', 'person', 5)).toBe(
            '/tree?spouse=3&sort=name&person=5#top',
        );
    });

    it('does not treat a path segment as a panel key', () => {
        expect(withPanel('/tree/5', 'person', 8)).toBe('/tree/5?person=8');
        expect(withPanel('/tree/5?person=8', 'person', null)).toBe('/tree/5');
    });
});

describe('panelVisitOptions', () => {
    it('reloads every registered panel prop', () => {
        for (const prop of Object.values(PANEL_PROPS)) {
            expect(panelVisitOptions.only).toContain(prop);
        }
    });
});
