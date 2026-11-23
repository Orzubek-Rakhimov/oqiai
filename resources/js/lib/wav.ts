/** The sample rate of the recordings sent to speech-to-text. */
export const WAV_SAMPLE_RATE = 16000;

/**
 * Bring mono samples down to the WAV sample rate, averaging the samples
 * that fall into each output sample (which also keeps out the highest
 * frequencies, as a simple low-pass filter).
 */
function resample(samples: Float32Array, inputRate: number): Float32Array {
    if (inputRate <= WAV_SAMPLE_RATE) {
        return samples;
    }

    const ratio = inputRate / WAV_SAMPLE_RATE;
    const output = new Float32Array(Math.floor(samples.length / ratio));

    for (let index = 0; index < output.length; index++) {
        const from = Math.floor(index * ratio);
        const to = Math.min(samples.length, Math.floor((index + 1) * ratio));
        let sum = 0;

        for (let position = from; position < to; position++) {
            sum += samples[position];
        }

        output[index] = to > from ? sum / (to - from) : 0;
    }

    return output;
}

/**
 * Write mono samples (from -1 to 1) as a 16-bit PCM WAV file at 16 kHz,
 * the format every speech-to-text service takes.
 */
export function encodeWav(
    chunks: Float32Array[],
    inputRate: number,
): ArrayBuffer {
    const joined = new Float32Array(
        chunks.reduce((length, chunk) => length + chunk.length, 0),
    );
    let offset = 0;

    for (const chunk of chunks) {
        joined.set(chunk, offset);
        offset += chunk.length;
    }

    const samples = resample(joined, inputRate);
    const rate = Math.min(inputRate, WAV_SAMPLE_RATE);
    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const view = new DataView(buffer);
    const text = (at: number, value: string) => {
        for (let index = 0; index < value.length; index++) {
            view.setUint8(at + index, value.charCodeAt(index));
        }
    };

    text(0, 'RIFF');
    view.setUint32(4, 36 + samples.length * 2, true);
    text(8, 'WAVE');
    text(12, 'fmt ');
    view.setUint32(16, 16, true); // the size of the format chunk
    view.setUint16(20, 1, true); // PCM
    view.setUint16(22, 1, true); // mono
    view.setUint32(24, rate, true);
    view.setUint32(28, rate * 2, true); // bytes a second
    view.setUint16(32, 2, true); // bytes a sample
    view.setUint16(34, 16, true); // bits a sample
    text(36, 'data');
    view.setUint32(40, samples.length * 2, true);

    for (let index = 0; index < samples.length; index++) {
        const sample = Math.max(-1, Math.min(1, samples[index]));
        view.setInt16(
            44 + index * 2,
            sample < 0 ? sample * 0x8000 : sample * 0x7fff,
            true,
        );
    }

    return buffer;
}
