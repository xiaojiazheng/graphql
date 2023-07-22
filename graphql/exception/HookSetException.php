<?php

namespace  Xiaobe\graphql\exception;

use Exception;

/**
 * 钩子设置异常
 * 函数时没有实现抛出此错误
 */
class HookSetException extends Exception
{
  public function __construct()
  {
    $code = 4001; // 设置异常的固定代码
    $message = "没有配置该接口"; // 设置异常的固定消息

    parent::__construct($message, $code);
  }
}
