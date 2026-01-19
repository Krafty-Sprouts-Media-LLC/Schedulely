<?php
/**
 * Filename: class-author-manager.php
 * Author: Krafty Sprouts Media, LLC
 * Created: 06/10/2025
 * Last Modified: 05/01/2026
 * Description: Author Management System - Handles random author assignment with exclusions
 *
 * @package Schedulely
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Schedulely_Author_Manager
 * 
 * Handles random author assignment with exclusions.
 */
class Schedulely_Author_Manager {
    
    /**
     * Get random author ID for assignment
     * 
     * @return int|false Author ID or false if none available
     */
    public function get_random_author() {
        if (!$this->is_enabled()) {
            return false;
        }
        
        $eligible_authors = $this->get_eligible_authors();
        
        if (empty($eligible_authors)) {
            return false;
        }
        
        // Get random author
        $random_key = array_rand($eligible_authors);
        $author = $eligible_authors[$random_key];
        
        return $author->ID;
    }
    
    /**
     * Get all eligible authors (excluding excluded users)
     * 
     * @return array Array of WP_User objects
     */
    private function get_eligible_authors() {
        $excluded_authors = $this->get_excluded_authors();
        
        $args = [
            'capability' => 'edit_posts',
            'fields' => 'all',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ];
        
        // Exclude specific users if set
        if (!empty($excluded_authors)) {
            $args['exclude'] = $excluded_authors;
        }
        
        $users = get_users($args);
        
        return $users;
    }
    
    /**
     * Check if author assignment is enabled
     * 
     * @return bool
     */
    public function is_enabled() {
        return (bool) get_option('schedulely_randomize_authors', false);
    }
    
    /**
     * Get excluded author IDs
     * 
     * @return array Array of user IDs
     */
    public function get_excluded_authors() {
        $excluded = get_option('schedulely_excluded_authors', []);
        
        // Ensure it's an array
        if (!is_array($excluded)) {
            $excluded = [];
        }
        
        // Ensure all values are integers
        $excluded = array_map('intval', $excluded);
        
        return $excluded;
    }
    
    /**
     * Get preserved author IDs (authors whose posts should not be randomized)
     * 
     * @return array Array of user IDs
     * @since 1.2.7
     */
    public function get_preserved_authors() {
        $preserved = get_option('schedulely_preserved_authors', []);
        
        // Ensure it's an array
        if (!is_array($preserved)) {
            $preserved = [];
        }
        
        // Ensure all values are integers
        $preserved = array_map('intval', $preserved);
        
        return $preserved;
    }
    
    /**
     * Check if an author is preserved (their posts should not be randomized)
     * 
     * @param int $author_id Author ID to check
     * @return bool True if author is preserved
     * @since 1.2.7
     */
    public function is_author_preserved($author_id) {
        $preserved_authors = $this->get_preserved_authors();
        return in_array((int) $author_id, $preserved_authors, true);
    }
    
    /**
     * Get count of eligible authors
     * 
     * @return int Number of eligible authors
     */
    public function get_eligible_count() {
        return count($this->get_eligible_authors());
    }
    
    /**
     * Check if there are any eligible authors
     * 
     * @return bool True if at least one eligible author exists
     */
    public function has_eligible_authors() {
        return $this->get_eligible_count() > 0;
    }
}

