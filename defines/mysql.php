<?php
/**
 * Created by PhpStorm.
 * User: Orange
 * Date: 19.08.17
 * Time: 0:44
 */

namespace defines;

//константы классов работают шустрее define

class MySQL {

    //шардинг и master/slave репликации

    //Всего 4 mysql сервера (но пока что два):
    //для таблиц [order, error] : master + slave
    //для таблиц [customer, executant] : master + slave
    const CONNECTION = [
        'order' => [
            'database' => 'vktech',
            'name' => 'order',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'account' => [
            'database' => 'vktech',
            'name' => 'account',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'payment' => [
            'database' => 'vktech',
            'name' => 'payment',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'payout' => [
            'database' => 'vktech',
            'name' => 'payment',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'session' => [
            'database' => 'vktech',
            'name' => 'session',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'error' => [
            'database' => 'vktech',
            'name' => 'error',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'log' => [
            'database' => 'vktech',
            'name' => 'log',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'script_time' => [
            'database' => 'vktech',
            'name' => 'script_time',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'task' => [
            'database' => 'vktech',
            'name' => 'work',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'task_subscribes' => [
            'database' => 'vktech',
            'name' => 'work',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ],
        'work' => [
            'database' => 'vktech',
            'name' => 'work',
            'master' => [
                'server' => '165.227.145.30',
                'user' => 'root',
                'password' => '0976fcb6ec83f9688889387c34'
            ],
            'slave' => [
                'server' => '139.59.140.83',
                'user' => 'root',
                'password' => '466e7c7143ffef7e8cc4777079'
            ]
        ]
    ];

}