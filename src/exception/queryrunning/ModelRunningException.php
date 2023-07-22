<?php

namespace  Xiaobe\Graphql\exception\queryrunning;

use  Xiaobe\Graphql\exception\QueryRunningException;

class ModelRunningException extends QueryRunningException
{
  public function __construct($msg, $code)
  {
    $code = 100; // 设置异常的固定代码
    $msg = "查询模型运行错误，原因: " + $msg; // 设置异常的固定消息

    parent::__construct($msg, $code);
  }
}
