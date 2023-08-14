<?php

namespace xiaobe\Graphql\root;

use support\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use xiaobe\Graphql\exception\WithHookParamException;
use xiaobe\Graphql\exception\queryrunning\ModelRunningException;

/**
 * @author xiaojiazheng <16673800623>
 * @email: 2162621686@qq.com
 * @date: 2023-7-21 20:08
 * @description
 * 查询模型
 * 自动化处理查询请求
 */
class QueryModel extends Model
{
    /**
     * 查询单例
     */
    protected $queryInstance = null;
    /**
     * 关闭时间戳维护
     */
    public $timestamps = false;

    /**
     * 获取查询单例
     * 
     */
    public function getqueryInstance()
    {
        if (!$this->queryInstance)
            $this->queryInstance = $this->query();
        return $this->queryInstance;
    }
    /**
     * 重置查询实例
     */
    public function resetqueryInstance()
    {
        $this->queryInstance = $this->query();
        return $this;
    }
    /**
     * 查询条件配置转ORM查询操作
     * @param array $properties 魔改查询语法
     * @param mixed  $query 查询对象，不指定默认从当前模型拿
     * @param boolean $withmode 递归处理关联查询用的，不用管
     */
    public function withWhere(&$properties, $query = null, $withmode = false)
    {
        if (!$query) {
            $query = $this->getqueryInstance();
        }
        if (empty($properties))
            return $this;
        $withKey = array_keys($properties);
        foreach ($properties as $keyword => $value) {
            if (!$value && $value != 0) {
                continue;
            }
            if (is_numeric($value)) {
                $this->applyCondition($query, $keyword, $value);
            } else if (is_string($value)) {
                // 兼容条件与或操作
                $conditions = preg_split('/([&|])/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
                $conditions = array_map('trim', $conditions);
                $conditions = array_filter($conditions, function ($condition) {
                    return !empty($condition); // 过滤掉空字符串
                });
                $whereMethod = 'where';
                foreach ($conditions as $condition) {
                    if (in_array($condition, ['&', '|'])) {
                        $conditions == '&' ? $whereMethod = 'where' : $whereMethod = 'orWhere';
                        continue;
                    }
                    $this->applyCondition($query, $keyword, $condition, $whereMethod);
                }
            } else if (is_array($value)) {
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // 判断数组第一个元素是否为"~"
                    if ($value[0] === '~') {
                        // 使用whereBetween进行条件筛选
                        if (count($value) >= 3) {
                            $minValue = !empty($value[1]) ? $value[1] : null;
                            $maxValue = !empty($value[2]) ? $value[2] : null;
                            if ($minValue !== null || $maxValue !== null) {
                                if ($minValue !== null) {
                                    $query->where($keyword, '>=', $minValue);
                                }
                                if ($maxValue !== null) {
                                    $query->where($keyword, '<=', $maxValue);
                                }
                            }
                        } else {
                            throw new ModelRunningException("存在名为{$keyword}的图表查询模板prop值不符合whereBetween条件筛选的要求", 2);
                        }
                    } else {
                        // 使用whereIn进行条件筛选
                        $query->whereIn($keyword, $value);
                    }
                } else {
                    if ($withmode) {
                        $withKey = array_diff($withKey, [$keyword]);
                    }
                    $nestedWithQuery = function ($subQuery) use ($value) {
                        $this->withWhere($value, $subQuery, true);
                    };
                    if (strpos($keyword, '_') === 0) {
                        // 去掉关键字中的 *
                        $trimKeyword = str_replace('_', '', $keyword);
                        // 使用 whereHas() 方法影响主查询
                        $query->with($trimKeyword, $nestedWithQuery);
                        $query->whereHas($trimKeyword, $nestedWithQuery);
                    } else {
                        // 嵌套关联查询
                        $query->with($keyword, $nestedWithQuery);
                    }

                    // 将额外的限制条件应用到主查询
                    unset($properties[$keyword]);
                }
            } else {
                throw new ModelRunningException("存在名为{$keyword}的图表查询模板prop值不是字符串或数组类型", 1);
            }
        }
        if ($withmode) {
            $query->select($withKey);
        }
        return $this;
    }
    /**
     * 轻量级编辑，根据某个字段，默认主键
     * @param array $data 需要编辑的数据
     * @param array $uniqueKeys 业务上的唯一键
     * @param string $primaryKey 编辑操作依照的字段名，默认为模型定义的主键
     * @return int 受影响的行数
     */
    public function withEdit(array $data, array $uniqueKeys = [],  $primaryKey = null)
    {
        if (empty($data)) {
            return 0;
        }
        if (!$primaryKey) {
            $primaryKey = $this->primaryKey;
        }
        if (!isset($data[$primaryKey])) {
            throw new WithHookParamException('编辑操作被阻止，必须提供有效的唯一键', 201);
        }
        if ($uniqueKeys) {
            $query = $this->getqueryInstance()->where($primaryKey, '<>', $data[$primaryKey]);
            foreach ($uniqueKeys as $uniqueKey) {
                $query->where($uniqueKey, $data[$uniqueKey]);
            }
            $exist  = $query->first();
            if ($exist) {
                return 0;
            }
            $this->resetqueryInstance();
        }

        // 提取主键值
        $id = $data[$primaryKey];
        // 从$data数组中移除主键字段
        unset($data[$primaryKey]);
        // 执行更新操作
        $query = $this->getqueryInstance();
        return $query->where($primaryKey, $id)->update($data);
    }

