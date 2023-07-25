<?php

namespace  xiaobe\Graphql\exception\queryaccess;

use  xiaobe\Graphql\exception\QueryAccessException;

class ModelNotFoundException extends QueryAccessException
{
  public function __construct($msg, $code)
  {
    $code = 400 + $code;
    $message = "找不到查询模型，原因: " . $msg; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
