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