    /**
     * 轻量级删除，根据某个字段删除，默认模型主键
     * 支持单个，批量
     * @param string $value 某字段的值索引数组
     * @param string $primaryKey 字段名，默认为模型定义的主键
     * @param string $softkey 是否软删除 为空不删除，否则指定字段设置为0
     */
    public function withDel(array $values, $primaryKey = null, $softkey = null)
    {
        if (empty($values)) {
            throw new WithHookParamException('删除操作被阻止，必须提供有效的唯一键值', 101);
        }

        if (!$primaryKey) {
            $primaryKey = $this->primaryKey;
        }
        $query = $this->getqueryInstance()->whereIn($primaryKey, $values);
        if ($softkey) {
            // 软删除，将指定字段值改为0
            return $query->update([$softkey => 1]);
        } else {
            // 直接删除
            return $query->delete();
        }
    }
    /**
     * @author xiaojiazheng
     * 单批量唯一添加钩子
     * @param $uniqueKey 指定的唯一key
     * @param $data 二维数组
     * @return int 受影响的行数
     */
    public function withInsertUnique(array $uniqueKeys, array $data)
    {
        if (empty($data)) {
            throw new WithHookParamException('要插入的数据为空', 1);
        }

        $uniqueData = [];
        if (!isset($data[0])) $data = [$data];
        foreach ($data as $item) {
            $temp = [];
            foreach ($uniqueKeys as $key) {
                $temp[$key] = $item[$key];
            }
            $uniqueData[] = $temp;
        }

        $query = $this->getqueryInstance();
        foreach ($uniqueKeys as $key) {
            $query->whereIn($key, Arr::pluck($data, $key));
        }

        $existingData = $query->select($uniqueKeys)->get()->toArray();
        $diffData = array_diff_key($uniqueData, $existingData);

        if (count($diffData) == 0) {
            return false;
        } else {
            $insertData = array_filter($data, function ($item) use ($uniqueKeys, $diffData) {
                $result = 0;
                foreach ($uniqueKeys as $key) {
                    if (in_array($item[$key], Arr::pluck($diffData, $key))) {
                        $result++;
                    }
                }
                return $result == count($uniqueKeys);
            });
            if (empty($insertData)) {
                return false;
            }

            $query->insert($insertData);
            return count($insertData);
        }
    }
    /**
     * 推荐使用
     * 集合数组分页利器
     * @return array 分页后的数组
     */
    public static function withPaginate($result, $page = 1, $limit = 10)
    {
        if (!is_array($result)) $result =  toArray($result);
        $start = ($page - 1) * $limit;
        $total = count($result);

        if ($start >= $total) {
            return ['data' => [], 'total' => $total];
        }

        $end = min($start + $limit, $total);
        $pagedData = array_slice($result, $start, $end - $start);

        return ['data' => $pagedData, 'total' => $total];
    }
    /**
     * 推荐使用
     * 查询分页利器
     */
    public function doPaginate($pageArray = [1, 10], $cols = ['*'], $query = null)
    {
        if (!$query)
            $query = $this->getqueryInstance();
        $result = $query->paginate($pageArray[1], $cols, 'page', $pageArray[0])->toArray();
        return ['data' => $result['data'], 'total' => $result['total']];
    }

