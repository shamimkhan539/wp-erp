<?php
namespace WeDevs\ERP\Updates\BP\Leave;

if ( ! class_exists( 'WP_Async_Request', false ) ) {
    require_once WPERP_INCLUDES . '/lib/bgprocess/wp-async-request.php';
}

if ( ! class_exists( 'WP_Background_Process', false ) ) {
    require_once WPERP_INCLUDES . '/lib/bgprocess/wp-background-process.php';
}

/**
 * Migrate Leave Request Data to new database model
 *
 * For each leave request table entry, there will be an entry in new leave request table, leave approval status table,
 * leave entitlements table and leave request details table.
 *
 * @since 1.5.15
 * @package WeDevs\ERP\Updates\BP\Leave
 */
class ERP_HR_Leave_Request extends \WP_Background_Process {

    /**
     * Background process name.
     *
     * @var string
     */
    protected $action = 'erp_hr_leaves_request_1_5_15';

    /**
     * Possible array elements
     *
     * @var array
     */
    protected $request_data = array(
        'task'                     => 'leave_request',
        'id'                       => 0,
        'user_id'                  => 0,
        'policy_id'                => 0,
        'days'                     => 0,
        'start_date'               => 0,
        'end_date'                 => 0,
        'comments'                 => '',
        'reason'                   => '',
        'status'                   => 0,
        'created_by'               => '',
        'updated_by'               => '',
        'created_on'               => '',
        'updated_on'               => '',
        'leave_request_id'         => 0,
        'leave_id'                 => '',
        'f_year'                   => '',
        'leave_approval_status_id' => 0,
        'leave_entitlement_id'     => 0,
    );

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param array $leave_request Queue item to iterate over.
     *
     * @return mixed
     */
    protected function task( $leave_request ) {
        $this->request_data = wp_parse_args( $leave_request, $this->request_data );

        error_log( print_r(
            array(
                'file' => __FILE__, 'line' => __LINE__,
                'message' => 'Leave request data: ' . print_r( $this->request_data, true )
            ), true )
        );

        switch ( $this->request_data['task'] ) {

            case 'leave_request':
                return $this->insert_leave_request();
                break;

            case 'leave_approval_status':
                return $this->insert_leave_approval_status();
                break;

            case 'leave_entitlements':
                return $this->insert_leave_entitlement();
                break;

            case 'leave_request_details':
                return $this->insert_leave_request_details();
                break;

            default:
                break;

        }

        return false;
    }

