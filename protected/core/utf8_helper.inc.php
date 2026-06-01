<?php
/**
 * UTF-8 Helper
 * Ensures proper UTF-8 encoding throughout the system
 * Handles special characters like Ñ, á, é, í, ó, ú, ü
 */

/**
 * Initialize UTF-8 support for the current page
 * Call this at the top of every PHP page
 */
function initUTF8Support() {
    // Set default character encoding for multibyte functions
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    
    // Set HTTP header for UTF-8
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    
    // Set default charset for htmlspecialchars, etc.
    if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
        ini_set('default_charset', 'UTF-8');
    }
}

/**
 * Sanitize string for safe HTML output while preserving UTF-8
 * 
 * @param string $string The string to sanitize
 * @return string Sanitized string with UTF-8 characters preserved
 */
function utf8_htmlspecialchars($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Encode data to JSON with UTF-8 support
 * 
 * @param mixed $data Data to encode
 * @param int $options Additional JSON encoding options
 * @return string|false JSON string or false on failure
 */
function utf8_json_encode($data, $options = 0) {
    // Always include JSON_INVALID_UTF8_SUBSTITUTE to handle malformed UTF-8
    $options = $options | JSON_INVALID_UTF8_SUBSTITUTE;
    
    // Preserve Unicode characters (don't escape them)
    $options = $options | JSON_UNESCAPED_UNICODE;
    
    return json_encode($data, $options);
}

/**
 * Clean string of invalid UTF-8 characters
 * 
 * @param string $string String to clean
 * @return string Cleaned string
 */
function utf8_clean($string) {
    if (!is_string($string)) {
        return $string;
    }
    
    // Remove invalid UTF-8 sequences
    return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
}

/**
 * Recursively clean array/object of invalid UTF-8
 * 
 * @param mixed $data Data to clean
 * @return mixed Cleaned data
 */
function utf8_clean_recursive($data) {
    if (is_array($data)) {
        array_walk_recursive($data, function(&$item) {
            if (is_string($item)) {
                $item = utf8_clean($item);
            }
        });
    } elseif (is_string($data)) {
        $data = utf8_clean($data);
    }
    
    return $data;
}

/**
 * Check if a string is valid UTF-8
 * 
 * @param string $string String to check
 * @return bool True if valid UTF-8
 */
function utf8_is_valid($string) {
    return mb_check_encoding($string, 'UTF-8');
}