    /**
     * 自动化查询辅助函数
     * 如果是值字符串类型
     * 实现多条件查询
     */
    protected function applyCondition($query, string $keyword, string $value, string $whereMethod = 'where')
    {
        if ($value[0] === '?') {
            // 模糊匹配
            $searchValue = substr($value, 1);
            $query->$whereMethod($keyword, 'like', '%' . $searchValue . '%');
        } elseif ($value[0] === '!') {
            // 不等于查询
            $notEqualValue = substr($value, 1);
            $query->$whereMethod($keyword, '<>', $notEqualValue);
        } else {
            // 等于查询
            $query->$whereMethod($keyword, '=', $value);
        }
    }
    /**
     * 推荐使用
     * 查询分组配置转ORM分组和求聚合操作
     */
    public function withGroupBy($group, $query = null)
    {
        if (!$query)
            $query = $this->getqueryInstance();
        if (!$group) {
            return $this;
        }
        $by = $group['groups'] ?? null;
        unset($group['groups']);
        if ($by) {
            $query->select($by);
            $query->groupBy($by);
        }
        $className = get_class($this);
        $className = get_class($this);
        $baseClassName = lcfirst(basename(str_replace('\\', '/', $className)));
        $query->selectRaw('COUNT(*) as total_count');
        $query->selectRaw('COUNT(*) as total_' . $baseClassName . '_count');
        foreach ($group as $field => $operators) {
            if (is_array($operators)) {
                foreach ($operators as $operator) {
                    if (!$operator) {
                        continue;
                    }
                    $alias = '';
                    switch ($operator) {
                        case '>':
                            $alias = "max_{$field}";
                            $query->selectRaw("MAX({$field}) AS {$alias}");
                            break;
                        case '<':
                            $alias = "min_{$field}";
                            $query->selectRaw("MIN({$field}) AS {$alias}");
                            break;
                        case '=':
                            $alias = "avg_{$field}";
                            $query->selectRaw("FORMAT(AVG({$field}), 4) AS {$alias}");
                            break;
                        case '#':
                            $alias = "count_{$field}";
                            $query->selectRaw("COUNT({$field}) AS {$alias}");
                            break;
                        case '#D':
                            $alias = "distinct_count_{$field}";
                            $query->selectRaw("COUNT(DISTINCT {$field}) AS {$alias}");
                            break;
                        case '!S':
                            continue 3;
                            break;
                    }
                }
            }
            $alias = "sum_{$field}";
            $query->selectRaw("SUM({$field}) AS {$alias}");
        }
        return $this;
    }
    /**
     * 推荐使用
     * 解析点记法的关联关系进行分组和求和
     */
    public static function withGroupByRelation(Collection $collection, array $relations, array $sumFields, array $otherFields = null, $defaultGroup = '未分类')
    {
        $groupedCollection = $collection->groupBy(function ($item) use ($relations, $defaultGroup) {
            $itemArray = $item->toArray();
            $return = '';

            foreach ($relations as $oneBy) {
                $by = explode('.', $oneBy);
                $value = $itemArray;

                foreach ($by as $key) {
                    $key = preg_replace('/(?<!^)([A-Z])/', '_$1', $key);
                    $key = strtolower($key);

                    if (isset($value[$key])) {
                        $value = $value[$key];
                    } else {
                        $value = $defaultGroup;
                        break;
                    }
                }

                $return .= $value;
            }

            return $return;
        });
        //dump($groupedCollection->toArray());
        $return = [];
        foreach ($groupedCollection as $key => $value) {
            $groupSum = [];
            $groupSum['total_count'] = 0;

            if (!is_array($value)) {
                $value = $value->toArray(); // 修复将对象转换为数组的问题
            }
            foreach ($sumFields as $field) {
                $groupSum['sum_' . $field] = 0;
            }

            array_map(function ($item) use ($sumFields, $otherFields, &$groupSum) {
                foreach ($sumFields as $field) {
                    $groupSum['sum_' . $field] += $item[$field] ?? ($item['sum_' . $field] ?? 0);
                }
                if ($otherFields) {
                    foreach ($otherFields as $field => $operators) {
                        foreach ($operators as $operator) {
                            switch ($operator) {
                                case '>':
                                    $alias = "max_{$field}";
                                    $groupSum[$alias] = isset($groupSum[$alias]) ? max($groupSum[$alias], $item[$alias]) : $item[$alias];
                                    break;
                                case '<':
                                    $alias = "min_{$field}";
                                    $groupSum[$alias] = isset($groupSum[$alias]) ? min($groupSum[$alias], $item[$alias]) : $item[$alias];
                                    break;
                                case '=':
                                    //先加起来，后面除去
                                    $alias = "avg_{$field}";
                                    $groupSum[$alias] = isset($groupSum[$alias]) ? $groupSum[$alias] +  $item[$alias] : $item[$alias];
                                    break;
                                default:
                                    continue 2;
                                    break;
                            }
                        }
                    }
                }
                $groupSum = $groupSum + $item;
                //兼容两种分组
                isset($item['total_count']) ? $groupSum['total_count'] += $item['total_count'] : '';
            }, $value);

            //实现关联字段分组的总数量计算
            $groupSum['total_count'] == 0 ? $groupSum['total_count'] = count($value) : '';
            $groupSum['group'] = $key; // 将分组的字段总和附加到分组集合中

            if ($otherFields) {
                foreach ($groupSum as $key => &$one) {
                    if (!is_numeric($one) || !$one) {
                        continue;
                    }

                    $one = floatval($one);

                    if (strpos($key, "avg_") === 0) {
                        $one = round($one / $groupSum['total_count'], 4);
                    } else {
                        $one = round($one, 4);
                    }
                }
            }
            $return[] = $groupSum;
        }
        return $return;
    }
    /**
     * 推荐使用
     * 查询排序配置转ORM排序操作
     */
    public function withSort(array $param, $query = null)
    {
        if (!$query) {
            $query = $this->getqueryInstance();
        }
        $orderbys = $param ?? [];
        foreach ($orderbys as $field => $direction) {
            $query->orderBy($field, $direction === 0 ? 'asc' : 'desc');
        }
        return $this;
    }
    /**
     * 推荐使用
     * 集合排序配置转集合排序操作
     */
    public static function withCollectionSort($collection, array $param)
    {
        $orderbys = $param ?? [];
        if (!$orderbys) return $collection;
        if (is_array($collection) && !($collection instanceof Collection)) $collection = collect($collection);

        foreach ($orderbys as $field => $direction) {
            $sortedCollection = $collection->sortBy($field, SORT_REGULAR, $direction);
        }

        return $sortedCollection->values()->toArray();
    }
    /**
     * 推荐使用 指定生成树
     */
    public static function generateTreeData($data, $mainField, $parentField)
    {
        $tree = array();
        $references = array();

        // 构建引用关系数组
        foreach ($data as $item) {
            $itemId = $item[$mainField];
            $parentId = $item[$parentField];

            if (!isset($references[$itemId])) {
                $references[$itemId] = array();
            }

            $item['children'] = &$references[$itemId];
            $references[$parentId][] = &$item;
        }

        // 找到根节点，即没有父节点的节点
        foreach ($data as $item) {
            $parentId = $item[$parentField];

            if (!isset($references[$parentId])) {
                $tree[] = $item;
            }
        }

        return $tree;
    }
}
