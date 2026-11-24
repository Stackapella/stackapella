<?php

declare(strict_types=1);

/*
 * Machine-readable marking of AI-made audio (EU AI Act Article 50, in force from 2 August 2026; 2026-11-23; fields
 * agreed by the three lanes 2026-11-24, build plan Brief G): one text for every file holding an AI voice, the worker's
 * masters and everything the server and the app make from them. WAV (the worker, the app): LIST/INFO ICMT the text, ISFT,
 * an iXML whose USER is App\Support\AiVoices\AiMark::line(). MP3 (the app): COMM the text, TXXX AI_GENERATED,
 * AI_GENERATOR, DIGITAL_SOURCE_TYPE. MP4/M4A (the server: every AAC and the share videos): the iTunes comment (©cmt) the
 * text and the description (desc) the same line as the iXML USER.
 */
return [
    'text' => 'AI-generated voice (Stackapella, SoulX-Singer)',
    'generator' => 'Stackapella voices (SoulX-Singer)',
    'digital_source_type' => 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia',

    // A master must carry the worker's iXML marking (`<USER>ai_generated=true;`). Off until the marking worker is live,
    // so masters from the one before it aren't refused mid-switch, and `app:ai-marking:existing --check` finds nothing older
    // unmarked. Every AAC and video the server makes from AI audio is marked and read back whatever this says.
    'required' => (bool) env('AI_MARKING_REQUIRED', false),

    // The marking chunks after a master's audio: at most this many bytes.
    'max_chunk_bytes' => 65536,
];
