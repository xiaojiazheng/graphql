<?php

namespace  xiaobe\Graphql\exception\queryaccess;

use  xiaobe\Graphql\exception\QueryAccessException;

class QueryNotAllowException extends QueryAccessException
{
  public function __construct($msg, $code)
  {
    $code = 300 + $code;
    $message = "图表查询被拒绝，原因: " . $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
