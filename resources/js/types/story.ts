/** A Story's Level: one of the `levels` the library page receives (Story::LEVELS). */
export type StoryLevel = number;

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
