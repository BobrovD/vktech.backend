<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 27.08.17
 * Time: 14:11
 */

function create_payment ($id, $summ){
    $ip = $_SERVER['HTTP_X_REAL_IP'];
    $time = time();
    $table = 'payment';
    $server = 'master';
    $query = 'INSERT INTO '.$table.' (user_id, user_ip, time, summ) VALUES (\''.$id.'\', INET_ATON(\''.$ip.'\'), \''.$time.'\', \''.$summ.'\') ';
    query($table, $server, $query);
    return last_insert_id($table, $server);
}

function generate_robo_payment ($id, $summ){
    $answ = [];
    $answ['OutSum'] = $summ;
    $answ['InvDesc'] = defines\Robokassa::InvDesc;
    $answ['MerchantLogin'] = defines\Robokassa::MerchantLogin;
    $answ['InvoiceID'] = create_payment($id, $summ);
    $pwd = defines\Robokassa::TEST_PASS_1;
    $answ['SignatureValue'] = md5( $answ['MerchantLogin'].':'.$answ['OutSum'].':'.$answ['InvoiceID'].':'.$pwd);
    return $answ;
}

function load_payment_history($id, $with_outdated = false){
    $table = 'payment';
    $server = 'slave';
    if(!$with_outdated)
        $status_list = ' AND status != \'outdated\'';
    $query = 'SELECT payment_id, INET_NTOA(user_ip) as user_ip, summ, time, status FROM '.$table.' WHERE user_id = \''.$id.'\' '.$status_list.' ORDER BY payment_id DESC ';//add limit + pages
    return query_ass($table, $server, $query);
}

function load_payment_by_id($payment_id, $status = false){
    $table = 'payment';
    $server = 'slave';
    if($status)
        $status_q = ' AND status = \''.$status.'\'';
    $query = 'SELECT * FROM '.$table.' WHERE payment_id = \''.$payment_id.'\' '.$status_q.' LIMIT 1';
    return query_ass_row($table, $server, $query);
}

function check_robox_signature($inputs){
    return strtoupper(md5($inputs['OutSum'].':'.$inputs['InvId'].':'.defines\Robokassa::TEST_PASS_2)) === $inputs['SignatureValue'];
}

function approve_payment($payment_id){
    $payment = load_payment_by_id($payment_id, 'created');
    $table = 'payment';
    $server = 'master';
    $query = 'UPDATE '.$table.' SET status = \'received\' WHERE payment_id = \''.$payment_id.'\'';
    query($table, $server, $query);
    up_balance($payment['user_id'], $payment['summ']);
}

function reject_payment($payment_id){
    $table = 'payment';
    $server = 'master';
    $query = 'UPDATE '.$table.' SET status = \'rejected\' WHERE payment_id = \''.$payment_id.'\'';
    query($table, $server, $query);
}