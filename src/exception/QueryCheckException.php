<?php

namespace  Xiaobe\Graphql\exception;

use Exception;

/**
 * 查询参数错误
 */
class QueryCheckException extends Exception
{
  public function __construct($message, $code)
  {
    $code = 1100 + $code; // 设置异常的固定代码
    $message = "查询参数{$message}"; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
