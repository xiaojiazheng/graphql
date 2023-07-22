<?php

namespace  Xiaobe\graphql\exception;

use Exception;

class QueryAccessException extends Exception
{
  public function __construct($msg, $code)
  {
    $code = 4000 + $code; // 设置异常的固定代码
    $message = "图表查询服务访问失败，原因: " + $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
