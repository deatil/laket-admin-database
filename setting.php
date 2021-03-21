<?php

// 设置
return [
    [ 
        'name' => "path", 
        'title' => '数据库备份路径', 
        'type' => 'text', 
        'value' => '/data/', 
        'remark' => '路径必须以 / 结尾',
    ],
    [
        'name' => "part", 
        'title' => '数据库备份卷大小',
        'type' => 'text',
        'value' => '20971520',
        'remark' => '该值用于限制压缩后的分卷最大长度。单位：B；建议设置20M',
    ],
    [
        'name' => "compress", 
        'title' => '是否启用压缩',
        'type' => 'select',
        'options' => [
            '1' => '启用压缩',
            '0' => '不启用',
        ],
        'value' => '1',
        'remark' => '压缩备份文件需要PHP环境支持gzopen,gzwrite函数',
    ],
    [
        'name' => "level", 
        'title' => '压缩级别',
        'type' => 'select',
        'options' => [
            '1' => '普通',
            '4' => '一般',
            '9' => '最高',
        ],
        'value' => '9',
        'remark' => '数据库备份文件的压缩级别，该配置在开启压缩时生效',
    ],
];
