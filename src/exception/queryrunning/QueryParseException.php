<?php

namespace  Xiaobe\Graphql\exception\queryrunning;

use  Xiaobe\Graphql\exception\QueryRunningException;

class QueryParseException extends QueryRunningException
{
  public function __construct($msg, $code)
  {
    $message = "图表类型解析失败，原因: " + $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
