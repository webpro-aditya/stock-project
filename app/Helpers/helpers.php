<?php


if (!function_exists('getDateFormatted')) {
    /**
     * Get the database date format from config.
     * This date format will be used to filter data from database
     *
     * @return date
     */
    function getDateFormatted($date, $format = 'Y-m-Y')
    {
        $dateFormatted = date($format, strtotime($date));
        return $dateFormatted;
    }
}

if (!function_exists('encryptPassword')) {
    /**
     * Encrypt the password using AES-256-ECB encryption method and base64 encoding.
     *
     */
    function encryptPassword($plainText, $base64Key)
    {
        $key = base64_decode($base64Key);
        $encrypted = openssl_encrypt($plainText, 'aes-256-ecb', $key, OPENSSL_RAW_DATA);
        return base64_encode($encrypted);
    }
}

