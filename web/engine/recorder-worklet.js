// Records an exact window of frames on the audio clock and streams it to the page in chunks.
// currentFrame is the frame of the first sample in each render block, so a recording from frame A
// holds exactly the samples that entered the graph from frame A on, whatever the timing.
const CHUNK = 4096;

class Recorder extends AudioWorkletProcessor {
    constructor() {
        super();
        this.job = null;
        this.port.onmessage = (event) => this.onMessage(event.data);
    }

    onMessage(message) {
        if (message.cmd === 'start') {
            this.job = {
                id: message.id,
                from: message.fromFrame,
                to: message.toFrame ?? Infinity,
                chunk: new Float32Array(CHUNK),
                filled: 0,
                emptyQuanta: 0,
                firstBlock: -1,
            };
        } else if (message.cmd === 'end' && this.job && this.job.id === message.id) {
            this.job.to = Math.max(this.job.from, message.atFrame);
        } else if (message.cmd === 'cancel') {
            this.job = null;
        }
    }

    flush(job) {
        if (job.filled > 0) {
            const data = job.chunk.slice(0, job.filled);
            this.port.postMessage({ type: 'chunk', id: job.id, data }, [data.buffer]);
        }
        job.chunk = new Float32Array(CHUNK);
        job.filled = 0;
    }

    process(inputs) {
        const job = this.job;
        if (!job) {
            return true;
        }
        const input = inputs[0];
        const channel = input && input.length > 0 ? input[0] : null;
        const blockStart = currentFrame;
        const blockEnd = blockStart + (channel ? channel.length : 128);
        if (blockEnd <= job.from) {
            return true;
        }
        if (job.firstBlock < 0) {
            job.firstBlock = blockStart;
        }
        if (!channel) {
            // No channels: a dropout from a live mic (in loopback, also anything before playback).
            job.emptyQuanta++;
        }
        const start = Math.max(blockStart, job.from);
        const end = Math.min(blockEnd, job.to);
        for (let frame = start; frame < end; frame++) {
            job.chunk[job.filled++] = channel ? channel[frame - blockStart] : 0;
            if (job.filled === CHUNK) {
                this.flush(job);
            }
        }
        if (blockEnd >= job.to) {
            this.flush(job);
            this.port.postMessage({
                type: 'ended',
                id: job.id,
                emptyQuanta: job.emptyQuanta,
                lateStart: job.firstBlock > job.from,
            });
            this.job = null;
        }
        return true;
    }
}

registerProcessor('recorder', Recorder);
