import { describe, expect, it } from 'vite-plus/test';
import { marriageRowDetails } from './marriage-details';

describe('marriageRowDetails', () => {
    it('prefixes the form before date and place', () => {
        expect(
            marriageRowDetails({
                marriage_form_label: 'cywilny',
                date: '15.06.2001',
                place: 'Kraków',
            }),
        ).toBe('ślub cywilny, 15.06.2001, Kraków');
    });

    it('leaves the line unchanged for an unknown form', () => {
        expect(
            marriageRowDetails({
                marriage_form_label: null,
                date: '15.06.2001',
                place: 'Kraków',
            }),
        ).toBe('15.06.2001, Kraków');
    });

    it('shows the form alone', () => {
        expect(
            marriageRowDetails({
                marriage_form_label: 'cywilno-wyznaniowy',
                date: null,
                place: null,
            }),
        ).toBe('ślub cywilno-wyznaniowy');
    });

    it('is empty when nothing is known', () => {
        expect(
            marriageRowDetails({
                marriage_form_label: null,
                date: null,
                place: null,
            }),
        ).toBe('');
    });
});
