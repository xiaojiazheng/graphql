<?php

namespace xiaobe\Graphql\root;

use xiaobe\Graphql\exception\WithHookParamException;
use xiaobe\Graphql\exception\mutationaccess\MutationNotFoundException;

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
  protected function _add(QueryModel $model, $QLbody)
  {
    $data = $QLbody['others'];
    $unique = array_keys($QLbody['properties']);

    if (empty($data)) {
      throw new WithHookParamException('要插入的数据为空', 11);
    }

    return $model->withInsertUnique($unique, $data);
  }

  /**
   * 基于条件的编辑
   * 基于主键的编辑请使用
   * $model->withEdit()
   */
  protected function _edit(QueryModel $model, $QLbody)
  {
    $where = $QLbody['properties'];
    $data = $QLbody['others'];

    if (empty($where)) {
      throw new WithHookParamException('编辑操作被阻止，必须提供有效的编辑条件', 211);
    }

    if (isset($data[0])) {
      throw new WithHookParamException('批量编辑操作被阻止，只允许单条记录编辑', 212);
    }

    return $model->withWhere($where)->update($data);
  }

  /**
   * 基于条件的软硬删除
   * 基于主键的软硬删除请使用
   * $model->withDel()
   */
  protected function _del(QueryModel $model, $QLbody)
  {
    $where = $QLbody['properties'];
    $soft = isset($QLbody['addition']['soft']) ? $QLbody['addition']['soft'] : '';

    if (empty($where)) {
      throw new WithHookParamException('删除操作被阻止，必须提供有效的删除条件', 111);
    }

    if (!empty($soft)) {
      return $model->withWhere($where)->getQueryInstance()->update([$soft => 1]);
    }

    return $model->withWhere($where)->delete();
  }
}
