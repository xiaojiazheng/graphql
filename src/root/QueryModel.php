<?php

namespace xiaobe\Graphql\root;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use support\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;
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
    /**.
     * 关闭时间戳维护
     */
    public $timestamps = false;

    /**
     * 执行查询
     * 实现
     * 1.where 多条件 与或 模糊 精确 范围 包含
     * 2.with 关联查询 递归关联查询 关联查询挑选列
     * 2.order 根据主查询分组 求和
     */
    public function executeQuery($QLbody)
    {
        $query = $this->getqueryInstance();
        $this->withWhere($QLbody['properties']);
        $this->withSum($query, $QLbody);
        return $this->gotSuccess($query, $QLbody);
    }
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
     * 实现自动化查询功能
     * @param array $properties 图表查询的properties体，也就是输入体的prop属性，图标查询和变更服务会自动转化
     * @param mixed  $query 查询对象，不指定默认从当前模型拿
     * @param boolean $withmode 递归处理关联查询用的，不用管
     */
    public function withWhere(&$properties, $query = null, $withmode = false)
    {
        if (!$query) {
            $query = $this->getqueryInstance();
        }
        if (!$properties)
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
     * @param string $primaryKey 编辑操作依照的字段名，默认为模型定义的主键
     * @return int 受影响的行数
     */
    public function withEdit(array $data, $primaryKey = null)
    {
        if (empty($data)) {
            return 0;
        }
        if (!$primaryKey || !is_string($primaryKey)) {
            $primaryKey = $this->primaryKey;
        }
        if (!isset($data[$primaryKey])) {
            throw new WithHookParamException('编辑操作被阻止，必须提供有效的唯一键', 201);
        }
        // 提取主键值
        $id = $data[$primaryKey];
        // 从$data数组中移除主键字段
        unset($data[$primaryKey]);
        // 执行更新操作
        $query = $this->getqueryInstance();
        return $query->where($primaryKey, '=', $id)->update($data);
    }

    /**
     * 轻量级删除，根据某个字段删除，默认主键
     * @param string $value 某字段的值
     * @param string $primaryKey 字段名，默认为模型定义的主键
     * @param string $softkey 是否软删除 为空不删除，否则指定字段设置为0
     */
    public function withDel($value, $primaryKey = null, $softkey = null)
    {
        if (empty($value)) {
            throw new WithHookParamException('删除操作被阻止，必须提供有效的唯一键', 101);
        }

        if (!$primaryKey) {
            $primaryKey = $this->primaryKey;
        }
        $query = $this->getqueryInstance();
        if ($softkey) {
            // 软删除，将指定字段值改为0
            return $query->where($primaryKey, '=', $value)->update([$softkey => 0]);
        } else {
            // 直接删除
            return $query->where($primaryKey, '=', $value)->delete();
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
     * 集合数组分页利器
     * @return array 分页后的数组
     */
    public function withPaginate($result, $page = 1, $limit = 10)
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
     * 实现总和功能
     * 只能放在最后一个钩子
     * 由于需要修改QLbody体properties字段
     */
    protected function withSum($query, &$QLbody)
    {
        if ($QLbody['sum']) {
            if (isset($QLbody['sum']['order']) && $QLbody['sum']['order']) {
                $sonOrder = [];
                $order = array_filter($QLbody['sum']['order'], function ($value) use (&$sonOrder) {
                    $result = strpos($value, '.') === false;
                    if (!$result) {
                        $sonOrder[] = $value;
                    }
                    return $result;
                });
                unset($QLbody['sum']['order']);
                if ($sonOrder) {
                    $QLbody['addition']['groupBy'] = $sonOrder;
                }
                if (!$order) {
                    return;
                }
                $QLbody['properties'] = array_intersect_key($QLbody['properties'], array_flip($order));
                $columns = array_keys($QLbody['properties']);
                $query->select($columns)->groupBy($columns);
            }

            $query->selectRaw('COUNT(*) as total_count');
            $sumFields = array_keys($QLbody['sum']);

            if ($sumFields) {
                foreach ($sumFields as $field) {
                    if (is_array($QLbody['sum'][$field])) {
                        foreach ($QLbody['sum'][$field] as $operator) {
                            if (!$operator) {
                                continue;
                            }

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
                                    continue 2; // Skip the field's SUM calculation
                            }

                            $alias = "sum_{$field}";
                            $query->selectRaw("SUM({$field}) AS {$alias}");
                        }
                    } else {
                        $alias = "sum_{$field}";
                        $query->selectRaw("SUM({$field}) AS {$alias}");
                    }
                }
            }
        }
    }
    /**
     * 暴漏给外部的，自动化查询求聚合函数
     * @return mixed query
     */
    public function withQuerySum($QLbody, $query = null)
    {
        if (!$query)
            $query = $this->getqueryInstance();
        if (!$QLbody['sum']) {
            return $query;
        }
        $this->withOrderBy($query, $QLbody);
        $order = $QLbody['sum']['order'] ?? null;
        unset($QLbody['sum']['order']);

        $columns = $order ? array_keys(array_intersect_key($QLbody['properties'], array_flip(array_filter($order, function ($value) {
            return strpos($value, '.') === false;
        })))) : array_keys($QLbody['properties']);


        if ($order) {
            $query->select($columns);
            $query->groupBy($columns);
        }
        $className = get_class($this);
        $className = get_class($this);
        $baseClassName = lcfirst(basename(str_replace('\\', '/', $className)));
        if (isset($QLbody['sum']['total_count'])) unset($QLbody['sum']['total_count']);
        $query->selectRaw('COUNT(*) as total_count');
        $query->selectRaw('COUNT(*) as total_' . $baseClassName . '_count');
        foreach ($QLbody['sum'] as $field => $operators) {
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
                            continue 2;
                            break;
                    }
                }
            }
            $alias = "sum_{$field}";
            $query->selectRaw("SUM({$field}) AS {$alias}");
        }
        return $query;
    }
    /**
     * 暴漏给外部的，自动化查集合求聚合函数
     */
    public function withCollectionSum($collection, $QLbody)
    {
        if ($QLbody['sum']) {
            if (isset($QLbody['sum']['order']) && $QLbody['sum']['order']) {
                $order = $QLbody['sum']['order'];
                unset($QLbody['sum']['order']);
                if ($order) {
                    $columns = array_keys(array_intersect_key($QLbody['properties'], array_flip($order)));
                    $collection = $collection->groupBy($columns);
                }
                $result = [];
                $totalSum = [];
                $sumFields = array_keys($QLbody['sum']);
                if ($sumFields) {
                    foreach ($collection as $key => $group) {
                        $fieldResult = [];
                        $first = $group->first()->toArray();
                        foreach ($sumFields as $field) {
                            $exist = isset($first[$field]);
                            if (is_array($QLbody['sum'][$field])) {
                                $operators = $QLbody['sum'][$field];
                                foreach ($operators as $operator) {
                                    if (!$operator) continue;
                                    switch ($operator) {
                                        case '>':
                                            $maxValue = $group->max($exist ? $field : "max_{$field}");
                                            $fieldResult["max_{$field}"] = $maxValue;
                                            $totalSum["g_max_{$field}"] = ($totalSum["g_max_{$field}"] ?? 0) + $maxValue;
                                            break;
                                        case '<':
                                            $minValue = $group->min($exist ? $field : "min_{$field}");
                                            $fieldResult["min_{$field}"] = $minValue;
                                            $totalSum["g_min_{$field}"] = ($totalSum["g_min_{$field}"] ?? 0) + $minValue;
                                            break;
                                        case '=':
                                            $avgValue = $group->avg($exist ? $field : "avg_{$field}");
                                            $fieldResult["avg_{$field}"] = round($avgValue, 8);
                                            $totalSum["g_avg_{$field}"] = ($totalSum["g_avg_{$field}"] ?? 0) + $avgValue;
                                            break;
                                        case '!S':
                                            continue 2;
                                            break;
                                    }
                                }
                            }
                            $sumValue = $group->sum($exist ? $field : "sum_{$field}");
                            $fieldResult["sum_{$field}"] = $sumValue;
                            $totalSum["g_sum_{$field}"] = ($totalSum["g_sum_{$field}"] ?? 0) + $sumValue;
                        }
                        foreach ($order as $one) {
                            if (isset($fieldResult[$one]) && $fieldResult[$one]) {
                                break;
                            }
                            $first = $group->pluck($one)->toArray();

                            if ($first[0]) {
                                $fieldResult[$one] = $first[0];
                            }
                        }
                        $fieldResult['group'] = !empty($key) ? $key : '无法分类';


                        if (!isset($totalSum['g_sum_total_count'])) {
                            $exist = isset($first['total_count']);
                            $totalSum["sum_total_count"] = ($totalSum["sum_total_count"] ?? 0) +  $group->sum('total_count');
                        }
                        $result[] = $fieldResult;
                    }
                }
                $return = $this->withCollectionOrderBy(collect($result), $QLbody);
                if (isset($QLbody['addition']['limit'])) {
                    $return = $this->withPaginate($result, $QLbody['addition']['page'], $QLbody['addition']['limit']);
                }
                $return = array_merge(['data' => $return], $totalSum);
                return $return;
            }
        }
        return [];
    }
    /**
     * 暴漏给外部的，自动化集合排序函数
     * 0 是顺序
     * 1 逆序
     */
    public function withCollectionOrderBy(Collection $collection, $QLbody)
    {
        $orderbys = $QLbody['addition']['orderby'] ?? [];
        if (!$orderbys) return $collection;

        foreach ($orderbys as $field => $direction) {
            $sortedCollection = $collection->sortBy($field, SORT_REGULAR, $direction == 0);
        }

        return $sortedCollection->values()->toArray();
    }


    /**
     * 暴漏给外部的，自动化查询排序函数
     * 0 是顺序
     * 1 逆序
     */
    public function withOrderBy($query, $QLbody)
    {
        $orderbys = $QLbody['addition']['orderby'] ?? [];
        foreach ($orderbys as $field => $direction) {
            $query->orderBy($field, $direction === 0 ? 'asc' : 'desc');
        }
        return $query;
    }
    /**
     * 这个函数尾巴有点大，找个时间优化
     */
    protected function gotSuccess($query, $QLbody)
    {
        $addition = $QLbody['addition'];
        $columns = array_keys($QLbody['properties']);
        $this->withOrderBy($query, $QLbody);
        $limit = $addition['limit'] ?? 50;
        $page = $addition['page'] ?? 1;

        if (isset($addition['groupBy'])) {
            $needSum = array_keys($QLbody['sum']);
            $groupedCollection = $this->getGroupedCollection($query, $columns, $needSum, $QLbody);
            $returnArray = $this->processGroupedCollection($groupedCollection, $needSum, $QLbody, $limit, $page);

            return $returnArray;
        }

        $take =  isset($addition['take']) ? $addition['take'] : false;
        if ($take) $query->take($take);
        $result = $query->get($columns);

        if (isset($addition['limit'])) {
            if ($QLbody['sum']) {
                $totalCount = $result->sum('total_count');
                $result = $this->withPaginate($result, $page, $limit);
                $result['total_count'] = $totalCount;
            } else {
                $result = $this->withPaginate($result, $page, $limit);
            }
        }

        return $result;
    }
    /**
     * 自用支持关联key集合分组
     */
    protected function getGroupedCollection($query, $columns, $needSum, $QLbody)
    {
        $collection = $query->get(array_merge($columns, $needSum));
        $groupedCollection = $collection->groupBy(function ($item) use ($QLbody) {
            $itemArray = toArray($item);
            $return = '';

            foreach ($QLbody['addition']['groupBy'] as $oneBy) {
                $by = explode('.', $oneBy);
                $value = $itemArray;

                foreach ($by as $key) {
                    $key = preg_replace('/(?<!^)([A-Z])/', '_$1', $key);
                    $key = strtolower($key);

                    if (isset($value[$key])) {
                        $value = $value[$key];
                    } else {
                        $value = '未分类';
                        break;
                    }
                }

                $return .= $value;
            }

            return $return;
        });

        return $groupedCollection;
    }
    /**
     * 自用支持关联key集合分组后求和
     */
    protected function processGroupedCollection($groupedCollection, $needSum, $QLbody, $limit, $page)
    {
        $returnArray = [];
        $start = ($page - 1) * $limit;
        $total = 0; // 修复计算$total的问题

        foreach ($groupedCollection as $key => $value) {
            $groupSum = [];
            $groupSum['total_count'] = 0;

            if (!is_array($value)) {
                $value = toArray($value); // 修复将对象转换为数组的问题
            }
            foreach ($needSum as $field) {
                $groupSum['sum_' . $field] = 0;
            }

            array_map(function ($item) use ($QLbody, $needSum, &$groupSum) {
                foreach ($needSum as $field) {
                    $groupSum['sum_' . $field] += $item[$field] ?? $item['sum_' . $field];
                    $sumType = $QLbody['sum'][$field];

                    if (!$sumType) {
                        continue;
                    }

                    if (is_array($sumType)) {
                        foreach ($sumType as $operator) {
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
                                    // 处理其他操作符的情况，可以抛出错误或者跳过该操作符
                                    break;
                            }
                        }
                    }
                }

                //兼容两种分组
                isset($item['total_count']) ? $groupSum['total_count'] += $item['total_count'] : '';
            }, $value);
            //实现关联字段分组的总数量计算
            $groupSum['total_count'] == 0 ? $groupSum['total_count'] = count($value) : '';
            $groupSum['group'] = $key; // 将分组的字段总和附加到分组集合中

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

            $returnArray[] = $groupSum;
            $total += 1; // 每次循环添加元素后增加$total的值
        }

        if ($start >= $total) {
            return ['data' => [], 'total' => $total];
        }
        $end = min($start + $limit, $total);
        $pagedData = array_slice($returnArray, $start, $end - $start);

        return ['data' => $pagedData, 'total' => $total];
    }
}
