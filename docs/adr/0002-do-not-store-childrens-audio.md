# Do not store children's voice recordings

When a Child reads, the recording is sent to the speech-to-text service and deleted once it has been processed. Only the transcript and the per-word results are kept. Voice recordings of minors are sensitive personal data, and v1 has no use for them that justifies the risk.

## Consequences

- Past Readings can never be re-scored with a better speech model, and there is no recorded data to tune or fine-tune one with. If that is needed later, add a separate recording opt-in that a Parent turns on explicitly. Do not quietly start keeping audio.
