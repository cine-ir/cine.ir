<?php
/**
 * Rolino Credits Core Class
 * 
 * Handles all credit-related operations including adding, consuming, and tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Rolino_Credits {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        
        $this->table_name = $wpdb->prefix . 'rolino_credits';
    }
    
    /**
     * Add credit to user
     * 
     * @param int $user_id
     * @param int $plan_id Plan ID (0 for single buy)
     * @param int $credits Credit amount
     * @param int $duration Duration in days
     * @return bool
     */
    public function add_credit($user_id, $plan_id, $credits, $duration) {
        global $wpdb;
        
        $start_time = current_time('mysql');
        $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' + ' . $duration . ' days'));
        
        // Handle subscription logic
        if ($plan_id > 0) {
            // For subscription plans, check if user has active subscription
            $active_subscription = $this->get_active_subscription($user_id);
            
            if ($active_subscription) {
                // User has active subscription, add as reserve
                $this->add_reserve_subscription($user_id, $plan_id, $credits, $duration);
                
                // Trigger SMS scenario 5 (Reserve Activated)
                do_action('rolino_reserve_subscription_added', $user_id, $plan_id, $duration);
                
                return true;
            } else {
                // No active subscription, make this the main subscription
                $result = $wpdb->insert(
                    $this->table_name,
                    array(
                        'user_id' => $user_id,
                        'plan_id' => $plan_id,
                        'start_time' => $start_time,
                        'end_time' => $end_time,
                        'credit' => $credits
                    ),
                    array('%d', '%d', '%s', '%s', '%d')
                );
                
                if ($result !== false) {
                    // Trigger SMS scenario 4 (Main Activated)
                    do_action('rolino_main_subscription_activated', $user_id, $plan_id, $duration);
                    
                    return true;
                }
            }
        } else {
            // Single buy credit
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'plan_id' => 0,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'credit' => $credits
                ),
                array('%d', '%d', '%s', '%s', '%d')
            );
            
            return $result !== false;
        }
        
        return false;
    }
    
    /**
     * Add reserve subscription
     * 
     * @param int $user_id
     * @param int $plan_id
     * @param int $credits
     * @param int $duration
     * @return bool
     */
    private function add_reserve_subscription($user_id, $plan_id, $credits, $duration) {
        global $wpdb;
        
        $active_subscription = $this->get_active_subscription($user_id);
        
        if ($active_subscription) {
            // Calculate start time as the end of current subscription
            $start_time = $active_subscription->end_time;
            $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' + ' . $duration . ' days'));
            
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_id' => $user_id,
                    'plan_id' => $plan_id,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'credit' => $credits
                ),
                array('%d', '%d', '%s', '%s', '%d')
            );
            
            return $result !== false;
        }
        
        return false;
    }
    
    /**
     * Consume credit
     * 
     * @param int $user_id
     * @param int $amount Amount to consume
     * @return bool
     */
    public function consume_credit($user_id, $amount = 1) {
        global $wpdb;
        
        $available_credits = $this->get_user_credits($user_id);
        
        if ($available_credits < $amount) {
            return false;
        }
        
        // Get active credits sorted by end_time (oldest first for FIFO)
        $credits = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE user_id = %d 
                 AND end_time > NOW() 
                 AND credit > 0 
                 ORDER BY end_time ASC",
                $user_id
            )
        );
        
        $remaining_to_consume = $amount;
        
        foreach ($credits as $credit_record) {
            if ($remaining_to_consume <= 0) {
                break;
            }
            
            $available_in_record = $credit_record->credit;
            $to_consume_from_record = min($remaining_to_consume, $available_in_record);
            
            // Update the record
            $new_credit = $available_in_record - $to_consume_from_record;
            
            $wpdb->update(
                $this->table_name,
                array('credit' => $new_credit),
                array('id' => $credit_record->id),
                array('%d'),
                array('%d')
            );
            
            $remaining_to_consume -= $to_consume_from_record;
        }
        
        // Log the consumption
        do_action('rolino_credit_consumed', $user_id, $amount);
        
        return true;
    }
    
    /**
     * Get user's total available credits
     * 
     * @param int $user_id
     * @return int
     */
    public function get_user_credits($user_id) {
        global $wpdb;
        
        // Get only active subscription credits
        $active_credits = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT credit FROM {$this->table_name} 
                 WHERE user_id = %d 
                 AND plan_id > 0 
                 AND start_time <= NOW() 
                 AND end_time > NOW() 
                 ORDER BY end_time DESC 
                 LIMIT 1",
                $user_id
            )
        );
        
        return intval($active_credits);
    }
    
    /**
     * Get user's active subscription
     * 
     * @param int $user_id
     * @return object|null
     */
    public function get_active_subscription($user_id) {
        global $wpdb;
        
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, p.plan_name 
                 FROM {$this->table_name} c
                 LEFT JOIN {$wpdb->prefix}rolino_plans p ON c.plan_id = p.id
                 WHERE c.user_id = %d 
                 AND c.plan_id > 0 
                 AND c.start_time <= NOW() 
                 AND c.end_time > NOW() 
                 ORDER BY c.end_time DESC 
                 LIMIT 1",
                $user_id
            )
        );
        
        return $subscription;
    }
    
    /**
     * Get user's reserve subscription
     * 
     * @param int $user_id
     * @return object|null
     */
    public function get_reserve_subscription($user_id) {
        global $wpdb;
        
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, p.plan_name 
                 FROM {$this->table_name} c
                 LEFT JOIN {$wpdb->prefix}rolino_plans p ON c.plan_id = p.id
                 WHERE c.user_id = %d 
                 AND c.plan_id > 0 
                 AND c.start_time > NOW() 
                 ORDER BY c.start_time ASC 
                 LIMIT 1",
                $user_id
            )
        );
        
        return $subscription;
    }
    
    /**
     * Get remaining time for active subscription
     * 
     * @param int $user_id
     * @return int Days remaining
     */
    public function get_remaining_time($user_id) {
        $subscription = $this->get_active_subscription($user_id);
        
        if (!$subscription) {
            // Check if user has reserve subscription
            $reserve = $this->get_reserve_subscription($user_id);
            if ($reserve) {
                $end_time = strtotime($reserve->end_time);
                $current_time = current_time('timestamp');
                $remaining_seconds = $end_time - $current_time;
                return max(0, ceil($remaining_seconds / DAY_IN_SECONDS));
            }
            return 0;
        }
        
        $end_time = strtotime($subscription->end_time);
        $current_time = current_time('timestamp');
        $remaining_seconds = $end_time - $current_time;
        
        return max(0, ceil($remaining_seconds / DAY_IN_SECONDS));
    }
    
    /**
     * Get remaining time until reserve subscription starts
     * 
     * @param int $user_id
     * @return int Days until reserve starts
     */
    public function get_reserve_remaining_time($user_id) {
        $reserve = $this->get_reserve_subscription($user_id);
        
        if (!$reserve) {
            return 0;
        }
        
        $start_time = strtotime($reserve->start_time);
        $current_time = current_time('timestamp');
        $remaining_seconds = $start_time - $current_time;
        
        return max(0, ceil($remaining_seconds / DAY_IN_SECONDS));
    }
    
    /**
     * Get user's credit history
     * 
     * @param int $user_id
     * @param array $args Query arguments
     * @return array
     */
    public function get_user_credit_history($user_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'start_time',
            'order' => 'DESC',
            'include_expired' => true
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT c.*, p.plan_name 
                FROM {$this->table_name} c 
                LEFT JOIN {$wpdb->prefix}rolino_plans p ON c.plan_id = p.id 
                WHERE c.user_id = %d";
        
        if (!$args['include_expired']) {
            $sql .= " AND c.end_time > NOW()";
        }
        
        $sql .= $wpdb->prepare(" ORDER BY %s %s LIMIT %d OFFSET %d", 
            $args['orderby'], $args['order'], $args['limit'], $args['offset']);
        
        return $wpdb->get_results($wpdb->prepare($sql, $user_id));
    }
    
    /**
     * Get single buy credits for user
     * 
     * @param int $user_id
     * @return int
     */
    public function get_single_buy_credits($user_id) {
        global $wpdb;
        
        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(credit) FROM {$this->table_name} 
                 WHERE user_id = %d 
                 AND plan_id = 0 
                 AND end_time > NOW() 
                 AND credit > 0",
                $user_id
            )
        );
        
        return intval($total);
    }
    
    /**
     * Extend subscription time
     * 
     * @param int $user_id
     * @param int $days Days to add
     * @return bool
     */
    public function extend_subscription($user_id, $days) {
        global $wpdb;
        
        $reserve = $this->get_reserve_subscription($user_id);
        
        if ($reserve) {
            // Extend reserve subscription
            $new_end_time = date('Y-m-d H:i:s', strtotime($reserve->end_time . ' + ' . $days . ' days'));
            
            $result = $wpdb->update(
                $this->table_name,
                array('end_time' => $new_end_time),
                array('id' => $reserve->id),
                array('%s'),
                array('%d')
            );
        } else {
            // Extend active subscription
            $subscription = $this->get_active_subscription($user_id);
            
            if ($subscription) {
                $new_end_time = date('Y-m-d H:i:s', strtotime($subscription->end_time . ' + ' . $days . ' days'));
                
                $result = $wpdb->update(
                    $this->table_name,
                    array('end_time' => $new_end_time),
                    array('id' => $subscription->id),
                    array('%s'),
                    array('%d')
                );
            } else {
                return false;
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Check if user has active sessions limit
     * 
     * @param int $user_id
     * @return array
     */
    public function check_active_sessions($user_id) {
        $subscription = $this->get_active_subscription($user_id);
        
        if (!$subscription) {
            return array(
                'has_limit' => false,
                'current_sessions' => 0,
                'max_sessions' => 0
            );
        }
        
        // Get plan details
        $plans = new Rolino_Plans();
        $plan = $plans->get_plan($subscription->plan_id);
        
        if (!$plan) {
            return array(
                'has_limit' => false,
                'current_sessions' => 0,
                'max_sessions' => 0
            );
        }
        
        // Here you would implement session tracking logic
        // For now, returning the plan's session limit
        return array(
            'has_limit' => true,
            'current_sessions' => $this->get_user_active_sessions($user_id),
            'max_sessions' => $plan->active_sessions
        );
    }
    
    /**
     * Get user's current active sessions (placeholder)
     * 
     * @param int $user_id
     * @return int
     */
    public function get_user_active_sessions($user_id) {
        // This would integrate with your session tracking system
        // For now, returning 0
        return apply_filters('rolino_user_active_sessions', 0, $user_id);
    }
    
    /**
     * Cleanup expired credits
     * 
     * @return int Number of records cleaned
     */
    public function cleanup_expired_credits() {
        global $wpdb;
        
        $deleted = $wpdb->query(
            "DELETE FROM {$this->table_name} 
             WHERE end_time < NOW() 
             AND credit = 0"
        );
        
        return intval($deleted);
    }
    
    /**
     * Get credit statistics
     * 
     * @return array
     */
    public function get_credit_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total active subscriptions
        $stats['active_subscriptions'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->table_name} 
             WHERE plan_id > 0 
             AND start_time <= NOW() 
             AND end_time > NOW()"
        );
        
        // Total reserve subscriptions
        $stats['reserve_subscriptions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE plan_id > 0 
             AND start_time > NOW()"
        );
        
        // Total credits in circulation
        $stats['total_credits'] = $wpdb->get_var(
            "SELECT SUM(credit) FROM {$this->table_name} 
             WHERE end_time > NOW()"
        );
        
        // Single buy credits
        $stats['single_buy_credits'] = $wpdb->get_var(
            "SELECT SUM(credit) FROM {$this->table_name} 
             WHERE plan_id = 0 
             AND end_time > NOW()"
        );
        
        return $stats;
    }
    
    /**
     * Get user subscription info for SMS variables
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_subscription_info($user_id) {
        $info = array(
            'remaining_time' => $this->get_remaining_time($user_id),
            'reserve_remaining_time' => $this->get_reserve_remaining_time($user_id),
            'single_buy_number' => $this->get_single_buy_credits($user_id),
            'plan_name' => '',
            'reserve_plan_name' => ''
        );
        
        // Get active subscription plan name
        $subscription = $this->get_active_subscription($user_id);
        if ($subscription && $subscription->plan_id > 0) {
            $plans = new Rolino_Plans();
            $plan = $plans->get_plan($subscription->plan_id);
            if ($plan) {
                $info['plan_name'] = $plan->plan_name;
            }
        }
        
        // Get reserve subscription plan name
        $reserve = $this->get_reserve_subscription($user_id);
        if ($reserve && $reserve->plan_id > 0) {
            $plans = new Rolino_Plans();
            $plan = $plans->get_plan($reserve->plan_id);
            if ($plan) {
                $info['reserve_plan_name'] = $plan->plan_name;
            }
        }
        
        return $info;
    }
    
    /**
     * Add credits (alias for add_credit)
     * 
     * @param int $user_id
     * @param int $plan_id
     * @param int $credits
     * @param int $duration
     * @return bool
     */
    public function add_credits($user_id, $plan_id, $credits, $duration) {
        return $this->add_credit($user_id, $plan_id, $credits, $duration);
    }
    
    /**
     * Delete user credits
     * 
     * @param int $user_id
     * @return bool
     */
    public function delete_user_credits($user_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            array('user_id' => $user_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Extend user credits
     * 
     * @param int $user_id
     * @param int $days
     * @return bool
     */
    public function extend_user_credits($user_id, $days) {
        global $wpdb;
        
        $sql = "UPDATE {$this->table_name} 
                SET end_time = DATE_ADD(end_time, INTERVAL %d DAY) 
                WHERE user_id = %d AND end_time > NOW()";
        
        $result = $wpdb->query($wpdb->prepare($sql, $days, $user_id));
        
        return $result !== false;
    }
}