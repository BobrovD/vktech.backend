<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 29.08.17
 * Time: 12:00
 */

function create_payout ($id, $summ){
    $ip = $_SERVER['HTTP_X_REAL_IP'];
    $time = time();
    $table = 'payout';
    $server = 'master';
    $method = 'qiwi';//base method
    $query = 'INSERT INTO '.$table.' (user_id, user_ip, time, summ, method) VALUES (\''.$id.'\', INET_ATON(\''.$ip.'\'), \''.$time.'\', \''.$summ.'\', \''.$method.'\') ';
    query($table, $server, $query);
    if($payout_id = last_insert_id($table, $server)) {
        up_salary($id, -$summ);
        return $id;
    }
    return false;
}

function load_payout_history($id, $with_canseled = false){
    $table = 'payout';
    $server = 'slave';
    if(!$with_canseled)
        $status_list = ' AND status != \'canseled\'';
    $query = 'SELECT payout_id, INET_NTOA(user_ip) as user_ip, summ, time, status, method FROM '.$table.' WHERE user_id = \''.$id.'\' '.$status_list.' ORDER BY payout_id DESC ';//add limit + pages
    return query_ass($table, $server, $query);
}

function load_payout_by_id($payment_id, $status = false){
    $table = 'payout';
    $server = 'slave';
    if($status)
        $status_q = ' AND status = \''.$status.'\'';
    $query = 'SELECT * FROM '.$table.' WHERE payment_id = \''.$payment_id.'\' '.$status_q.' LIMIT 1';
    return query_ass_row($table, $server, $query);
}

function cansel_payout($payout_id){
    $table = 'payout';
    $server = 'master';
    $payout = load_payout_by_id($payout_id, 'created');
    if($payout['summ']){
        up_salary($payout['user_id'], $payout['summ']);
    }
    $query = 'UPDATE '.$table.' SET status = \'canseled\' WHERE payout_id = \''.$payout_id.'\' LIMIT 1';
    return query($table, $server, $query);
}