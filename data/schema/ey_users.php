<?php 
return array (
  'users_id' => 
  array (
    'name' => 'users_id',
    'type' => 'int(11) unsigned',
    'notnull' => false,
    'default' => NULL,
    'primary' => true,
    'autoinc' => true,
  ),
  'username' => 
  array (
    'name' => 'username',
    'type' => 'varchar(30)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'password' => 
  array (
    'name' => 'password',
    'type' => 'varchar(255)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'nickname' => 
  array (
    'name' => 'nickname',
    'type' => 'varchar(50)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'is_mobile' => 
  array (
    'name' => 'is_mobile',
    'type' => 'tinyint(1)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'mobile' => 
  array (
    'name' => 'mobile',
    'type' => 'varchar(20)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'is_email' => 
  array (
    'name' => 'is_email',
    'type' => 'tinyint(1)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'email' => 
  array (
    'name' => 'email',
    'type' => 'varchar(60)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'paypwd' => 
  array (
    'name' => 'paypwd',
    'type' => 'varchar(255)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'users_money' => 
  array (
    'name' => 'users_money',
    'type' => 'decimal(20,2)',
    'notnull' => false,
    'default' => '0.00',
    'primary' => false,
    'autoinc' => false,
  ),
  'frozen_money' => 
  array (
    'name' => 'frozen_money',
    'type' => 'decimal(20,2)',
    'notnull' => false,
    'default' => '0.00',
    'primary' => false,
    'autoinc' => false,
  ),
  'scores' => 
  array (
    'name' => 'scores',
    'type' => 'int(10)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'devote' => 
  array (
    'name' => 'devote',
    'type' => 'int(10)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'reg_time' => 
  array (
    'name' => 'reg_time',
    'type' => 'int(11) unsigned',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'last_login' => 
  array (
    'name' => 'last_login',
    'type' => 'int(11) unsigned',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'last_ip' => 
  array (
    'name' => 'last_ip',
    'type' => 'varchar(15)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'login_count' => 
  array (
    'name' => 'login_count',
    'type' => 'int(11)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'head_pic' => 
  array (
    'name' => 'head_pic',
    'type' => 'varchar(255)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'province' => 
  array (
    'name' => 'province',
    'type' => 'int(6)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'city' => 
  array (
    'name' => 'city',
    'type' => 'int(6)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'district' => 
  array (
    'name' => 'district',
    'type' => 'int(6)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'level' => 
  array (
    'name' => 'level',
    'type' => 'smallint(5)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'open_level_time' => 
  array (
    'name' => 'open_level_time',
    'type' => 'int(11) unsigned',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'level_maturity_days' => 
  array (
    'name' => 'level_maturity_days',
    'type' => 'varchar(20)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'discount' => 
  array (
    'name' => 'discount',
    'type' => 'decimal(20,2)',
    'notnull' => false,
    'default' => '1.00',
    'primary' => false,
    'autoinc' => false,
  ),
  'total_amount' => 
  array (
    'name' => 'total_amount',
    'type' => 'decimal(20,2)',
    'notnull' => false,
    'default' => '0.00',
    'primary' => false,
    'autoinc' => false,
  ),
  'order_total_amount' => 
  array (
    'name' => 'order_total_amount',
    'type' => 'decimal(20,2) unsigned',
    'notnull' => false,
    'default' => '0.00',
    'primary' => false,
    'autoinc' => false,
  ),
  'is_activation' => 
  array (
    'name' => 'is_activation',
    'type' => 'tinyint(1)',
    'notnull' => false,
    'default' => '1',
    'primary' => false,
    'autoinc' => false,
  ),
  'register_place' => 
  array (
    'name' => 'register_place',
    'type' => 'tinyint(1)',
    'notnull' => false,
    'default' => '2',
    'primary' => false,
    'autoinc' => false,
  ),
  'open_id' => 
  array (
    'name' => 'open_id',
    'type' => 'varchar(50)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'thirdparty' => 
  array (
    'name' => 'thirdparty',
    'type' => 'tinyint(1)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'is_lock' => 
  array (
    'name' => 'is_lock',
    'type' => 'tinyint(1)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'admin_id' => 
  array (
    'name' => 'admin_id',
    'type' => 'int(10)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'lang' => 
  array (
    'name' => 'lang',
    'type' => 'varchar(20)',
    'notnull' => false,
    'default' => 'cn',
    'primary' => false,
    'autoinc' => false,
  ),
  'is_del' => 
  array (
    'name' => 'is_del',
    'type' => 'tinyint(1)',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'unread_notice_num' => 
  array (
    'name' => 'unread_notice_num',
    'type' => 'int(10) unsigned',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'update_time' => 
  array (
    'name' => 'update_time',
    'type' => 'int(11) unsigned',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'sex' => 
  array (
    'name' => 'sex',
    'type' => 'varchar(10)',
    'notnull' => false,
    'default' => '保密',
    'primary' => false,
    'autoinc' => false,
  ),
  'coin' => 
  array (
    'name' => 'coin',
    'type' => 'int(11) unsigned',
    'notnull' => false,
    'default' => '0',
    'primary' => false,
    'autoinc' => false,
  ),
  'union_id' => 
  array (
    'name' => 'union_id',
    'type' => 'varchar(50)',
    'notnull' => false,
    'default' => '',
    'primary' => false,
    'autoinc' => false,
  ),
  'source' => 
  array (
    'name' => 'source',
    'type' => 'tinyint(3)',
    'notnull' => false,
    'default' => '1',
    'primary' => false,
    'autoinc' => false,
  ),
);