<?php
/**
 * Credits Simple Template
 * 
 * Template for displaying simple credits info
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<span class="rolino-credits">
    <?php echo number_format($total_credits); ?> <?php _e('اعتبار', 'rolino'); ?>
</span>

<style>
.rolino-credits {
    font-weight: bold;
    color: #28a745;
}
</style>