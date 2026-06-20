import { describe, it, expect } from 'vitest';
import { isTrackGap, trackSegments, TRACK_GAP_DEG } from '../track.js';

describe('isTrackGap', () => {
    it('treats adjacent fixes a few metres apart as one continuous track', () => {
        // ~46 m at 6 kn over a 15 s reporting interval.
        expect(isTrackGap([43.3870, -8.3709], [43.3874, -8.3709])).toBe(false);
    });

    it('flags a multi-kilometre jump as a gap', () => {
        // The real telemetry gap that rendered as a blue bridging line
        // (~1.5 km, endpoint speed 0 kn).
        expect(isTrackGap([43.3990, -8.6700], [43.3870, -8.6657])).toBe(true);
    });

    it('uses TRACK_GAP_DEG as the boundary (exclusive)', () => {
        const within = [0, 0];
        const exactly = [0, TRACK_GAP_DEG];
        const beyond = [0, TRACK_GAP_DEG + 0.0001];
        expect(isTrackGap(within, exactly)).toBe(false);
        expect(isTrackGap(within, beyond)).toBe(true);
    });
});

describe('trackSegments', () => {
    it('splits the track at a gap instead of bridging it', () => {
        const points = [
            { pos: [43.0000, -8.0000], speed: 5 },
            { pos: [43.0004, -8.0000], speed: 5 }, // continuous
            { pos: [43.5000, -8.0000], speed: 0 }, // big jump → gap, not drawn
            { pos: [43.5004, -8.0000], speed: 6 }, // continuous again
        ];

        const segments = trackSegments(points);

        expect(segments).toHaveLength(2);
        expect(segments[0]).toEqual({ from: [43.0000, -8.0000], to: [43.0004, -8.0000], speed: 5 });
        // The 0 kn bridging segment across the gap is omitted; the track resumes
        // on the far side.
        expect(segments[1]).toEqual({ from: [43.5000, -8.0000], to: [43.5004, -8.0000], speed: 6 });
    });

    it('returns no segments for a single point', () => {
        expect(trackSegments([{ pos: [43, -8], speed: 0 }])).toEqual([]);
    });

    it('colours each segment by its end point speed', () => {
        const points = [
            { pos: [43.0000, -8.0000], speed: 1 },
            { pos: [43.0004, -8.0000], speed: 7 },
        ];
        expect(trackSegments(points)[0].speed).toBe(7);
    });
});
