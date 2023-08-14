<?php

namespace  xiaobe\Graphql\exception;

class RequestTypeException extends \Exception
{
  public function __construct()
  {
    $code = 1001; // 设置异常的固定代码
    $message = "请求类型错误，不支持web服务"; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
