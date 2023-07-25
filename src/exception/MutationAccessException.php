<?php

namespace  xiaobe\Graphql\exception;

use Exception;

class MutationAccessException extends Exception
{
  public function __construct($msg, $code)
  {
    $code = 400 + $code; // 设置异常的固定代码
    $message = "变更服务访问失败，原因: " . $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
