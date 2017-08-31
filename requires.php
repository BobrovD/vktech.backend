<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 19.08.17
 * Time: 0:07
 */

require_once $_SERVER['DOCUMENT_ROOT'].'/defines/mysql.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/defines/codes.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/defines/robokassa.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/defines/pay.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/defines/limit.php';

require_once $_SERVER['DOCUMENT_ROOT'].'/my_lib/error_catcher.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/my_lib/mysql.php';

require_once $_SERVER['DOCUMENT_ROOT'].'/func/helper.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/func/statistic.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/func/account.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/func/payment.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/func/payout.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/func/worker.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/func/task.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/func/memcached.php';