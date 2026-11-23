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

/**
 * A misread word the Child deals with before the next sentence: its one
 * retry, or Syllable help once the retry was misread too. Clip URLs stay
 * null until the word's audio has been generated.
 */
export type PendingWord = {
    stage: 'retry' | 'syllable_help';
    sentence: number;
    position: number;
    text: string;
    syllables: string[];
    clips: { syllables: string | null; word: string | null };
};

/**
 * Where the Reading stands after a sentence, retry or Syllable help: the
 * pending word comes first; the next sentence follows once there is none.
 */
export type ReadingStep = {
    pendingWord: PendingWord | null;
    progress: ReadingProgress;
    nextSentence: ReaderSentence | null;
};

export type Unheard = {
    status: 'unheard';
    reason: 'nothing_heard' | 'service_failed';
};

export type SentenceResult =
    | ({
          status: 'scored';
          marks: WordMark[];
      } & ReadingStep)
    | Unheard;

export type RetryResult =
    | ({
          status: 'scored';
          correct: boolean;
      } & ReadingStep)
    | Unheard;

export type SyllableHelpResult = {
    status: 'done';
} & ReadingStep;
