export type AvatarKey = 'fox' | 'owl' | 'bear' | 'rabbit' | 'cat' | 'lion';

export type Child = {
    id: number;
    name: string;
    grade: number;
    avatar: AvatarKey;
};

export type MicrophoneCheckResult =
    | { status: 'passed' }
    | { status: 'unheard'; reason: 'nothing_heard' | 'service_failed' };

/**
 * The figures the Parent screen shows for one Child. Weeks run Monday to
 * Sunday in Tashkent; readingSpeedByWeek lists the three weeks before this
 * one and then this week, with null for a week without a finished Reading.
 */
export type ChildProgress = {
    hasRead: boolean;
    daysPractised: number;
    storiesFinished: number;
    readingSpeedByWeek: { weekStart: string; readingSpeed: number | null }[];
    hardWords: { word: string; count: number }[];
    streak: number;
    starsTotal: number;
};
