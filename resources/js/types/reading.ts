export type ReaderSentence = {
    position: number;
    words: string[];
};

export type ReadingProgress = {
    read: number;
    total: number;
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
