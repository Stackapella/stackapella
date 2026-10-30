<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'The :attribute field must be accepted.',
    'accepted_if' => 'The :attribute field must be accepted when :other is :value.',
    'active_url' => 'The :attribute field must be a valid URL.',
    'after' => 'The :attribute field must be a date after :date.',
    'after_or_equal' => 'The :attribute field must be a date after or equal to :date.',
    'alpha' => 'The :attribute field must only contain letters.',
    'alpha_dash' => 'The :attribute field must only contain letters, numbers, dashes, and underscores.',
    'alpha_num' => 'The :attribute field must only contain letters and numbers.',
    'any_of' => 'The :attribute field is invalid.',
    'array' => 'The :attribute field must be an array.',
    'array_keys' => 'The :attribute field must only contain the following keys: :values.',
    'ascii' => 'The :attribute field must only contain single-byte alphanumeric characters and symbols.',
    'base64' => 'The :attribute field must be a valid Base64 string.',
    'before' => 'The :attribute field must be a date before :date.',
    'before_or_equal' => 'The :attribute field must be a date before or equal to :date.',
    'between' => [
        'array' => 'The :attribute field must have between :min and :max items.',
        'file' => 'The :attribute field must be between :min and :max kilobytes.',
        'numeric' => 'The :attribute field must be between :min and :max.',
        'string' => 'The :attribute field must be between :min and :max characters.',
    ],
    'boolean' => 'The :attribute field must be true or false.',
    'can' => 'The :attribute field contains an unauthorized value.',
    'confirmed' => 'The :attribute field confirmation does not match.',
    'contains' => 'The :attribute field is missing a required value.',
    'current_password' => 'The password is incorrect.',
    'date' => 'The :attribute field must be a valid date.',
    'date_equals' => 'The :attribute field must be a date equal to :date.',
    'date_format' => 'The :attribute field must match the format :format.',
    'decimal' => 'The :attribute field must have :decimal decimal places.',
    'declined' => 'The :attribute field must be declined.',
    'declined_if' => 'The :attribute field must be declined when :other is :value.',
    'different' => 'The :attribute field and :other must be different.',
    'digits' => 'The :attribute field must be :digits digits.',
    'digits_between' => 'The :attribute field must be between :min and :max digits.',
    'dimensions' => 'The :attribute field has invalid image dimensions.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'doesnt_contain' => 'The :attribute field must not contain any of the following: :values.',
    'doesnt_end_with' => 'The :attribute field must not end with one of the following: :values.',
    'doesnt_start_with' => 'The :attribute field must not start with one of the following: :values.',
    'email' => 'The :attribute field must be a valid email address.',
    'encoding' => 'The :attribute field must be encoded in :encoding.',
    'ends_with' => 'The :attribute field must end with one of the following: :values.',
    'enum' => 'The selected :attribute is invalid.',
    'exists' => 'The selected :attribute is invalid.',
    'extensions' => 'The :attribute field must have one of the following extensions: :values.',
    'file' => 'The :attribute field must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'array' => 'The :attribute field must have more than :value items.',
        'file' => 'The :attribute field must be greater than :value kilobytes.',
        'numeric' => 'The :attribute field must be greater than :value.',
        'string' => 'The :attribute field must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'The :attribute field must have :value items or more.',
        'file' => 'The :attribute field must be greater than or equal to :value kilobytes.',
        'numeric' => 'The :attribute field must be greater than or equal to :value.',
        'string' => 'The :attribute field must be greater than or equal to :value characters.',
    ],
    'hex_color' => 'The :attribute field must be a valid hexadecimal color.',
    'image' => 'The :attribute field must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'in_array' => 'The :attribute field must exist in :other.',
    'in_array_keys' => 'The :attribute field must contain at least one of the following keys: :values.',
    'integer' => 'The :attribute field must be an integer.',
    'ip' => 'The :attribute field must be a valid IP address.',
    'ipv4' => 'The :attribute field must be a valid IPv4 address.',
    'ipv6' => 'The :attribute field must be a valid IPv6 address.',
    'json' => 'The :attribute field must be a valid JSON string.',
    'list' => 'The :attribute field must be a list.',
    'lowercase' => 'The :attribute field must be lowercase.',
    'lt' => [
        'array' => 'The :attribute field must have less than :value items.',
        'file' => 'The :attribute field must be less than :value kilobytes.',
        'numeric' => 'The :attribute field must be less than :value.',
        'string' => 'The :attribute field must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'The :attribute field must not have more than :value items.',
        'file' => 'The :attribute field must be less than or equal to :value kilobytes.',
        'numeric' => 'The :attribute field must be less than or equal to :value.',
        'string' => 'The :attribute field must be less than or equal to :value characters.',
    ],
    'mac_address' => 'The :attribute field must be a valid MAC address.',
    'max' => [
        'array' => 'The :attribute field must not have more than :max items.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'max_digits' => 'The :attribute field must not have more than :max digits.',
    'mimes' => 'The :attribute field must be a file of type: :values.',
    'mimetypes' => 'The :attribute field must be a file of type: :values.',
    'min' => [
        'array' => 'The :attribute field must have at least :min items.',
        'file' => 'The :attribute field must be at least :min kilobytes.',
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'min_digits' => 'The :attribute field must have at least :min digits.',
    'missing' => 'The :attribute field must be missing.',
    'missing_if' => 'The :attribute field must be missing when :other is :value.',
    'missing_unless' => 'The :attribute field must be missing unless :other is :value.',
    'missing_with' => 'The :attribute field must be missing when :values is present.',
    'missing_with_all' => 'The :attribute field must be missing when :values are present.',
    'multiple_of' => 'The :attribute field must be a multiple of :value.',
    'not_in' => 'The selected :attribute is invalid.',
    'not_regex' => 'The :attribute field format is invalid.',
    'numeric' => 'The :attribute field must be a number.',
    'password' => [
        'letters' => 'The :attribute field must contain at least one letter.',
        'mixed' => 'The :attribute field must contain at least one uppercase and one lowercase letter.',
        'numbers' => 'The :attribute field must contain at least one number.',
        'symbols' => 'The :attribute field must contain at least one symbol.',
        'uncompromised' => 'The given :attribute has appeared in a data leak. Please choose a different :attribute.',
    ],
    'present' => 'The :attribute field must be present.',
    'present_if' => 'The :attribute field must be present when :other is :value.',
    'present_unless' => 'The :attribute field must be present unless :other is :value.',
    'present_with' => 'The :attribute field must be present when :values is present.',
    'present_with_all' => 'The :attribute field must be present when :values are present.',
    'prohibited' => 'The :attribute field is prohibited.',
    'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
    'prohibited_if_accepted' => 'The :attribute field is prohibited when :other is accepted.',
    'prohibited_if_declined' => 'The :attribute field is prohibited when :other is declined.',
    'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
    'prohibits' => 'The :attribute field prohibits :other from being present.',
    'regex' => 'The :attribute field format is invalid.',
    'required' => 'The :attribute field is required.',
    'required_array_keys' => 'The :attribute field must contain entries for: :values.',
    'required_if' => 'The :attribute field is required when :other is :value.',
    'required_if_accepted' => 'The :attribute field is required when :other is accepted.',
    'required_if_declined' => 'The :attribute field is required when :other is declined.',
    'required_unless' => 'The :attribute field is required unless :other is in :values.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'required_with_all' => 'The :attribute field is required when :values are present.',
    'required_without' => 'The :attribute field is required when :values is not present.',
    'required_without_all' => 'The :attribute field is required when none of :values are present.',
    'same' => 'The :attribute field must match :other.',
    'size' => [
        'array' => 'The :attribute field must contain :size items.',
        'file' => 'The :attribute field must be :size kilobytes.',
        'numeric' => 'The :attribute field must be :size.',
        'string' => 'The :attribute field must be :size characters.',
    ],
    'starts_with' => 'The :attribute field must start with one of the following: :values.',
    'string' => 'The :attribute field must be a string.',
    'timezone' => 'The :attribute field must be a valid timezone.',
    'unique' => 'The :attribute has already been taken.',
    'uploaded' => 'The :attribute failed to upload.',
    'uppercase' => 'The :attribute field must be uppercase.',
    'url' => 'The :attribute field must be a valid URL.',
    'ulid' => 'The :attribute field must be a valid ULID.',
    'uuid' => 'The :attribute field must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

    /*
    | Stackapella's own rules.
    */

    'no_links' => 'Links aren\'t allowed here.',
    'one_line' => 'Keep it to one line.',
    'handle_for_profile' => 'Choose a handle first: it\'s your profile\'s address.',
    'rights_confirmed' => 'Confirm you have the right to share this song, and that its parts may be used as guide voices.',
    'template_size' => 'A template may be at most :kb KB.',
    'template_id' => 'The template\'s id must be the one in its address.',
    'template_part' => 'That part isn\'t in this song.',
    'featured_fill' => 'Feature a public harmony sung for this one.',
    'choir_take' => 'Join with a take of this song, on the part you sing.',
    'choir_quality' => 'That take clipped or picked up the other parts: record it again in headphones.',
    'choir_score' => 'Almost! Your take needs :min% on pitch to join: practise and try again.',
    'choir_score_mismatch' => 'That score isn\'t this take\'s.',
    'request_title' => 'Say which song: a title with some letters in it.',
    'request_merge' => 'Merge it into another request that is still open.',
    'request_template' => 'Ready needs one of our songs to learn.',
    'open_parts' => 'Open parts of the song sung in it, leaving at least one part sung.',
    'idea_voicing' => 'This tune has no version for that many singers.',
    'template_declaration' => 'A template in the library is saved with who wrote the song, each time.',
    'group_member' => 'That person isn\'t in this group.',
    'group_one_part' => 'Each person sings one part of a song.',
    'coaching_adult' => 'Coaching is for people 18 and over.',
    'review_take' => 'Choose one of your own recordings, once it has finished uploading.',
    'review_length' => 'This is for up to :seconds seconds.',
    'review_summary' => 'Write the summary before you deliver.',
    'refund_amount' => 'Refund between 1 and :max cents.',
    'es_address' => 'Use your Event Schedule\'s own https address, like https://events.example.org.',
    'es_unreachable' => 'Event Schedule didn\'t answer at that address.',
    'es_key' => 'Event Schedule didn\'t accept that key.',
    'group_song' => 'That song isn\'t one of the group\'s.',
    'take_too_long' => 'A take can be at most 10 minutes long.',
    'take_bytes' => 'The file\'s size doesn\'t match a 16-bit mono WAV of that many frames.',
    'take_scores' => 'The quality check\'s results are too large.',
    'take_key' => 'A guide is sung in the song\'s original key, :key.',
    'clip_assigned' => 'This singer already has that clip.',
    'take_not_ready' => 'Send it once the sound check says it\'s ready.',
    'part_missing' => ':template has no part called :part.',
    'already_assigned' => ':name is already asked to record this part.',
    'trims_too_long' => 'The trims and the nudge leave less than a second of the take.',
    'part_twice' => 'This part is in the list twice.',
    'part_project' => 'A part can\'t move to another song.',
    'part_take' => 'That take isn\'t one of this part\'s.',
    'editable_limit' => 'Choose at most :max songs.',
    'editable_unknown' => 'That song isn\'t one of yours, or it\'s in Recently deleted.',
    'editable_plus_song' => 'A song started from a Plus song stays read-only until you have Plus.',
    'reminder_time' => 'The time must be like 18:30, on a quarter hour.',
    'push_endpoint' => 'The endpoint must be a push service\'s address.',
    'score_id' => 'That score was already sent with other numbers.',
    'take_id' => 'That take was already sent with other numbers.',
    'take_size' => 'The size doesn\'t match a 16-bit mono WAV of that many frames.',
    'harmony_id' => 'That share was already sent with other numbers.',
    'mudeo_csv' => 'The file needs a header row with an email column.',
    'mudeo_ids' => 'Some of these were already sent or aren\'t on the list. Get the next batch again.',
    'harmony_size' => 'The size doesn\'t match a 16-bit WAV of that many frames and channels.',
    'harmony_length' => 'A shared harmony is at most :minutes minutes.',
    'track_peaks' => 'Each part\'s outline must be base64, one byte for every 50 ms of the mix.',
    'video_section' => 'A video is :min to :max seconds of the harmony.',
    'settings_size' => 'The settings may be at most :kb KB.',
    'cursor' => 'That cursor isn\'t valid.',
    'common_password' => 'Choose a password that\'s harder to guess: this one is on the list of the most common.',
    'handle' => [
        'invalid' => 'Use 3 to 20 lower-case letters, numbers or underscores, starting with a letter, and keep it kind.',
        'reserved' => 'That handle is kept for Stackapella. Try another.',
        'taken' => 'That handle is taken.',
    ],
    'adult' => 'You need to be 18 or over to sing in the beta.',

    'cursor' => 'That page of the list has moved on. Start again from the first page.',

    'block_value' => [
        'email' => 'That isn\'t an email address.',
        'email_domain' => 'That isn\'t a domain (for example spam.example).',
        'ip' => 'That isn\'t an IP address.',
        'ip_range' => 'That isn\'t an IP range (for example 203.0.113.0/24, no wider than a /8).',
        'card_fingerprint' => 'That isn\'t a card fingerprint from Stripe.',
    ],

    // Game mode's boards.
    'initials_blocked' => 'Choose other letters.',
    'initials_required' => 'Choose your initials first.',
    'daily_date' => 'One of the last seven days.',
    'ghost_pitches' => 'At most 120 seconds.',

];
