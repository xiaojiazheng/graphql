<?php

namespace  xiaobe\Graphql\exception;

use Exception;

class WithHookParamException extends Exception
{
  public function __construct($msg, $code)
  {
    $code = 3000 + $code; // 设置异常的固定代码
    $message = "钩子参数异常，原因: " . $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
