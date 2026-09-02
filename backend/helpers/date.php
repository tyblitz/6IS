<?php
/**
 * Centralized Date & Time Formatting Helpers for 6IS Backend
 * Strictly adheres to Workspace Customization Rules in .agents/AGENTS.md
 */

if (!function_exists('format_date')) {
    /**
     * 1. Date Only Format: "DD MMM YYYY" (e.g. "27 Aug 2026")
     */
    function format_date($dateInput): string {
        if (empty($dateInput)) {
            return 'N/A';
        }
        $timestamp = is_numeric($dateInput) ? (int)$dateInput : strtotime($dateInput);
        if (!$timestamp) {
            return (string)$dateInput;
        }
        return date('d M Y', $timestamp);
    }
}

if (!function_exists('format_time')) {
    /**
     * 2. Time Only Format: Military time with trailing 'H' ("HHmmH", e.g. "1400H", "0830H")
     */
    function format_time($timeInput): string {
        if (empty($timeInput)) {
            return '';
        }
        $timestamp = is_numeric($timeInput) ? (int)$timeInput : strtotime($timeInput);
        if (!$timestamp) {
            // Handle plain HH:MM or HH:MM:SS
            if (preg_match('/^(\d{1,2}):(\d{2})/', (string)$timeInput, $matches)) {
                return str_pad($matches[1], 2, '0', STR_PAD_LEFT) . $matches[2] . 'H';
            }
            return (string)$timeInput;
        }
        return date('Hi\H', $timestamp);
    }
}

if (!function_exists('format_datetime')) {
    /**
     * 3. Date and Time Combined Format: "DD HHmmH MMM YYYY" (e.g. "27 1400H Aug 2026")
     */
    function format_datetime($dateTimeInput): string {
        if (empty($dateTimeInput)) {
            return 'N/A';
        }
        $timestamp = is_numeric($dateTimeInput) ? (int)$dateTimeInput : strtotime($dateTimeInput);
        if (!$timestamp) {
            return (string)$dateTimeInput;
        }
        return date('d Hi\H M Y', $timestamp);
    }
}

if (!function_exists('format_datetime_day')) {
    /**
     * 4. Date, Time, and Day of Week Format: "DD HHmmH MMM YYYY dddd" (e.g. "27 1400H Aug 2026 Friday")
     */
    function format_datetime_day($dateTimeInput): string {
        if (empty($dateTimeInput)) {
            return 'N/A';
        }
        $timestamp = is_numeric($dateTimeInput) ? (int)$dateTimeInput : strtotime($dateTimeInput);
        if (!$timestamp) {
            return (string)$dateTimeInput;
        }
        return date('d Hi\H M Y l', $timestamp);
    }
}