    /**
     * This method will insert old leave request single row data to new leave request table.
     *
     * @since 1.5.15
     *
     * @return array will return updated data to further run current background process.
     */
    protected function insert_leave_request() {
        global $wpdb;

        // get leave request data
        $leave_request_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_hr_leave_requests WHERE id = %d",
                array( $this->request_data['id'] )
            ),
            ARRAY_A
        );

        if ( null === $leave_request_data ) {
            error_log( print_r(
                array(
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => 'No old leave request data.'
                ), true )
            );
            // no result found: can be because of query error, handle this problem here probably log this error.
            // todo: keep log here.

        } elseif ( is_array( $leave_request_data ) && ! empty( $leave_request_data ) ) {
            // store datas for further use.
            $this->request_data = wp_parse_args( $leave_request_data, $this->request_data );
            // fix dates.
            if ( isset( $this->request_data['start_date'] ) ) {
                $this->request_data['start_date'] = erp_mysqldate_to_phptimestamp( $this->request_data['start_date'] );
            }

            if ( isset( $this->request_data['end_date'] ) ) {
                $this->request_data['end_date'] = erp_mysqldate_to_phptimestamp( $this->request_data['end_date'] );
            }

            if ( isset( $this->request_data['created_on'] ) ) {
                $this->request_data['created_on'] = erp_mysqldate_to_phptimestamp( $this->request_data['created_on'] );
            }

            if ( isset( $this->request_data['updated_on'] ) ) {
                $this->request_data['updated_on'] = erp_mysqldate_to_phptimestamp( $this->request_data['updated_on'] );
            }

            // now get data from new leave policy table.
            $policy_data = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT leave_id, f_year FROM {$wpdb->prefix}erp_hr_leave_policies_new WHERE old_policy_id = %d",
                    array( $this->request_data['policy_id'] )
                ),
                ARRAY_A
            );

            if ( null === $policy_data ) {
                error_log( print_r(
                    array(
                        'file' => __FILE__, 'line' => __LINE__,
                        'message' => '(Query error) No policies data found from new table.'
                    ), true )
                );
                // no result found: can be because of query error, handle this problem here probably log this error.
                // todo: keep log here.

            } elseif ( is_array( $policy_data ) && ! empty( $policy_data ) ) {
                $this->request_data['leave_id'] = $policy_data['leave_id'];
                $this->request_data['f_year']   = $policy_data['f_year'];

                // insert into new leave request table.
                $table_data = array(
                    'user_id'       => $this->request_data['user_id'],
                    'leave_id'      => $this->request_data['leave_id'],
                    'day_status_id' => 1,
                    'days'          => $this->request_data['days'],
                    'start_date'    => $this->request_data['start_date'],
                    'end_date'      => $this->request_data['end_date'],
                    'reason'        => wp_kses_post( $this->request_data['reason'] ),
                    'created_at'    => $this->request_data['created_on'],
                    'updated_at'    => $this->request_data['updated_on'],
                );

                $table_format = array(
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                    '%d',
                    '%d',
                );

                if ( $wpdb->insert( "{$wpdb->prefix}erp_hr_leave_requests_new", $table_data, $table_format ) === false ) {
                    error_log( print_r(
                        array(
                            'file' => __FILE__, 'line' => __LINE__,
                            'message' => '(Query error) Insertion failed into new leave requests table.'
                        ), true )
                    );
                    // todo: query error, do loging or something here.
                } else {
                    $this->request_data['task']             = 'leave_approval_status';
                    $this->request_data['leave_request_id'] = $wpdb->insert_id;


                    error_log( print_r(
                        array(
                            'file' => __FILE__, 'line' => __LINE__,
                            'message' => 'New Leave request data: ' . print_r( $this->request_data, true )
                        ), true )
                    );

                    return $this->request_data;
                }
            }
        }
        // todo: should I return something else in case of errors ?
    }

    /**
     * This method will insert old leave request single row data to new leave approval status table.
     *
     * @since 1.5.15
     *
     * @return array will return updated data to further run current background process.
     */
    protected function insert_leave_approval_status() {
        error_log( print_r(
            array(
                'file' => __FILE__, 'line' => __LINE__,
                'message' => 'Insert leave approval status: ' . print_r( $this->request_data, true )
            ), true )
        );

        // insert only if leave is approved or rejected and request is already made.
        if ( isset( $this->request_data['status'] ) && in_array( $this->request_data['status'], array( 1, 3 ) ) && isset( $this->request_data['leave_request_id'] ) && $this->request_data['leave_request_id'] > 0 ) {
            // leave approved or rejected.
            global $wpdb;
            $table_data = array(
                'leave_request_id'   => $this->request_data['leave_request_id'],
                'approval_status_id' => $this->request_data['status'],
                'approved_by'        => $this->request_data['updated_by'],
                'approved_date'      => $this->request_data['updated_on'],
                'created_at'         => $this->request_data['updated_on'],
                'updated_at'         => $this->request_data['updated_on'],
            );

            $table_format = array(
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
                '%d',
            );

            if ( isset( $this->request_data['comments'] ) && $this->request_data['comments'] != '' ) {
                $table_data['message'] = wp_kses_post( $this->request_data['comments'] );
                $table_format[]        = '%s';
            }

            if ( $wpdb->insert( "{$wpdb->prefix}erp_hr_leave_approval_status_new", $table_data, $table_format ) === false ) {
                error_log( print_r(
                    array(
                        'file' => __FILE__, 'line' => __LINE__,
                        'message' => '(Query error) Insertion failed into new leave approval status table.'
                    ), true )
                );
                // todo: query error, do loging or something here.
            } else {
                $this->request_data['task']                     = 'leave_entitlements';
                $this->request_data['leave_approval_status_id'] = $wpdb->insert_id;
                return $this->request_data;
            }
        }
        // todo: should I return something else in case of errors ?
    }

    protected function insert_leave_entitlement() {
        if ( isset( $this->request_data['leave_approval_status_id'] ) && $this->request_data['leave_approval_status_id'] > 0 && isset( $this->request_data['status'] ) && $this->request_data['status'] === 1 ) {

            global $wpdb;

            $table_data = array(
                'user_id'     => $this->request_data['user_id'],
                'leave_id'    => $this->request_data['leave_id'],
                'created_by'  => $this->request_data['updated_by'],
                'trn_id'      => $this->request_data['leave_approval_status_id'],
                'trn_type'    => 'leave_approval_status',
                'day_in'      => $this->request_data['days'],
                'day_out'     => 0,
                'description' => erp_hr_leave_request_get_statuses( $this->request_data['status'] ),
                'f_year'      => $this->request_data['f_year'],
                'created_at'  => $this->request_data['created_on'],
                'updated_at'  => $this->request_data['created_on'],
            );

            $table_format = array(
                '%d',
                '%d',
                '%d',
                '%d',
                '%s',
                '%d',
                '%d',
                '%s',
                '%d',
                '%d',
                '%d',
            );

            if ( $wpdb->insert( "{$wpdb->prefix}erp_hr_leave_entitlements_new", $table_data, $table_format ) === false ) {
                error_log( print_r(
                    array(
                        'file' => __FILE__, 'line' => __LINE__,
                        'message' => '(Query error) Insertion failed into new leave entitlement table.'
                    ), true )
                );
                // todo: query error, do logging or something here.
            } else {
                $this->request_data['task']                 = 'leave_request_details';
                $this->request_data['leave_entitlement_id'] = $wpdb->insert_id;

                // now get days data from new leave policy table.
                $policy_days = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT days FROM {$wpdb->prefix}erp_hr_leave_policies_new WHERE old_policy_id = %d",
                        array( $this->request_data['policy_id'] )
                    )
                );

                $days_count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT SUM(days) FROM {$wpdb->prefix}erp_hr_leave_requests WHERE policy_id = %d and user_id = %d and id <= %d",
                        array( $this->request_data['policy_id'], $this->request_data['user_id'], $this->request_data['id'] )
                    )
                );

                if ( $days_count > $policy_days ) {
                    // calculate extra leaves
                    $option_key = "extra_days_count_{$this->request_data['user_id']}_{$this->request_data['policy_id']}";
                    $extra_days_count = absint( get_option( $option_key , 0 ) );

                    $current_count = absint( $days_count ) - absint( $policy_days ) - absint( $extra_days_count );
                    update_option( $option_key, $extra_days_count + $current_count );

                    // insert into new unpaid leave table.
                    $table_data = array(
                        'leave_id'                 => $this->request_data['leave_id'],
                        'leave_request_id'         => $this->request_data['leave_request_id'],
                        'leave_approval_status_id' => $this->request_data['leave_approval_status_id'],
                        'user_id'                  => $this->request_data['user_id'],
                        'days'                     => $current_count,
                        'amount'                   => 0,
                        'total'                    => 0,
                        'status'                   => 1,
                        'created_at'               => $this->request_data['created_on'],
                        'updated_at'               => $this->request_data['updated_on'],
                    );

                    $table_format = array(
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d',
                        '%d'
                    );

                    if ( $wpdb->insert( "{$wpdb->prefix}erp_hr_leaves_unpaid_new", $table_data, $table_format ) === false ) {
                        error_log( print_r(
                            array(
                                'file' => __FILE__, 'line' => __LINE__,
                                'message' => '(Query error) Insertion failed new unpaid leave table.'
                            ), true )
                        );
                        // todo: query error, do loging or something here.
                    } else {
                        $table_data = array(
                            'user_id'     => $this->request_data['user_id'],
                            'leave_id'    => $this->request_data['leave_id'],
                            'created_by'  => $this->request_data['updated_by'],
                            'trn_id'      => $wpdb->insert_id,
                            'trn_type'    => 'unpaid_leave',
                            'day_in'      => $current_count,
                            'day_out'     => 0,
                            'description' => 'Accounts',
                            'f_year'      => $this->request_data['f_year'],
                            'created_at'  => $this->request_data['created_on'],
                            'updated_at'  => $this->request_data['created_on'],
                        );

                        $table_format = array(
                            '%d',
                            '%d',
                            '%d',
                            '%d',
                            '%s',
                            '%d',
                            '%d',
                            '%s',
                            '%d',
                            '%d',
                            '%d',
                        );

                        if ( $wpdb->insert( "{$wpdb->prefix}erp_hr_leave_entitlements_new", $table_data, $table_format ) === false ) {
                            error_log( print_r(
                                array(
                                    'file' => __FILE__, 'line' => __LINE__,
                                    'message' => '(Query error) Insertion failed new leave entitlements table.'
                                ), true )
                            );
                            // todo: query error, do loging or something here.
                        } else {
                            // All done, have fun
                        }

                    }
                }

                return $this->request_data;
            }
        }
        // todo: should I return something else in case of errors ?
    }

    /**
     * This method will insert old hr_leaves table data to new leave request details table. Can be multiple rows.
     *
     * @since 1.5.15
     *
     * @return bool will return false on success that will prevent for this task to run further and remove this task from current queue.
     */
    protected function insert_leave_request_details() {
        if ( isset( $this->request_data['leave_entitlement_id'] ) && $this->request_data['leave_entitlement_id'] > 0 ) {
            // get hr leaves: data coming from old db table.
            global $wpdb;
            $hr_leaves_data = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT `date` FROM {$wpdb->prefix}erp_hr_leaves WHERE request_id = %d",
                    array( $this->request_data['id'] )
                )
            );

            if ( is_array( $hr_leaves_data ) && ! empty( $hr_leaves_data ) ) {
                $table_rows = array();
                foreach ( $hr_leaves_data as $leave_date ) {
                    $table_rows[] = array(
                        'leave_request_id'         => $this->request_data['leave_request_id'],
                        'leave_approval_status_id' => $this->request_data['leave_approval_status_id'],
                        'workingday_status'        => 1,
                        'user_id'                  => $this->request_data['user_id'],
                        'leave_date'               => erp_mysqldate_to_phptimestamp( $leave_date ),
                        'created_at'               => $this->request_data['created_on'],
                        'updated_at'               => $this->request_data['created_on'],
                    );
                }
                if ( ! empty( $table_rows ) ) {
                    if ( erp_wp_insert_rows( $table_rows, "{$wpdb->prefix}erp_hr_leave_request_details_new" ) == false ) {
                        error_log( print_r(
                            array(
                                'file' => __FILE__, 'line' => __LINE__,
                                'message' => '(Query error) Insertion failed new leave request details table.'
                            ), true )
                        );
                        // todo: query error, add this error to log file.
                    } else {
                        return false;
                    }
                }
            }
        }
        // all import task in completed now we can safely return false from here;
        return false;
    }

    /**
     * Complete
     */
    protected function complete() {
        parent::complete();

        if ( ! class_exists('\WeDevs\ERP\HRM\Update\ERP_1_5_15') ) {
            require_once WPERP_INCLUDES . '/updates/update-1.5.15.php';
        }

        //now delete all old db tables and data
        $erp_update_1_5_15 = new \WeDevs\ERP\HRM\Update\ERP_1_5_15();

        if ( $erp_update_1_5_15->delete_old_db_tables() ) {
            $erp_update_1_5_15->alter_new_db_tables();
        }
    }
}
