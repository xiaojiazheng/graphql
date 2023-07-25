<?php

namespace  xiaobe\Graphql\exception\mutationaccess;

use xiaobe\Graphql\exception\MutationAccessException;

class MutationNotFoundException extends MutationAccessException
{
  public function __construct($msg, $code)
  {
    $code = 40 + $code;
    $message = "变更服务拒绝服务，原因: " . $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
