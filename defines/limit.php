<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 30.08.17
 * Time: 8:40
 */

namespace defines;

class Limit {
    const LIMIT_CONNECTIONS = [
        //time => connections
        1 => 5,
        60 => 180
    ];
    //помним имя пользователя 5 минут.
    const REMEMBER_USER_NAME = 60 * 5;
}