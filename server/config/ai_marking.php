<?php

declare(strict_types=1);

/*
 * Machine-readable marking of AI-made audio (EU AI Act Article 50, in force from 2 August 2026; 2026-11-23): one text
 * for every file holding an AI voice, the worker's masters and everything the server and the app make from them.
 * Agreed with voices, whose worker writes it into each master (WAV: LIST/INFO ICMT and an iXML chunk).
 */
return [
    'text' => 'AI-generated singing voice (synthetic). Made by Stackapella with the SoulX-Singer model. Marked under EU AI Act Article 50.',
    'generator' => 'Stackapella voices (SoulX-Singer)',
    'digital_source_type' => 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia',

    // A master must carry the worker's iXML marking (`<USER>ai_generated=true;`). Off until the marking worker is live,
    // so masters from the one before it aren't refused mid-switch.
    'required' => (bool) env('AI_MARKING_REQUIRED', false),

    // The marking chunks after a master's audio: at most this many bytes.
    'max_chunk_bytes' => 65536,
];
