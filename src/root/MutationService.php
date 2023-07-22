<?php

namespace Xiaobe\Graphql\root;

use Xiaobe\Graphql\exception\mutationaccess\MutationNotFoundException;

/**
 * 变更服务抽象类
 */
abstract class MutationService
{
  public function __call($name, $argument)
  {
    throw new MutationNotFoundException('不支持行为: ' . $name . ',请检查拼写或联系管理员', 2);
  }
  /**
   * 参数结构如下
   * [
   * name,  服务名
   * data   QLBody数据
   * ]
   */
  public function index(array $param)
  {
    return "变更服务{$param['name']}正在运行";
  }
}
