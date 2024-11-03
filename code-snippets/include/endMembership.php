public function terminate_free_membership($membership_plan, $member_details)
{
    global $wpdb;

    // Check if the plan name is valid and not "Free Trial"
    if (!empty($membership_plan->name) && $membership_plan->name !== 'Free Trial') {
        
        $user_active_memberships = wc_memberships_get_user_active_memberships($member_details['user_id']);
        
        if (!empty($user_active_memberships)) {
            foreach ($user_active_memberships as $membership) {
                
                // Skip if membership name matches the current plan
                if ($membership->plan->get_name() !== $membership_plan->name) {
                    
                    // Determine end date based on conditions
                    $current_month = intval(date('m', current_time('timestamp', true)));
                    $is_free_trial = ($membership->plan->get_name() === 'Free Trial');
                    $end_date = $is_free_trial || ($current_month < 9) ? current_time('timestamp', true) - DAY_IN_SECONDS : strtotime('December 30 ' . date('Y') . ' 23:59:59');
                    
                    // Expire current membership
                    $membership->set_end_date(date('Y-m-d H:i:s', $end_date));
                    $wpdb->update(
                        $wpdb->prefix . 'posts',
                        ['post_status' => 'wcm-expired'],
                        ['ID' => $membership->get_id()]
                    );

                    // Update start and end dates for new plan
                    $new_plan = wc_memberships_get_user_membership($member_details['user_id'], $membership_plan->id);
                    $new_end_date = $is_free_trial ? date('Y') . '-12-30 23:59:59' : date('Y', strtotime('+1 year')) . '-12-30 23:59:59';
                    
                    $new_plan->set_end_date($new_end_date);

                    if (!$is_free_trial) {
                        $new_start_date = date('Y', strtotime('+1 year')) . '-01-01 00:00:00';
                        $new_plan->set_start_date($new_start_date);
                        $wpdb->update(
                            $wpdb->prefix . 'posts',
                            ['post_status' => 'wcm-delayed'],
                            ['ID' => $new_plan->get_id()]
                        );
                    }
                }
            }
        } else {
            // For users with no active memberships, set the end date of the new plan
            $new_plan = wc_memberships_get_user_membership($member_details['user_id'], $membership_plan->id);
            $new_plan->set_end_date(date('Y') . '-12-30 23:59:59');
        }
    }
}
