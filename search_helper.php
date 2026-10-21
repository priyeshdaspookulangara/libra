<?php
// search_helper.php - Manglish / Transliteration & Phonetic Normalization Engine

/**
 * Normalizes Manglish / Latin transliteration string for phonetic matching.
 * Converts common Malayalam-English transliteration variations to a canonical phonetic key.
 */
function normalizeManglish($text) {
    if (empty($text)) {
        return '';
    }

    $text = strtolower(trim($text));

    // Remove punctuation
    $text = preg_replace('/[^\w\s]/u', '', $text);

    // Common Manglish substitutions
    $replacements = [
        'zh' => 's',
        'sh' => 's',
        'ch' => 's',
        'th' => 't',
        'dh' => 't',
        'bh' => 'b',
        'ph' => 'p',
        'gh' => 'g',
        'kh' => 'k',
        'oo' => 'u',
        'ee' => 'i',
        'ea' => 'i',
        'ou' => 'u',
        'aa' => 'a',
        'ai' => 'i',
        'ei' => 'i',
        'au' => 'u',
        'nj' => 'n',
        'ng' => 'n',
    ];

    $text = strtr($text, $replacements);

    // Remove duplicate consecutive letters
    $text = preg_replace('/(.)\1+/', '$1', $text);

    return $text;
}

/**
 * Removes spaces and non-alphanumeric chars for contiguous substring checking
 */
function compactString($text) {
    return preg_replace('/[^a-z0-9]/', '', strtolower(normalizeManglish($text)));
}

/**
 * Checks if two text strings match phonetically or via Manglish normalization
 */
function isPhoneticMatch($query, $target) {
    if (empty($query) || empty($target)) {
        return false;
    }

    $normQuery = normalizeManglish($query);
    $normTarget = normalizeManglish($target);

    // Substring match on normalized string
    if (strpos($normTarget, $normQuery) !== false) {
        return true;
    }

    // Compact match (ignoring spaces e.g. "aadu jeevitham" vs "aadujeevitham")
    $compactQ = compactString($query);
    $compactT = compactString($target);
    if (!empty($compactQ) && strpos($compactT, $compactQ) !== false) {
        return true;
    }

    // Word-by-word phonetic comparison
    if (strlen($normQuery) >= 3) {
        $qWords = array_filter(explode(' ', $normQuery));
        $tWords = array_filter(explode(' ', $normTarget));

        foreach ($qWords as $qw) {
            foreach ($tWords as $tw) {
                if (soundex($qw) === soundex($tw) && soundex($qw) !== '0000') {
                    return true;
                }
                if (metaphone($qw) === metaphone($tw) && !empty(metaphone($qw))) {
                    return true;
                }
                if (levenshtein($qw, $tw) <= 2 && strlen($qw) >= 3) {
                    return true;
                }
            }
        }
    }

    return false;
}
