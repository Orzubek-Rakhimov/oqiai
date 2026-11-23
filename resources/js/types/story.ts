export type StoryLevel = 1 | 2 | 3;

export type LibraryStory = {
    id: number;
    slug: string;
    title: string;
    level: StoryLevel;
    minutes: number;
    isNew: boolean;
    bestStars: number | null;
};

export type SuggestedStory = {
    id: number;
    title: string;
    level: StoryLevel;
    minutes: number;
};
