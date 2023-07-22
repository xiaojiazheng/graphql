<?php

namespace  Xiaobe\graphql\exception\queryrunning;

use  Xiaobe\graphql\exception\QueryRunningException;

class QueryParseException extends QueryRunningException
{
  public function __construct($msg, $code)
  {
    $message = "图表类型解析失败，原因: " + $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
