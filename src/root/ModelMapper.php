<?php

namespace  xiaobe\Graphql\root;

use xiaobe\Graphql\exception\queryaccess\ModelNotFoundException;
use xiaobe\Graphql\root\QueryModel;


/**
 * @author xiaojiazheng <16673800623>
 * @category: None
 * @email: 2162621686@qq.com
 * @date: 2023-7-21 21:16
 * @description
 * 图表类型 与 查询模型 映射
 */
class ModelMapper
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
   * 获取模型
   */
  public function getModel(string $graphqlName): QueryModel
  {
    if (isset($this->mapping[$graphqlName])) {
      $className = $this->mapping[$graphqlName];
      return new $className();
    } else {
      throw new ModelNotFoundException("图表类型{$graphqlName}未绑定查询模型", 1);
    }
  }
}
