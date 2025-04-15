<?php
/**
 * Template for Fullscreen Public Art Map
 */

get_header(); ?>

<style>
    html, body, #pam-map {
        margin: 0;
        padding: 0;
        height: 100%;
        width: 100%;
    }
    #pam-map {
        position: relative;
        z-index: 1;
    }
</style>

<div id="pam-map">Loading map...</div>

<script>
    // Placeholder
</script>

<?php get_footer(); ?>
