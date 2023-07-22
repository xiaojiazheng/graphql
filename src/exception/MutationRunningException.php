<?php

namespace  Xiaobe\Graphql\exception;

use Exception;

class MutationRunningException extends Exception
{
  public function __construct($msg, $code)
  {
    $code = 5000 + $code; // 设置异常的固定代码
    $message = "多服务运行错误，原因: " . $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
