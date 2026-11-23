export type ReaderSentence = {
    position: number;
    words: string[];
};

export type ReadingProgress = {
    read: number;
    total: number;
};

export type ContinueReading = {
    id: number;
    story: { id: number; title: string };
    progress: ReadingProgress;
};

export type WordMark = {
    word: string;
    correct: boolean;
};

export type SentenceResult =
    | {
          status: 'scored';
          marks: WordMark[];
          progress: ReadingProgress;
          nextSentence: ReaderSentence | null;
      }
    | {
          status: 'unheard';
          reason: 'nothing_heard' | 'service_failed';
      };

export type StarCount = 1 | 2 | 3;

export type ReadingResult = {
    stars: StarCount;
    accuracy: number;
    readingSpeed: number;
    wordsToPractise: string[];
};
