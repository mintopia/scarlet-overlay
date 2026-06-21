// @vitest-environment happy-dom
import { describe, it, expect, beforeEach } from 'vitest';
import { resolveColor, cssVar } from '../lib/uplotTheme.js';

describe('uplotTheme', () => {
    beforeEach(() => {
        document.documentElement.style.setProperty('--color-teal', '#0aa');
    });

    it('resolves a var() token to its computed value', () => {
        expect(resolveColor('var(--color-teal)')).toBe('#0aa');
    });

    it('passes through a plain color', () => {
        expect(resolveColor('#123456')).toBe('#123456');
    });

    it('returns the fallback for an undefined var', () => {
        expect(cssVar('--nope', '#fff')).toBe('#fff');
    });
});
