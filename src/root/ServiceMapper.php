<?php

namespace  Xiaobe\Graphql\root;

use Xiaobe\Graphql\exception\mutationaccess\MutationNotFoundException;


/**
 * @author xiaojiazheng <16673800623>
 * @category: None
 * @email: 2162621686@qq.com
 * @date: 2023-7-22 21:53
 * @description
 * 图表类型 与 变更服务 映射
 */
class ServiceMapper
{
  private $mapping = [];

  /**
   * 添加模型映射
   */
  public function addMapping(string $graphqlName, string $className)
  {
    $this->mapping[$graphqlName] = $className;
  }
  /**
   * @param string $graphqlName GraphQl类型名
   * 获取服务
   */
  public function getService(string $graphqlName): MutationService
  {
    if (isset($this->mapping[$graphqlName])) {
      $className = $this->mapping[$graphqlName];
      return new $className();
    } else {
      throw new MutationNotFoundException("变更服务{$graphqlName}未启用", 1);
    }
  }
}
