<?php
/**
 * Admin page template for Feedback Manager
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap feedback-manager-admin">
    <h1 class="wp-heading-inline"><?php _e('Feedback Manager', 'feedback-manager'); ?></h1>
    
    <a href="<?php echo esc_url(wp_nonce_url(admin_url('tools.php?action=export_feedback_csv'), 'export_feedback_csv', 'nonce')); ?>" class="page-title-action">
        <?php _e('Export to CSV', 'feedback-manager'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Stats Cards -->
     <div class="feedback-stats-wrapper">
        <div class="feedback-stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo esc_html($total_items); ?></div>
                <div class="stat-label"><?php _e('Total Feedback', 'feedback-manager'); ?></div>
            </div>
            <div class="stat-card">
                <?php
                $today_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(created_at) = CURDATE()");
                ?>
                <div class="stat-value"><?php echo esc_html($today_count); ?></div>
                <div class="stat-label"><?php _e('Today', 'feedback-manager'); ?></div>
            </div>
            <div class="stat-card">
                <?php
                $week_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                ?>
                <div class="stat-value"><?php echo esc_html($week_count); ?></div>
                <div class="stat-label"><?php _e('This Week', 'feedback-manager'); ?></div>
            </div>
        </div>
     </div>
    
    <!-- Search and Filter Form -->
    <form method="get" class="feedback-search-form">
        <input type="hidden" name="page" value="feedback-manager">
        
        <div class="search-filter-wrapper">
            <div class="search-box-wrapper">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search by name, email, or message...', 'feedback-manager'); ?>" class="search-input">
            </div>
            
            <div class="filter-box-wrapper">
                <select name="date_filter" class="filter-select">
                    <option value=""><?php _e('All Time', 'feedback-manager'); ?></option>
                    <option value="today" <?php selected($date_filter, 'today'); ?>><?php _e('Today', 'feedback-manager'); ?></option>
                    <option value="yesterday" <?php selected($date_filter, 'yesterday'); ?>><?php _e('Yesterday', 'feedback-manager'); ?></option>
                    <option value="week" <?php selected($date_filter, 'week'); ?>><?php _e('Last 7 Days', 'feedback-manager'); ?></option>
                    <option value="month" <?php selected($date_filter, 'month'); ?>><?php _e('Last 30 Days', 'feedback-manager'); ?></option>
                    <option value="year" <?php selected($date_filter, 'year'); ?>><?php _e('Last Year', 'feedback-manager'); ?></option>
                </select>
                
                <select name="orderby" class="filter-select">
                    <option value="created_at" <?php selected($order_by, 'created_at'); ?>><?php _e('Sort by Date', 'feedback-manager'); ?></option>
                    <option value="name" <?php selected($order_by, 'name'); ?>><?php _e('Sort by Name', 'feedback-manager'); ?></option>
                    <option value="email" <?php selected($order_by, 'email'); ?>><?php _e('Sort by Email', 'feedback-manager'); ?></option>
                </select>
                
                <select name="order" class="filter-select">
                    <option value="DESC" <?php selected($order, 'DESC'); ?>><?php _e('Descending', 'feedback-manager'); ?></option>
                    <option value="ASC" <?php selected($order, 'ASC'); ?>><?php _e('Ascending', 'feedback-manager'); ?></option>
                </select>
            </div>
            
            <div class="button-wrapper">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php _e('Apply Filters', 'feedback-manager'); ?>
                </button>
                <?php if (!empty($search) || !empty($date_filter) || $order_by !== 'created_at' || $order !== 'DESC'): ?>
                    <a href="<?php echo esc_url(admin_url('tools.php?page=feedback-manager')); ?>" class="button">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e('Reset', 'feedback-manager'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($search) || !empty($date_filter)): ?>
        <div class="active-filters">
            <strong><?php _e('Active Filters:', 'feedback-manager'); ?></strong>
            <?php if (!empty($search)): ?>
                <span class="filter-tag">
                    <?php printf(__('Search: "%s"', 'feedback-manager'), esc_html($search)); ?>
                    <a href="<?php echo esc_url(add_query_arg(array('page' => 'feedback-manager', 'date_filter' => $date_filter), admin_url('tools.php'))); ?>" class="remove-filter">&times;</a>
                </span>
            <?php endif; ?>
            <?php if (!empty($date_filter)): ?>
                <span class="filter-tag">
                    <?php 
                    $date_labels = array(
                        'today' => __('Today', 'feedback-manager'),
                        'yesterday' => __('Yesterday', 'feedback-manager'),
                        'week' => __('Last 7 Days', 'feedback-manager'),
                        'month' => __('Last 30 Days', 'feedback-manager'),
                        'year' => __('Last Year', 'feedback-manager')
                    );
                    printf(__('Date: %s', 'feedback-manager'), esc_html($date_labels[$date_filter]));
                    ?>
                    <a href="<?php echo esc_url(add_query_arg(array('page' => 'feedback-manager', 's' => $search), admin_url('tools.php'))); ?>" class="remove-filter">&times;</a>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>
    
    <?php if (empty($feedbacks)): ?>
        <div class="feedback-empty-state">
            <div class="empty-icon">📝</div>
            <h2><?php _e('No feedback yet', 'feedback-manager'); ?></h2>
            <p><?php _e('Feedback submissions will appear here.', 'feedback-manager'); ?></p>
            <p>
                <?php 
                printf(
                    __('Use the shortcode %s to display the feedback form on any page.', 'feedback-manager'),
                    '<code>[feedback_form]</code>'
                ); 
                ?>
            </p>
        </div>
    <?php else: ?>
        
        <!-- Bulk Actions Form -->
        <form method="post" id="bulk-action-form">
            <?php wp_nonce_field('bulk_delete_feedback'); ?>
            <input type="hidden" name="action" value="bulk_delete">
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action2" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'feedback-manager'); ?></option>
                        <option value="bulk_delete"><?php _e('Delete', 'feedback-manager'); ?></option>
                    </select>
                    <button type="submit" class="button action" id="doaction"><?php _e('Apply', 'feedback-manager'); ?></button>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(_n('%s item', '%s items', $total_items, 'feedback-manager'), number_format_i18n($total_items)); ?>
                    </span>
                    <span class="pagination-links">
                        <?php
                        $base_url = add_query_arg(array(
                            'page' => 'feedback-manager', 
                            's' => $search,
                            'date_filter' => $date_filter,
                            'orderby' => $order_by,
                            'order' => $order
                        ), admin_url('tools.php'));
                        
                        if ($current_page > 1) {
                            echo '<a class="button" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">&laquo;</a> ';
                            echo '<a class="button" href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '">&lsaquo;</a> ';
                        } else {
                            echo '<span class="button disabled">&laquo;</span> ';
                            echo '<span class="button disabled">&lsaquo;</span> ';
                        }
                        
                        echo '<span class="paging-input">';
                        echo '<span class="tablenav-paging-text">';
                        printf(__('%1$s of %2$s', 'feedback-manager'), 
                            '<span class="current-page">' . number_format_i18n($current_page) . '</span>',
                            '<span class="total-pages">' . number_format_i18n($total_pages) . '</span>'
                        );
                        echo '</span></span> ';
                        
                        if ($current_page < $total_pages) {
                            echo '<a class="button" href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '">&rsaquo;</a> ';
                            echo '<a class="button" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '">&raquo;</a>';
                        } else {
                            echo '<span class="button disabled">&rsaquo;</span> ';
                            echo '<span class="button disabled">&raquo;</span>';
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Feedback Table -->
            <table class="wp-list-table widefat fixed striped feedback-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-1">
                        </td>
                        <th class="manage-column column-name sortable <?php echo ($order_by === 'name') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array(
                                'page' => 'feedback-manager',
                                's' => $search,
                                'date_filter' => $date_filter,
                                'orderby' => 'name',
                                'order' => ($order_by === 'name' && $order === 'ASC') ? 'DESC' : 'ASC'
                            ), admin_url('tools.php'))); ?>">
                                <span><?php _e('Name', 'feedback-manager'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-email sortable <?php echo ($order_by === 'email') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array(
                                'page' => 'feedback-manager',
                                's' => $search,
                                'date_filter' => $date_filter,
                                'orderby' => 'email',
                                'order' => ($order_by === 'email' && $order === 'ASC') ? 'DESC' : 'ASC'
                            ), admin_url('tools.php'))); ?>">
                                <span><?php _e('Email', 'feedback-manager'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-message"><?php _e('Message', 'feedback-manager'); ?></th>
                        <th class="manage-column column-date sortable <?php echo ($order_by === 'created_at') ? 'sorted ' . strtolower($order) : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array(
                                'page' => 'feedback-manager',
                                's' => $search,
                                'date_filter' => $date_filter,
                                'orderby' => 'created_at',
                                'order' => ($order_by === 'created_at' && $order === 'ASC') ? 'DESC' : 'ASC'
                            ), admin_url('tools.php'))); ?>">
                                <span><?php _e('Date', 'feedback-manager'); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-actions"><?php _e('Actions', 'feedback-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedbacks as $feedback): ?>
                    <tr data-id="<?php echo esc_attr($feedback->id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="feedback_ids[]" value="<?php echo esc_attr($feedback->id); ?>">
                        </th>
                        <td class="column-name">
                            <strong><?php echo esc_html($feedback->name); ?></strong>
                        </td>
                        <td class="column-email">
                            <a href="mailto:<?php echo esc_attr($feedback->email); ?>">
                                <?php echo esc_html($feedback->email); ?>
                            </a>
                        </td>
                        <td class="column-message">
                            <div class="message-preview">
                                <?php echo esc_html(wp_trim_words($feedback->message, 20)); ?>
                            </div>
                            <?php if (strlen($feedback->message) > 100): ?>
                            <button type="button" class="button-link view-full-message" data-message="<?php echo esc_attr($feedback->message); ?>">
                                <?php _e('View Full Message', 'feedback-manager'); ?>
                            </button>
                            <?php endif; ?>
                            <div class="feedback-meta">
                                <span class="meta-item" title="<?php _e('IP Address', 'feedback-manager'); ?>">
                                    🌐 <?php echo esc_html($feedback->ip_address); ?>
                                </span>
                            </div>
                        </td>
                        <td class="column-date">
                            <?php 
                            $date = new DateTime($feedback->created_at);
                            echo '<strong>' . esc_html($date->format('M d, Y')) . '</strong><br>';
                            echo '<span class="time">' . esc_html($date->format('g:i A')) . '</span>';
                            ?>
                        </td>
                        <td class="column-actions">
                            <button type="button" class="button delete-feedback" data-id="<?php echo esc_attr($feedback->id); ?>">
                                <?php _e('Delete', 'feedback-manager'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="select-all-2">
                        </td>
                        <th class="manage-column column-name"><?php _e('Name', 'feedback-manager'); ?></th>
                        <th class="manage-column column-email"><?php _e('Email', 'feedback-manager'); ?></th>
                        <th class="manage-column column-message"><?php _e('Message', 'feedback-manager'); ?></th>
                        <th class="manage-column column-date"><?php _e('Date', 'feedback-manager'); ?></th>
                        <th class="manage-column column-actions"><?php _e('Actions', 'feedback-manager'); ?></th>
                    </tr>
                </tfoot>
            </table>
            
            <!-- Bottom Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(_n('%s item', '%s items', $total_items, 'feedback-manager'), number_format_i18n($total_items)); ?>
                    </span>
                    <span class="pagination-links">
                        <?php
                        if ($current_page > 1) {
                            echo '<a class="button" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">&laquo;</a> ';
                            echo '<a class="button" href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '">&lsaquo;</a> ';
                        } else {
                            echo '<span class="button disabled">&laquo;</span> ';
                            echo '<span class="button disabled">&lsaquo;</span> ';
                        }
                        
                        echo '<span class="paging-input">';
                        echo '<span class="tablenav-paging-text">';
                        printf(__('%1$s of %2$s', 'feedback-manager'), 
                            '<span class="current-page">' . number_format_i18n($current_page) . '</span>',
                            '<span class="total-pages">' . number_format_i18n($total_pages) . '</span>'
                        );
                        echo '</span></span> ';
                        
                        if ($current_page < $total_pages) {
                            echo '<a class="button" href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '">&rsaquo;</a> ';
                            echo '<a class="button" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '">&raquo;</a>';
                        } else {
                            echo '<span class="button disabled">&rsaquo;</span> ';
                            echo '<span class="button disabled">&raquo;</span>';
                        }
                        ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<!-- Modal for Full Message -->
<div id="full-message-modal" class="feedback-modal" style="display:none;">
    <div class="feedback-modal-overlay"></div>
    <div class="feedback-modal-content">
        <div class="feedback-modal-header">
            <h2><?php _e('Full Message', 'feedback-manager'); ?></h2>
            <button type="button" class="feedback-modal-close">&times;</button>
        </div>
        <div class="feedback-modal-body">
            <p id="full-message-text"></p>
        </div>
    </div>
</div>