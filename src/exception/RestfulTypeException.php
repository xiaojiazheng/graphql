<?php

namespace  xiaobe\Graphql\exception;

class RestfulTypeException extends \Exception
{
  public function __construct($method)
  {
    $code = 1002; // 设置异常的固定代码
    $message = "请求方法错误，不支持{$method}请求"; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
